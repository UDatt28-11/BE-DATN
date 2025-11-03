<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Promotion;
use App\Models\Property;
use Carbon\Carbon;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        // Create sample promotions
        $promotion1 = Promotion::create([
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
        ]);

        $promotion2 = Promotion::create([
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
        ]);

        $promotion3 = Promotion::create([
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
        ]);

        // Attach rooms to promotion2
        $promotion2->rooms()->attach([1, 2]);

        // Attach room types to promotion3
        $promotion3->roomTypes()->attach([1, 2]);

        echo "✅ Promotion seeder completed\n";
    }
}
