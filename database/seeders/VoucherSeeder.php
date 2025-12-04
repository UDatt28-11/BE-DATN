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
        $property = Property::first();
        
        if (!$property) {
            $this->command->warn('⚠️  No property found. Skipping vouchers creation.');
            return;
        }

        // Xóa vouchers cũ
        Voucher::where('property_id', $property->id)->delete();

        // Tạo 2 vouchers sạch và thực tế
        $vouchers = [
            [
                'code' => 'SUMMER2024',
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'start_date' => Carbon::now()->subDays(7),
                'end_date' => Carbon::now()->addDays(60),
            ],
            [
                'code' => 'WEEKEND100K',
                'discount_type' => 'fixed_amount',
                'discount_value' => 100000,
                'start_date' => Carbon::now()->subDays(7),
                'end_date' => Carbon::now()->addDays(90),
            ],
        ];

        foreach ($vouchers as $voucherData) {
            Voucher::create([
                'property_id' => $property->id,
                'code' => $voucherData['code'],
                'discount_type' => $voucherData['discount_type'],
                'discount_value' => $voucherData['discount_value'],
                'start_date' => $voucherData['start_date'],
                'end_date' => $voucherData['end_date'],
                'is_active' => $voucherData['end_date']->isFuture(),
            ]);
        }

        $this->command->info('✅ Created ' . count($vouchers) . ' vouchers for property');
    }
}

