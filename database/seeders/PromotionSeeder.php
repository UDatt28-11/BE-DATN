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
        $properties = Property::all();
        
        if ($properties->isEmpty()) {
            $this->command->warn('⚠️  No properties found. Skipping promotions creation.');
            return;
        }

        foreach ($properties as $property) {
            // Tạo 2-3 promotions cho mỗi property
            $promotionCount = rand(2, 3);
            
            for ($i = 1; $i <= $promotionCount; $i++) {
                $startDate = Carbon::now()->addDays(rand(-30, 30));
                $endDate = $startDate->copy()->addDays(rand(30, 90));
                
                Promotion::create([
                    'property_id' => $property->id,
                    'code' => strtoupper(substr($property->name, 0, 3)) . rand(1000, 9999),
                    'description' => 'Mã giảm giá ' . $i . ' cho ' . $property->name,
                    'discount_type' => ['percentage', 'fixed_amount'][rand(0, 1)],
                    'discount_value' => $i === 1 ? rand(10, 30) : rand(50000, 200000),
                    'max_discount_amount' => rand(100000, 500000),
                    'min_purchase_amount' => rand(500000, 2000000),
                    'max_usage_limit' => rand(10, 50),
                    'max_usage_per_user' => rand(1, 3),
                    'usage_count' => 0,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'is_active' => $endDate->isFuture(),
                    'applicable_to' => 'all',
                ]);
            }
        }

        $this->command->info('✅ Created promotions for all properties');
    }
}

