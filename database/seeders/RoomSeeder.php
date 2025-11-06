<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Property;
use App\Models\RoomType;
use App\Models\Room;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        // Lấy 1 Property bất kỳ để gắn phòng
        $property = Property::first();
        if (!$property) {
            $this->command?->info('❌ No property found, run PropertySeeder first.');
            return;
        }

        // Tạo RoomType (phải có property_id)
        $roomType = RoomType::first();
        if (!$roomType) {
            $roomType = RoomType::create([
                'property_id' => $property->id, // 👈 thêm dòng này
                'name' => 'Deluxe',
                'description' => 'Deluxe room type for testing',
            ]);
        }

        // Tạo 1 room mẫu nếu chưa có
        $room = Room::first();
        if (!$room) {
            Room::create([
                'property_id' => $property->id,
                'room_type_id' => $roomType->id,
                'name' => 'Sample Room',
                'description' => 'A sample room for upload testing',
                'max_adults' => 2,
                'max_children' => 1,
                'price_per_night' => 500000,
                'status' => 'available',
            ]);
        }
    }
}
