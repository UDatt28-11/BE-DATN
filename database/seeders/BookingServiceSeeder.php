<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BookingServiceSeeder extends Seeder
{
    public function run(): void
    {
        // Get booking details and supplies
        $bookingDetails = DB::table('booking_details')->get();
        $supplies = DB::table('supplies')->get();

        if ($bookingDetails->count() > 0 && $supplies->count() > 0) {
            foreach ($bookingDetails as $detail) {
                // Add 0-2 services per booking detail
                $serviceCount = rand(0, 2);
                for ($i = 0; $i < $serviceCount; $i++) {
                    $supply = $supplies->random();
                    DB::table('booking_services')->insert([
                        'booking_details_id' => $detail->id,
                        'service_id' => $supply->id,
                        'quantity' => rand(1, 3),
                        'price_at_booking' => $supply->unit_price,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
