<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Review;
use App\Models\BookingDetail;
use App\Models\BookingOrder;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    private $property;
    private $room;
    private $user;
    private $bookingDetail;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();

        $this->property = Property::create([
            'owner_id' => User::factory()->create()->id,
            'name' => 'Test Property',
            'address' => '123 Test St',
            'status' => 'active'
        ]);

        $roomType = RoomType::create([
            'property_id' => $this->property->id,
            'name' => 'Standard',
            'description' => 'Standard room',
            'base_price' => 100,
        ]);

        $this->room = Room::create([
            'property_id' => $this->property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Test Room',
            'max_adults' => 2,
            'max_children' => 1,
            'price_per_night' => 100,
            'status' => 'available'
        ]);

        $bookingOrder = BookingOrder::create([
            'guest_id' => $this->user->id,
            'order_code' => 'TEST001',
            'customer_name' => 'Test User',
            'customer_phone' => '0901234567',
            'customer_email' => 'test@example.com',
            'total_amount' => 500,
            'payment_method' => 'cash',
            'status' => 'completed'
        ]);

        $this->bookingDetail = BookingDetail::create([
            'booking_order_id' => $bookingOrder->id,
            'room_id' => $this->room->id,
            'check_in_date' => now()->subDays(5),
            'check_out_date' => now()->subDays(3),
            'number_of_adults' => 2,
            'number_of_children' => 0,
            'price' => 500,
        ]);
    }

    public function test_can_create_review()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/reviews', [
            'booking_details_id' => $this->bookingDetail->id,
            'rating' => 5,
            'title' => 'Tuyệt vời!',
            'comment' => 'Phòng rất sạch sẽ',
            'photos' => [],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('reviews', [
            'booking_details_id' => $this->bookingDetail->id,
            'user_id' => $this->user->id,
            'rating' => 5,
        ]);
    }

    public function test_user_can_only_review_once()
    {
        $this->actingAs($this->user);

        // Create first review
        $this->postJson('/api/reviews', [
            'booking_details_id' => $this->bookingDetail->id,
            'rating' => 5,
            'title' => 'Tuyệt vời!',
            'comment' => 'Phòng rất sạch sẽ',
        ]);

        // Try to create second review
        $response = $this->postJson('/api/reviews', [
            'booking_details_id' => $this->bookingDetail->id,
            'rating' => 4,
            'title' => 'Tốt',
            'comment' => 'Bình thường',
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }

    public function test_can_get_reviews()
    {
        Review::create([
            'booking_details_id' => $this->bookingDetail->id,
            'user_id' => $this->user->id,
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'rating' => 5,
            'title' => 'Tuyệt vời!',
            'comment' => 'Phòng sạch sẽ',
            'is_verified_purchase' => true,
            'status' => 'approved',
        ]);

        $response = $this->getJson('/api/reviews');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.total', 1);
    }

    public function test_can_get_property_reviews()
    {
        Review::create([
            'booking_details_id' => $this->bookingDetail->id,
            'user_id' => $this->user->id,
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'rating' => 5,
            'title' => 'Tuyệt vời!',
            'comment' => 'Phòng sạch sẽ',
            'is_verified_purchase' => true,
            'status' => 'approved',
        ]);

        $response = $this->getJson("/api/reviews/property/{$this->property->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.average_rating', 5.0);
    }

    public function test_can_mark_review_as_helpful()
    {
        $review = Review::create([
            'booking_details_id' => $this->bookingDetail->id,
            'user_id' => $this->user->id,
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'rating' => 5,
            'title' => 'Tuyệt vời!',
            'comment' => 'Phòng sạch sẽ',
            'is_verified_purchase' => true,
            'status' => 'approved',
        ]);

        $response = $this->postJson("/api/reviews/{$review->id}/mark-helpful");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertEquals(1, $review->fresh()->is_helpful_count);
    }

    public function test_can_approve_review()
    {
        $this->actingAs($this->user);

        $review = Review::create([
            'booking_details_id' => $this->bookingDetail->id,
            'user_id' => $this->user->id,
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'rating' => 5,
            'title' => 'Tuyệt vời!',
            'comment' => 'Phòng sạch sẽ',
            'is_verified_purchase' => true,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/reviews/{$review->id}/approve", [
            'admin_notes' => 'Phê duyệt'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'status' => 'approved',
        ]);
    }

    public function test_can_get_review_statistics()
    {
        Review::create([
            'booking_details_id' => $this->bookingDetail->id,
            'user_id' => $this->user->id,
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'rating' => 5,
            'title' => 'Tuyệt vời!',
            'comment' => 'Phòng sạch sẽ',
            'status' => 'approved',
        ]);

        $response = $this->getJson('/api/reviews/statistics/overview');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => ['total_reviews', 'approved_reviews', 'average_rating']]);
    }
}
