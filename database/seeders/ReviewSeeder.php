<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;
use App\Models\Property;
use App\Models\User;
use App\Models\Review;
use App\Models\BookingDetail;
use Carbon\Carbon;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('role', 'user')->get();
        
        if ($users->isEmpty()) {
            $this->command->warn('⚠️  No users found. Skipping reviews creation.');
            return;
        }

        $reviews = [
            ['rating' => 5, 'title' => 'Tuyệt vời!', 'comment' => 'Phòng rất đẹp, sạch sẽ và tiện nghi đầy đủ. Nhân viên phục vụ nhiệt tình.'],
            ['rating' => 5, 'title' => 'Rất hài lòng', 'comment' => 'Trải nghiệm tuyệt vời, sẽ quay lại lần sau.'],
            ['rating' => 4, 'title' => 'Tốt', 'comment' => 'Phòng ổn, giá cả hợp lý.'],
            ['rating' => 4, 'title' => 'Đáng giá', 'comment' => 'Vị trí thuận tiện, không gian thoải mái.'],
            ['rating' => 3, 'title' => 'Bình thường', 'comment' => 'Phòng ổn nhưng có thể cải thiện thêm.'],
        ];

        // Lấy booking details để gán vào reviews
        $bookingDetails = \App\Models\BookingDetail::all();
        
        if ($bookingDetails->isEmpty()) {
            $this->command->warn('⚠️  No booking details found. Skipping reviews creation.');
            return;
        }
        
        // Tạo reviews cho các booking details có sẵn
        $selectedBookingDetails = $bookingDetails->random(min(20, $bookingDetails->count()));
        
        foreach ($selectedBookingDetails as $bookingDetail) {
            $reviewData = $reviews[array_rand($reviews)];
            $user = $users->random();
            
            Review::create([
                'booking_details_id' => $bookingDetail->id,
                'user_id' => $user->id,
                'property_id' => $bookingDetail->room->property_id,
                'room_id' => $bookingDetail->room_id,
                'rating' => $reviewData['rating'],
                'title' => $reviewData['title'],
                'comment' => $reviewData['comment'],
                'is_verified_purchase' => rand(0, 1) === 1,
                'is_helpful_count' => rand(0, 10),
                'is_not_helpful_count' => rand(0, 2),
                'status' => ['pending', 'approved', 'approved'][rand(0, 2)],
                'created_at' => Carbon::now()->subDays(rand(1, 90)),
            ]);
        }

        $this->command->info('✅ Created reviews for rooms');
    }
}

