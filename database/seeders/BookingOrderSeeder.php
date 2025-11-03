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
                'total_amount' => 1500000.00, // 2 đêm x 500,000
                'status' => 'confirmed',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ],
            [
                'guest_id' => $guest2->id,
                'order_code' => 'BK' . strtoupper(Str::random(8)),
                'total_amount' => 2400000.00, // 2 đêm x 800,000
                'status' => 'confirmed',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ],
            [
                'guest_id' => $guest3->id,
                'order_code' => 'BK' . strtoupper(Str::random(8)),
                'total_amount' => 3600000.00, // 3 đêm x 1,200,000
                'status' => 'pending',
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ],
            [
                'guest_id' => $guest1->id,
                'order_code' => 'BK' . strtoupper(Str::random(8)),
                'total_amount' => 3000000.00, // 1 đêm x 2,000,000
                'status' => 'completed',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(8),
            ],
            [
                'guest_id' => $guest2->id,
                'order_code' => 'BK' . strtoupper(Str::random(8)),
                'total_amount' => 4500000.00, // 3 đêm x 1,500,000
                'status' => 'cancelled',
                'created_at' => now()->subDays(7),
                'updated_at' => now()->subDays(6),
            ]
        ];

        DB::table('booking_orders')->insert($bookingOrders);
    }
}
