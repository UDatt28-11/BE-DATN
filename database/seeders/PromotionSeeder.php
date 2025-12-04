<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Promotion;
use Carbon\Carbon;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        $promotions = [
            [
                'property_id' => 1,
                'code' => 'SUMMER2024',
                'description' => 'Giảm giá mùa hè 20%',
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'max_discount_amount' => 500,
                'min_purchase_amount' => 1000,
                'max_usage_limit' => 100,
                'max_usage_per_user' => 1,
                'start_date' => Carbon::now()->subDays(30),
                'end_date' => Carbon::now()->addDays(60),
                'is_active' => true,
                'applicable_to' => 'all',
            ],
            [
                'property_id' => 1,
                'code' => 'VIP100',
                'description' => 'Giảm giá 100k cho thành viên VIP',
                'discount_type' => 'fixed_amount',
                'discount_value' => 100,
                'min_purchase_amount' => 500,
                'max_usage_limit' => 50,
                'max_usage_per_user' => 2,
                'start_date' => Carbon::now()->subDays(60),
                'end_date' => Carbon::now()->addDays(30),
                'is_active' => true,
                'applicable_to' => 'specific_rooms',
                'rooms' => [1, 2],
            ],
            [
                'property_id' => 1,
                'code' => 'NEWYEAR15',
                'description' => 'Chào năm mới 15%',
                'discount_type' => 'percentage',
                'discount_value' => 15,
                'max_discount_amount' => 300,
                'max_usage_limit' => 200,
                'start_date' => Carbon::now()->subDays(5),
                'end_date' => Carbon::now()->addDays(30),
                'is_active' => true,
                'applicable_to' => 'specific_room_types',
                'room_types' => [1, 2],
            ],
            [
                'property_id' => 2,
                'code' => 'WEEKDAYRELAX12',
                'description' => 'Ưu đãi ngày thường 12% cho Mountain Lodge Đà Lạt',
                'discount_type' => 'percentage',
                'discount_value' => 12,
                'max_discount_amount' => 300,
                'min_purchase_amount' => 800,
                'max_usage_limit' => 150,
                'max_usage_per_user' => 1,
                'start_date' => Carbon::now()->subDays(10),
                'end_date' => Carbon::now()->addDays(90),
                'is_active' => true,
                'applicable_to' => 'all',
            ],
            [
                'property_id' => 2,
                'code' => 'DALATSUITE25',
                'description' => 'Giảm 25% cho phòng Suite cao cấp',
                'discount_type' => 'percentage',
                'discount_value' => 25,
                'max_discount_amount' => 600,
                'min_purchase_amount' => 1500,
                'max_usage_limit' => 80,
                'max_usage_per_user' => 1,
                'start_date' => Carbon::now()->subDays(20),
                'end_date' => Carbon::now()->addDays(45),
                'is_active' => true,
                'applicable_to' => 'specific_room_types',
                'room_types' => [4],
            ],
            [
                'property_id' => 3,
                'code' => 'OCEANFLASH30',
                'description' => 'Flash sale 30% phòng view biển',
                'discount_type' => 'percentage',
                'discount_value' => 30,
                'max_discount_amount' => 700,
                'max_usage_limit' => 120,
                'max_usage_per_user' => 1,
                'start_date' => Carbon::now()->subDays(2),
                'end_date' => Carbon::now()->addDays(15),
                'is_active' => true,
                'applicable_to' => 'specific_rooms',
                'rooms' => [7, 8],
            ],
            [
                'property_id' => 3,
                'code' => 'GARDENSTAY80',
                'description' => 'Giảm 80k khi đặt phòng Garden View từ 2 đêm',
                'discount_type' => 'fixed_amount',
                'discount_value' => 80,
                'min_purchase_amount' => 600,
                'max_usage_limit' => 200,
                'max_usage_per_user' => 3,
                'start_date' => Carbon::now()->subDays(12),
                'end_date' => Carbon::now()->addDays(70),
                'is_active' => true,
                'applicable_to' => 'specific_room_types',
                'room_types' => [6],
            ],
            [
                'property_id' => 1,
                'code' => 'EARLYBIRD25',
                'description' => 'Đặt sớm 25% cho Homestay Sapa View',
                'discount_type' => 'percentage',
                'discount_value' => 25,
                'max_discount_amount' => 400,
                'min_purchase_amount' => 800,
                'max_usage_limit' => 60,
                'max_usage_per_user' => 1,
                'start_date' => Carbon::now()->subDays(15),
                'end_date' => Carbon::now()->addDays(45),
                'is_active' => true,
                'applicable_to' => 'all',
            ],
            [
                'property_id' => 2,
                'code' => 'LASTMINUTE10',
                'description' => 'Đặt cận ngày giảm ngay 10%',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'max_discount_amount' => 200,
                'max_usage_limit' => 90,
                'max_usage_per_user' => 1,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addDays(14),
                'is_active' => true,
                'applicable_to' => 'all',
            ],
            [
                'property_id' => 3,
                'code' => 'LONGBSTAY150',
                'description' => 'Ở từ 3 đêm giảm 150k',
                'discount_type' => 'fixed_amount',
                'discount_value' => 150,
                'min_purchase_amount' => 2000,
                'max_usage_limit' => 70,
                'max_usage_per_user' => 2,
                'start_date' => Carbon::now()->subDays(20),
                'end_date' => Carbon::now()->addDays(120),
                'is_active' => true,
                'applicable_to' => 'all',
            ],
        ];

        foreach ($promotions as $promotionData) {
            $rooms = $promotionData['rooms'] ?? [];
            $roomTypes = $promotionData['room_types'] ?? [];

            unset($promotionData['rooms'], $promotionData['room_types']);

            $promotion = Promotion::create($promotionData);

            if (!empty($rooms)) {
                $promotion->rooms()->attach($rooms);
            }

            if (!empty($roomTypes)) {
                $promotion->roomTypes()->attach($roomTypes);
            }
        }

        echo "✅ Promotion seeder completed\n";
    }
}
