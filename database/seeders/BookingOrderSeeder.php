<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingOrderSeeder extends Seeder
{
    public function run(): void
    {
        $guest1 = DB::table('users')->where('email', 'guest1@email.com')->first();
        $guest2 = DB::table('users')->where('email', 'guest2@email.com')->first();
        $guest3 = DB::table('users')->where('email', 'guest3@email.com')->first();

        $bookingOrders = [
            [
                'guest_id' => $guest1->id,
                'order_code' => 'BK' . strtoupper(Str::random(8)),
                'customer_name' => 'Nguyễn Văn An',
                'customer_phone' => '0901234567',
                'customer_email' => 'nva@example.com',
                'total_amount' => 3000000.00,
                'payment_method' => 'credit_card',
                'notes' => 'Yêu cầu phòng view biển, tầng cao',
                'status' => 'completed',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(8),
            ],
            [
                'guest_id' => $guest2->id,
                'order_code' => 'BK' . strtoupper(Str::random(8)),
                'customer_name' => 'Trần Thị Bình',
                'customer_phone' => '0912345678',
                'customer_email' => 'ttb@example.com',
                'total_amount' => 5000000.00,
                'payment_method' => 'bank_transfer',
                'notes' => 'Đặt thêm giường phụ cho trẻ em',
                'status' => 'confirmed',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ],
            [
                'guest_id' => $guest3->id,
                'order_code' => 'BK' . strtoupper(Str::random(8)),
                'customer_name' => 'Lê Minh Cường',
                'customer_phone' => '0923456789',
                'customer_email' => 'lmc@gmail.com',
                'total_amount' => 2500000.00,
                'payment_method' => 'cash',
                'notes' => null,
                'status' => 'pending',
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ],
            [
                'guest_id' => $guest1->id,
                'order_code' => 'BK' . strtoupper(Str::random(8)),
                'customer_name' => 'Phạm Thị Dung',
                'customer_phone' => '0934567890',
                'customer_email' => null,
                'total_amount' => 1800000.00,
                'payment_method' => 'cash',
                'notes' => 'Khách yêu cầu hủy do thay đổi lịch',
                'status' => 'cancelled',
                'created_at' => now()->subDays(7),
                'updated_at' => now()->subDays(6),
            ],
        ];

        DB::table('booking_orders')->insert($bookingOrders);
    }
}
