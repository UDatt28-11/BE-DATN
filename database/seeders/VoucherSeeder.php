<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Property;
use App\Models\Voucher;
use Carbon\Carbon;

class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        $properties = Property::all();
        
        if ($properties->isEmpty()) {
            $this->command->warn('⚠️  No properties found. Skipping vouchers creation.');
            return;
        }

        foreach ($properties as $property) {
            // Tạo 3-5 vouchers cho mỗi property
            $voucherCount = rand(3, 5);
            
            for ($i = 1; $i <= $voucherCount; $i++) {
                $startDate = Carbon::now()->addDays(rand(-15, 15));
                $endDate = $startDate->copy()->addDays(rand(30, 60));
                
                // Tạo code unique
                $code = 'VOUCHER' . $property->id . '_' . $i . '_' . time() . '_' . rand(1000, 9999);
                
                Voucher::create([
                    'property_id' => $property->id,
                    'code' => $code,
                    'discount_type' => ['percentage', 'fixed_amount'][rand(0, 1)],
                    'discount_value' => $i === 1 ? rand(15, 25) : rand(100000, 300000),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'is_active' => $endDate->isFuture(),
                ]);
            }
        }

        $this->command->info('✅ Created vouchers for all properties');
    }
}

