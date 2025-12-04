<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Voucher;
use App\Models\Property;
use Carbon\Carbon;

class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding vouchers...');

        // Lấy property đầu tiên để tạo một số voucher riêng
        $property = Property::first();
        $propertyId = $property?->id;

        $vouchers = [
            // Voucher công khai - giảm phần trăm
            [
                'code' => 'WELCOME10',
                'name' => 'Chào mừng khách hàng mới',
                'description' => 'Giảm 10% cho đơn đặt phòng đầu tiên. Áp dụng cho tất cả các phòng.',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'min_order_amount' => 500000,
                'max_discount_amount' => 200000,
                'usage_limit' => 1000,
                'max_usage_per_user' => 1,
                'start_date' => Carbon::now()->subDays(7),
                'end_date' => Carbon::now()->addMonths(6),
                'is_active' => true,
                'is_public' => true,
                'property_id' => null, // Áp dụng cho tất cả
            ],
            [
                'code' => 'SUMMER20',
                'name' => 'Khuyến mãi mùa hè',
                'description' => 'Giảm 20% cho kỳ nghỉ mùa hè. Đặt phòng từ 2 đêm trở lên.',
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'min_order_amount' => 1000000,
                'max_discount_amount' => 500000,
                'usage_limit' => 500,
                'max_usage_per_user' => 2,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonths(3),
                'is_active' => true,
                'is_public' => true,
                'property_id' => null,
            ],
            [
                'code' => 'VIP30',
                'name' => 'Ưu đãi VIP',
                'description' => 'Giảm 30% dành cho khách hàng VIP. Mã riêng tư.',
                'discount_type' => 'percentage',
                'discount_value' => 30,
                'min_order_amount' => 2000000,
                'max_discount_amount' => 1000000,
                'usage_limit' => 100,
                'max_usage_per_user' => 3,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addYear(),
                'is_active' => true,
                'is_public' => false, // Mã riêng tư
                'property_id' => null,
            ],

            // Voucher giảm số tiền cố định
            [
                'code' => 'GIAM50K',
                'name' => 'Giảm 50.000đ',
                'description' => 'Giảm ngay 50.000đ cho đơn từ 300.000đ',
                'discount_type' => 'fixed_amount',
                'discount_value' => 50000,
                'min_order_amount' => 300000,
                'max_discount_amount' => null,
                'usage_limit' => null, // Không giới hạn
                'max_usage_per_user' => 5,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonths(12),
                'is_active' => true,
                'is_public' => true,
                'property_id' => null,
            ],
            [
                'code' => 'GIAM100K',
                'name' => 'Giảm 100.000đ',
                'description' => 'Giảm 100.000đ cho đơn từ 800.000đ. Số lượng có hạn!',
                'discount_type' => 'fixed_amount',
                'discount_value' => 100000,
                'min_order_amount' => 800000,
                'max_discount_amount' => null,
                'usage_limit' => 200,
                'max_usage_per_user' => 2,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonths(2),
                'is_active' => true,
                'is_public' => true,
                'property_id' => null,
            ],
            [
                'code' => 'GIAM200K',
                'name' => 'Siêu giảm giá 200K',
                'description' => 'Giảm 200.000đ cho đơn từ 1.500.000đ',
                'discount_type' => 'fixed_amount',
                'discount_value' => 200000,
                'min_order_amount' => 1500000,
                'max_discount_amount' => null,
                'usage_limit' => 100,
                'max_usage_per_user' => 1,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonths(1),
                'is_active' => true,
                'is_public' => true,
                'property_id' => null,
            ],

            // Voucher đặc biệt cho property
            [
                'code' => 'PROPERTY15',
                'name' => 'Ưu đãi riêng cơ sở',
                'description' => 'Giảm 15% khi đặt phòng tại cơ sở được chỉ định',
                'discount_type' => 'percentage',
                'discount_value' => 15,
                'min_order_amount' => 500000,
                'max_discount_amount' => 300000,
                'usage_limit' => 50,
                'max_usage_per_user' => 2,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonths(3),
                'is_active' => true,
                'is_public' => true,
                'property_id' => $propertyId,
            ],

            // Voucher sắp hết hạn
            [
                'code' => 'FLASH5',
                'name' => 'Flash Sale 5%',
                'description' => 'Giảm 5% - Chỉ còn vài ngày!',
                'discount_type' => 'percentage',
                'discount_value' => 5,
                'min_order_amount' => 0,
                'max_discount_amount' => 100000,
                'usage_limit' => 1000,
                'max_usage_per_user' => 10,
                'start_date' => Carbon::now()->subDays(5),
                'end_date' => Carbon::now()->addDays(2),
                'is_active' => true,
                'is_public' => true,
                'property_id' => null,
            ],

            // Voucher đã hết hạn (để test)
            [
                'code' => 'EXPIRED2024',
                'name' => 'Khuyến mãi cũ',
                'description' => 'Voucher đã hết hạn',
                'discount_type' => 'percentage',
                'discount_value' => 25,
                'min_order_amount' => 500000,
                'max_discount_amount' => 400000,
                'usage_limit' => 100,
                'max_usage_per_user' => 1,
                'start_date' => Carbon::now()->subMonths(6),
                'end_date' => Carbon::now()->subDays(30),
                'is_active' => true,
                'is_public' => true,
                'property_id' => null,
            ],

            // Voucher chưa bắt đầu
            [
                'code' => 'NEWYEAR2026',
                'name' => 'Happy New Year 2026',
                'description' => 'Ưu đãi đón năm mới 2026',
                'discount_type' => 'percentage',
                'discount_value' => 25,
                'min_order_amount' => 1000000,
                'max_discount_amount' => 500000,
                'usage_limit' => 1000,
                'max_usage_per_user' => 1,
                'start_date' => Carbon::create(2025, 12, 25),
                'end_date' => Carbon::create(2026, 1, 15),
                'is_active' => true,
                'is_public' => true,
                'property_id' => null,
            ],

            // Voucher bị vô hiệu hóa
            [
                'code' => 'DISABLED10',
                'name' => 'Voucher tạm ngưng',
                'description' => 'Voucher đang bị vô hiệu hóa',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'min_order_amount' => 200000,
                'max_discount_amount' => 100000,
                'usage_limit' => 500,
                'max_usage_per_user' => 3,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonths(6),
                'is_active' => false, // Vô hiệu hóa
                'is_public' => true,
                'property_id' => null,
            ],
        ];

        foreach ($vouchers as $voucherData) {
            Voucher::updateOrCreate(
                ['code' => $voucherData['code']],
                $voucherData
            );
        }

        $this->command->info('Created ' . count($vouchers) . ' vouchers!');
    }
}
