<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Promotion;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class PromotionControllerTest extends TestCase
{
    use RefreshDatabase;

    private $property;
    private $room;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->property = Property::create([
            'owner_id' => User::factory()->create()->id,
            'name' => 'Test Property',
            'address' => '123 Test St',
            'status' => 'active'
        ]);

        $this->room = Room::create([
            'property_id' => $this->property->id,
            'room_type_id' => 1,
            'name' => 'Test Room',
            'max_adults' => 2,
            'max_children' => 1,
            'price_per_night' => 100,
            'status' => 'available'
        ]);

        $this->user = User::factory()->create();
    }

    public function test_can_create_promotion()
    {
        $response = $this->postJson('/api/promotions', [
            'property_id' => $this->property->id,
            'code' => 'TEST100',
            'description' => 'Test promotion',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date' => Carbon::now()->addDays(30)->format('Y-m-d'),
            'is_active' => true,
            'applicable_to' => 'all',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('promotions', ['code' => 'TEST100']);
    }

    public function test_can_get_promotions()
    {
        Promotion::create([
            'property_id' => $this->property->id,
            'code' => 'PROMO1',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => Carbon::now(),
            'end_date' => Carbon::now()->addDays(30),
            'is_active' => true,
            'applicable_to' => 'all',
        ]);

        $response = $this->getJson('/api/promotions');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.total', 1);
    }

    public function test_can_validate_promotion()
    {
        $promotion = Promotion::create([
            'property_id' => $this->property->id,
            'code' => 'VALIDATE',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'min_purchase_amount' => 1000,
            'start_date' => Carbon::now()->subDays(1),
            'end_date' => Carbon::now()->addDays(30),
            'is_active' => true,
            'applicable_to' => 'all',
        ]);

        $response = $this->postJson('/api/promotions/validate', [
            'code' => 'VALIDATE',
            'total_amount' => 5000,
            'property_id' => $this->property->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.discount_amount', 1000); // 20% of 5000
        $response->assertJsonPath('data.final_amount', 4000);
    }

    public function test_promotion_discount_calculation()
    {
        $promotion = Promotion::create([
            'property_id' => $this->property->id,
            'code' => 'CALC',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'max_discount_amount' => 300,
            'start_date' => Carbon::now(),
            'end_date' => Carbon::now()->addDays(30),
            'is_active' => true,
            'applicable_to' => 'all',
        ]);

        // Test discount calculation
        $discount = $promotion->calculateDiscount(5000);
        $this->assertEquals(300, $discount); // Should be capped at max_discount_amount
    }

    public function test_can_update_promotion()
    {
        $promotion = Promotion::create([
            'property_id' => $this->property->id,
            'code' => 'UPDATE',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => Carbon::now(),
            'end_date' => Carbon::now()->addDays(30),
            'is_active' => true,
            'applicable_to' => 'all',
        ]);

        $response = $this->putJson("/api/promotions/{$promotion->id}", [
            'discount_value' => 15,
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'discount_value' => 15,
            'is_active' => false,
        ]);
    }

    public function test_can_delete_promotion()
    {
        $promotion = Promotion::create([
            'property_id' => $this->property->id,
            'code' => 'DELETE',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => Carbon::now(),
            'end_date' => Carbon::now()->addDays(30),
            'is_active' => true,
            'applicable_to' => 'all',
        ]);

        $response = $this->deleteJson("/api/promotions/{$promotion->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseMissing('promotions', ['id' => $promotion->id]);
    }
}
