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
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting database seeding...');
        $this->command->newLine();

        // Bước 1: Roles & Users
        $this->command->info('📋 Step 1: Creating roles and users...');
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            StaffSeeder::class,
        ]);
        $this->command->info('✅ Step 1 completed');
        $this->command->newLine();

        // Bước 2: Properties
        $this->command->info('🏢 Step 2: Creating property...');
        $this->call([
            PropertySeeder::class,
        ]);
        $this->command->info('✅ Step 2 completed');
        $this->command->newLine();

        // Bước 3: RoomTypes & Rooms
        $this->command->info('🛏️  Step 3: Creating room types and rooms...');
        $this->call([
            RoomTypeSeeder::class,
            RoomTypeImageSeeder::class,
            RoomSeeder::class,
        ]);
        $this->command->info('✅ Step 3 completed');
        $this->command->newLine();

        // Bước 4: Amenities
        $this->command->info('✨ Step 4: Creating amenities and assigning to room types...');
        $this->call([
            AmenitySeeder::class,
            RoomTypeAmenitySeeder::class, // Gán amenities cho room types thay vì rooms
        ]);
        $this->command->info('✅ Step 4 completed');
        $this->command->newLine();

        // Bước 5: Services, Promotions, Vouchers
        $this->command->info('🎁 Step 5: Creating services, promotions, and vouchers...');
        $this->call([
            ServiceSeeder::class,
            PromotionSeeder::class,
            VoucherSeeder::class,
        ]);
        $this->command->info('✅ Step 5 completed');
        $this->command->newLine();

        // Bước 6: Supplies
        $this->command->info('📦 Step 6: Creating supplies...');
        $this->call([
            SupplySeeder::class,
        ]);
        $this->command->info('✅ Step 6 completed');
        $this->command->newLine();

        $this->command->info('🎉 Database seeding completed successfully!');
        $this->command->newLine();
        $this->command->info('📊 Summary:');
        $this->command->info('   - Roles: admin, owner, staff, user');
        $this->command->info('   - Users: admin, owner, staff, and test users');
        $this->command->info('   - Properties: 1 (Sunrise Beach Resort & Spa)');
        $this->command->info('   - Room Types: 10 loại (1-10 người/phòng)');
        $this->command->info('   - Rooms: 78 phòng');
        $this->command->info('   - Amenities: 32 tiện ích');
        $this->command->newLine();
        $this->command->info('💡 Room capacity for smart allocation:');
        $this->command->info('   - 1 người: 10 phòng  (Single)');
        $this->command->info('   - 2 người: 35 phòng  (Standard/Superior/Deluxe)');
        $this->command->info('   - 3 người: 8 phòng   (Triple)');
        $this->command->info('   - 4 người: 8 phòng   (Family Deluxe)');
        $this->command->info('   - 5 người: 5 phòng   (Family Suite)');
        $this->command->info('   - 6 người: 6 phòng   (Group Room)');
        $this->command->info('   - 8 người: 4 villa   (Garden View)');
        $this->command->info('   - 10 người: 2 villa  (Beach Front)');
        $this->command->info('   => Tổng sức chứa: 200+ khách');
    }
}
