<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BookingDetailSeeder extends Seeder
{
    public function run(): void
    {
        // Lấy rooms
        $rooms = DB::table('rooms')->get();
        $bookingOrders = DB::table('booking_orders')->get();

        foreach ($bookingOrders as $order) {
            // Tạo 1-2 booking details per order
            $detailCount = rand(1, 2);
            for ($i = 0; $i < $detailCount; $i++) {
                $room = $rooms->random();
                $nights = rand(1, 5);
                $subtotal = $room->price_per_night * $nights;
                
                DB::table('booking_details')->insert([
                    'booking_order_id' => $order->id,
                    'room_id' => $room->id,
                    'check_in_date' => now()->addDays(rand(1, 10))->toDateString(),
                    'check_out_date' => now()->addDays(rand(11, 20))->toDateString(),
                    'num_adults' => rand(1, 4),
                    'num_children' => rand(0, 2),
                    'sub_total' => $subtotal,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
