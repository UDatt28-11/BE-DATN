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
        // Cấu hình phòng - Tổng khoảng 80 phòng
        // Đủ cho nhóm 40+ người test tính năng chia phòng
        // ========================================
        $roomConfig = [
            'Phòng Single' => [
                'price' => 350000,
                'max_adults' => 1,
                'max_children' => 0,
                'count' => 10,
            ],
            'Phòng Standard Double' => [
                'price' => 550000,
                'max_adults' => 2,
                'max_children' => 1,
                'count' => 15,
            ],
            'Phòng Superior Twin' => [
                'price' => 650000,
                'max_adults' => 2,
                'max_children' => 0,
                'count' => 12,
            ],
            'Phòng Deluxe Ocean View' => [
                'price' => 1200000,
                'max_adults' => 2,
                'max_children' => 2,
                'count' => 8,
            ],
            'Phòng Triple' => [
                'price' => 850000,
                'max_adults' => 3,
                'max_children' => 1,
                'count' => 8,
            ],
            'Phòng Family Deluxe' => [
                'price' => 1500000,
                'max_adults' => 4,
                'max_children' => 2,
                'count' => 8,
            ],
            'Phòng Family Suite' => [
                'price' => 2200000,
                'max_adults' => 5,
                'max_children' => 2,
                'count' => 5,
            ],
            'Phòng Group Room' => [
                'price' => 1800000,
                'max_adults' => 6,
                'max_children' => 0,
                'count' => 6,
            ],
            'Villa Garden View' => [
                'price' => 4500000,
                'max_adults' => 8,
                'max_children' => 2,
                'count' => 4,
            ],
            'Villa Beach Front' => [
                'price' => 8000000,
                'max_adults' => 10,
                'max_children' => 3,
                'count' => 2,
            ],
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
            $config = $roomConfig[$roomType->name] ?? [
                'price' => 500000,
                'max_adults' => 2,
                'max_children' => 1,
                'count' => 3,
            ];

            // Tạo các phòng cho loại phòng này
            for ($i = 1; $i <= $config['count']; $i++) {
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
                    'max_adults' => $config['max_adults'],
                    'max_children' => $config['max_children'],
                    'price_per_night' => $config['price'],
                    'status' => 'available',
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                ]);

                $totalCreated++;
            }
        }

        $this->command->info("✅ Created {$totalCreated} rooms");
        $this->command->info("   Room distribution by capacity:");
        $this->command->info("   - 1 người: 10 phòng (Single)");
        $this->command->info("   - 2 người: 35 phòng (Standard + Superior + Deluxe)");
        $this->command->info("   - 3 người: 8 phòng (Triple)");
        $this->command->info("   - 4 người: 8 phòng (Family Deluxe)");
        $this->command->info("   - 5 người: 5 phòng (Family Suite)");
        $this->command->info("   - 6 người: 6 phòng (Group Room)");
        $this->command->info("   - 8 người: 4 villa (Garden View)");
        $this->command->info("   - 10 người: 2 villa (Beach Front)");
    }
}
