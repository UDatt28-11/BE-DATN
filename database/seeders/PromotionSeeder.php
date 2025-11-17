<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\Promotion;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        $properties = Property::all();

        foreach ($properties as $property) {
            // Promotion 1: Giảm giá 10% cho tất cả phòng
            Promotion::create([
                'property_id' => $property->id,
                'code' => 'WELCOME10-P' . $property->id,
                'description' => 'Giảm 10% cho đơn hàng đầu tiên',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'max_discount_amount' => 500000,
                'min_purchase_amount' => 1000000,
                'max_usage_limit' => 100,
                'max_usage_per_user' => 1,
                'usage_count' => 0,
                'start_date' => now(),
                'end_date' => now()->addMonths(3),
                'is_active' => true,
                'applicable_to' => 'all',
                'additional_settings' => [],
            ]);

            // Promotion 2: Giảm giá 200k cho đơn từ 2 triệu
            Promotion::create([
                'property_id' => $property->id,
                'code' => 'SAVE200K-P' . $property->id,
                'description' => 'Giảm 200.000đ cho đơn từ 2.000.000đ',
                'discount_type' => 'fixed_amount',
                'discount_value' => 200000,
                'max_discount_amount' => null,
                'min_purchase_amount' => 2000000,
                'max_usage_limit' => 50,
                'max_usage_per_user' => 2,
                'usage_count' => 0,
                'start_date' => now(),
                'end_date' => now()->addMonths(2),
                'is_active' => true,
                'applicable_to' => 'all',
                'additional_settings' => [],
            ]);

            // Promotion 3: Giảm giá 15% cho phòng Suite
            Promotion::create([
                'property_id' => $property->id,
                'code' => 'SUITE15-P' . $property->id,
                'description' => 'Giảm 15% cho phòng Suite',
                'discount_type' => 'percentage',
                'discount_value' => 15,
                'max_discount_amount' => 1000000,
                'min_purchase_amount' => 0,
                'max_usage_limit' => 30,
                'max_usage_per_user' => 1,
                'usage_count' => 0,
                'start_date' => now(),
                'end_date' => now()->addMonths(1),
                'is_active' => true,
                'applicable_to' => 'specific_room_types',
                'additional_settings' => [],
            ]);
        }
    }
}

