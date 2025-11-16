<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,          // Chạy trước
            UserSeeder::class,          // Tạo users
            StaffSeeder::class,         // Tạo staff user
            PropertySeeder::class,      // Tạo properties
            RoomTypeSeeder::class,      // Tạo room types
            RoomSeeder::class,          // Tạo rooms (cập nhật để tạo nhiều hơn)
            AmenitySeeder::class,       // Tạo amenities
            RoomAmenitySeeder::class,   // Gán amenities cho rooms
            PromotionSeeder::class,     // Tạo promotions
            ServiceSeeder::class,       // Tạo services
            VoucherSeeder::class,       // Tạo vouchers
            SupplySeeder::class,        // Tạo supplies
            BookingSeeder::class,       // Tạo booking orders để test (phải chạy trước ReviewSeeder và InvoiceSeeder)
            InvoiceSeeder::class,       // Tạo invoices (cần booking orders)
            ReviewSeeder::class,        // Tạo reviews (cần booking details)
        ]);
    }
}
