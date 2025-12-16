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
        $property = Property::first();
        
        if (!$property) {
            $this->command->warn('⚠️  No property found. Skipping rooms creation.');
            return;
        }

        $roomTypes = RoomType::where('property_id', $property->id)->get();

        if ($roomTypes->isEmpty()) {
            $this->command->warn('⚠️  No room types found. Skipping rooms creation.');
            return;
        }

        // Xóa rooms cũ
        Room::query()->delete();

        // ========================================
        // Số lượng phòng cho mỗi loại
        // Giá và sức chứa lấy từ room_types
        // ========================================
        $roomCounts = [
            'Phòng Single' => 10,
            'Phòng Standard Double' => 15,
            'Phòng Superior Twin' => 12,
            'Phòng Deluxe Ocean View' => 8,
            'Phòng Triple' => 8,
            'Phòng Family Deluxe' => 8,
            'Phòng Family Suite' => 5,
            'Phòng Group Room' => 6,
            'Villa Garden View' => 4,
            'Villa Beach Front' => 2,
        ];

        // Định nghĩa tầng và phân loại theo enum (ground_floor, upper_floor, attic)
        $floorCategories = [
            ['floor' => 0, 'category' => 'ground_floor'],  // Tầng trệt
            ['floor' => 1, 'category' => 'upper_floor'],   // Tầng 1
            ['floor' => 2, 'category' => 'upper_floor'],   // Tầng 2
            ['floor' => 3, 'category' => 'upper_floor'],   // Tầng 3
            ['floor' => 4, 'category' => 'attic'],         // Gác mái
        ];

        $totalCreated = 0;

        foreach ($roomTypes as $roomType) {
            $count = $roomCounts[$roomType->name] ?? 3;

            // Tạo các phòng cho loại phòng này
            // Giá và sức chứa lấy từ room_type
            for ($i = 1; $i <= $count; $i++) {
                // Phân bổ tầng theo vòng lặp
                $floorIndex = ($i - 1) % count($floorCategories);
                $floorInfo = $floorCategories[$floorIndex];

                // Tạo tên phòng dễ hiểu (VD: P101, P102, P201...)
                $roomNumber = ($floorInfo['floor'] * 100) + $i;
                $roomName = $roomType->name . ' - P' . str_pad($roomNumber, 3, '0', STR_PAD_LEFT);

                Room::create([
                    'property_id' => $property->id,
                    'room_type_id' => $roomType->id,
                    'name' => $roomName,
                    'description' => $roomType->description,
                    'floor_number' => $floorInfo['floor'],
                    'floor_category' => $floorInfo['category'],
                    // Lấy giá và sức chứa từ room_type
                    'max_adults' => $roomType->max_adults,
                    'max_children' => $roomType->max_children,
                    'price_per_night' => $roomType->base_price,
                    'status' => 'available',
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                ]);

                $totalCreated++;
            }
        }

        $this->command->info("✅ Created {$totalCreated} rooms (prices and capacities from room_types)");
    }
}
