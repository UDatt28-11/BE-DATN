<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Property;
use App\Models\Promotion;
use Carbon\Carbon;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        $property = Property::first();
        
        if (!$property) {
            $this->command->warn('⚠️  No property found. Skipping promotions creation.');
            return;
        }

        // Xóa promotions cũ
        Promotion::where('property_id', $property->id)->delete();

        // Tạo 2 promotions sạch và thực tế
        $promotions = [
            [
                'code' => 'WELCOME2024',
                'description' => 'Mã giảm giá chào mừng khách mới - Giảm 15% cho đơn hàng đầu tiên',
                'discount_type' => 'percentage',
                'discount_value' => 15,
                'max_discount_amount' => 500000,
                'min_purchase_amount' => 1000000,
                'max_usage_limit' => 100,
                'max_usage_per_user' => 1,
                'start_date' => Carbon::now()->subDays(7),
                'end_date' => Carbon::now()->addDays(60),
            ],
            [
                'code' => 'LONGSTAY',
                'description' => 'Giảm giá cho khách ở từ 3 đêm trở lên - Giảm 200.000đ',
                'discount_type' => 'fixed_amount',
                'discount_value' => 200000,
                'max_discount_amount' => 200000,
                'min_purchase_amount' => 2000000,
                'max_usage_limit' => 50,
                'max_usage_per_user' => 2,
                'start_date' => Carbon::now()->subDays(7),
                'end_date' => Carbon::now()->addDays(90),
            ],
        ];

        foreach ($promotions as $promoData) {
            Promotion::create([
                'property_id' => $property->id,
                'code' => $promoData['code'],
                'description' => $promoData['description'],
                'discount_type' => $promoData['discount_type'],
                'discount_value' => $promoData['discount_value'],
                'max_discount_amount' => $promoData['max_discount_amount'],
                'min_purchase_amount' => $promoData['min_purchase_amount'],
                'max_usage_limit' => $promoData['max_usage_limit'],
                'max_usage_per_user' => $promoData['max_usage_per_user'],
                'usage_count' => 0,
                'start_date' => $promoData['start_date'],
                'end_date' => $promoData['end_date'],
                'is_active' => $promoData['end_date']->isFuture(),
                'applicable_to' => 'all',
            ]);
        }

        $this->command->info('✅ Created ' . count($promotions) . ' promotions for property');
    }
}

