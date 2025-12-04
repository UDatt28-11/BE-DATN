<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * 
     * Thứ tự seed quan trọng:
     * 1. Roles & Users (cần có trước)
     * 2. Properties (cần owner)
     * 3. RoomTypes & Rooms (cần property)
     * 4. Amenities & RoomAmenities (cần rooms)
     * 5. Services, Promotions, Vouchers (cần property)
     * 6. Supplies (cần property)
     * 7. Bookings (cần rooms, users)
     * 8. Invoices & Reviews (cần bookings)
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting database seeding...');
        $this->command->newLine();

        // Bước 1: Roles & Users
        $this->command->info('📋 Step 1: Creating roles and users...');
        $this->call([
            RoleSeeder::class,          // Tạo roles trước
            UserSeeder::class,          // Tạo users (admin, owner, user)
            StaffSeeder::class,         // Tạo staff user
        ]);
        $this->command->info('✅ Step 1 completed');
        $this->command->newLine();

        // Bước 2: Properties
        $this->command->info('🏢 Step 2: Creating properties...');
        $this->call([
            PropertySeeder::class,      // Tạo properties (cần owner)
        ]);
        $this->command->info('✅ Step 2 completed');
        $this->command->newLine();

        // Bước 3: RoomTypes & Rooms
        $this->command->info('🛏️  Step 3: Creating room types and rooms...');
        $this->call([
            RoomTypeSeeder::class,      // Tạo room types (cần property)
            RoomTypeImageSeeder::class, // Tạo ảnh cho room types
            RoomSeeder::class,          // Tạo rooms (cần room types)
        ]);
        $this->command->info('✅ Step 3 completed');
        $this->command->newLine();

        // Bước 4: Amenities
        $this->command->info('✨ Step 4: Creating amenities and assigning to rooms...');
        $this->call([
            AmenitySeeder::class,       // Tạo amenities (cần property)
            RoomAmenitySeeder::class,   // Gán amenities cho rooms (cần rooms & amenities)
        ]);
        $this->command->info('✅ Step 4 completed');
        $this->command->newLine();

        // Bước 5: Services, Promotions, Vouchers
        $this->command->info('🎁 Step 5: Creating services, promotions, and vouchers...');
        $this->call([
            ServiceSeeder::class,       // Tạo services (cần property)
            PromotionSeeder::class,     // Tạo promotions (cần property)
            VoucherSeeder::class,       // Tạo vouchers (cần property)
        ]);
        $this->command->info('✅ Step 5 completed');
        $this->command->newLine();

        // Bước 6: Supplies
        $this->command->info('📦 Step 6: Creating supplies...');
        $this->call([
            SupplySeeder::class,        // Tạo supplies (cần property)
        ]);
        $this->command->info('✅ Step 6 completed');
        $this->command->newLine();

        // Bước 7: Bookings (optional - có thể bỏ qua nếu muốn dữ liệu sạch)
        $this->command->info('📅 Step 7: Creating sample bookings...');
        $this->call([
            BookingSeeder::class,       // Tạo booking orders (cần rooms, users)
        ]);
        $this->command->info('✅ Step 7 completed');
        $this->command->newLine();

        // Bước 8: Invoices & Reviews (optional)
        $this->command->info('📄 Step 8: Creating invoices and reviews...');
        $this->call([
            InvoiceSeeder::class,       // Tạo invoices (cần booking orders)
            ReviewSeeder::class,        // Tạo reviews (cần booking details)
        ]);
        $this->command->info('✅ Step 8 completed');
        $this->command->newLine();

        $this->command->info('🎉 Database seeding completed successfully!');
        $this->command->newLine();
        $this->command->info('📊 Summary:');
        $this->command->info('   - Roles: admin, owner, staff, user');
        $this->command->info('   - Users: admin, owner, staff, and test users');
        $this->command->info('   - Properties: 1 property');
        $this->command->info('   - Room Types: 4 types');
        $this->command->info('   - Rooms: Multiple rooms per type');
        $this->command->info('   - Amenities: Full set with filter categories');
        $this->command->info('   - Services, Promotions, Vouchers: Sample data');
        $this->command->info('   - Supplies: Sample inventory');
        $this->command->info('   - Bookings: Sample bookings for testing');
    }
}
