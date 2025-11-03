<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            PropertySeeder::class,
            RoomTypeSeeder::class,
            RoomSeeder::class,
            BookingOrderSeeder::class,
            BookingDetailSeeder::class,
            BookingServiceSeeder::class,
            SupplySeeder::class,
            InvoiceSeeder::class,
            InvoiceItemSeeder::class,
            // SupplyLogSeeder::class,  // Comment out for now
        ]);
    }
}
