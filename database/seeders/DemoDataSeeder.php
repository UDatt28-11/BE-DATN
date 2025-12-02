<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Property;
use App\Models\RoomType;
use App\Models\Room;
use App\Models\Amenity;
use App\Models\User;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Tìm user admin hoặc user đầu tiên làm owner
        $admin = User::where('role', 'admin')->first() ?? User::first();

        if (!$admin) {
            throw new \Exception('Không tìm thấy user admin hoặc user nào để làm owner property');
        }

        // Tạo property
        $property = Property::firstOrCreate([
            'name' => 'Homestay Demo',
            'address' => '123 Đường ABC, Quận XYZ, TP.HCM'
        ], [
            'description' => 'Homestay đẹp với không gian yên tĩnh',
            'owner_id' => $admin->id,
            'verification_status' => 'verified',
            'status' => 'active',
        ]);

        // Tạo amenities
        $wifi = Amenity::firstOrCreate(['name' => 'WiFi'], [
            'property_id' => $property->id,
        ]);

        $parking = Amenity::firstOrCreate(['name' => 'Bãi đỗ xe'], [
            'property_id' => $property->id,
        ]);

        $pool = Amenity::firstOrCreate(['name' => 'Hồ bơi'], [
            'property_id' => $property->id,
        ]);

        // Tạo room types
        $singleRoom = RoomType::firstOrCreate(['name' => 'Phòng Đơn'], [
            'property_id' => $property->id,
            'description' => 'Phòng đơn thoải mái cho 1-2 người',
            'status' => 'active',
        ]);

        $doubleRoom = RoomType::firstOrCreate(['name' => 'Phòng Đôi'], [
            'property_id' => $property->id,
            'description' => 'Phòng đôi rộng rãi cho gia đình',
            'status' => 'active',
        ]);

        // Gán amenities cho room types (skip vì model không có relationship)

        // Tạo rooms
        Room::firstOrCreate([
            'property_id' => $property->id,
            'name' => 'Phòng 101',
        ], [
            'room_type_id' => $singleRoom->id,
            'status' => 'available',
            'price_per_night' => 300000,
            'description' => 'Phòng đơn tầng 1, view sân vườn',
            'verification_status' => 'verified',
        ]);

        Room::firstOrCreate([
            'property_id' => $property->id,
            'name' => 'Phòng 201',
        ], [
            'room_type_id' => $singleRoom->id,
            'status' => 'available',
            'price_per_night' => 320000,
            'description' => 'Phòng đơn tầng 2, view thành phố',
            'verification_status' => 'verified',
        ]);

        Room::firstOrCreate([
            'property_id' => $property->id,
            'name' => 'Phòng 102',
        ], [
            'room_type_id' => $doubleRoom->id,
            'status' => 'available',
            'price_per_night' => 500000,
            'description' => 'Phòng đôi tầng 1, có ban công',
            'verification_status' => 'verified',
        ]);

        $this->command->info('Demo data seeded successfully!');
        $this->command->info('Room Types: ' . RoomType::count());
        $this->command->info('Rooms: ' . Room::count());
        $this->command->info('Amenities: ' . Amenity::count());
    }
}
