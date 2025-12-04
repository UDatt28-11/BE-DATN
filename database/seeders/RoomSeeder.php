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
        Room::where('property_id', $property->id)->delete();

        // Định nghĩa giá và thông tin cho từng loại phòng
        $roomTypeConfig = [
            'Phòng Standard' => [
                'price' => 500000,
                'max_adults' => 2,
                'max_children' => 1,
                'count' => 3,
            ],
            'Phòng Deluxe' => [
                'price' => 800000,
                'max_adults' => 2,
                'max_children' => 2,
                'count' => 2,
            ],
            'Phòng Family' => [
                'price' => 1200000,
                'max_adults' => 4,
                'max_children' => 2,
                'count' => 2,
            ],
            'Studio' => [
                'price' => 600000,
                'max_adults' => 2,
                'max_children' => 1,
                'count' => 2,
            ],
        ];

        foreach ($roomTypes as $roomType) {
            $config = $roomTypeConfig[$roomType->name] ?? [
                'price' => 500000,
                'max_adults' => 2,
                'max_children' => 1,
                'count' => 2,
            ];
            
            // Đảm bảo tất cả phòng cùng loại có thông tin giống nhau
            // Description chung cho tất cả phòng cùng loại (không thêm số phòng)
            $commonDescription = $roomType->description;
            
            // Phân bổ phòng vào các tầng khác nhau
            // Tầng 0 (tầng trệt): 30% phòng
            // Tầng 1-3 (tầng cao): 60% phòng
            // Tầng 4+ (gác mái): 10% phòng
            $groundFloorCount = max(1, (int) ceil($config['count'] * 0.3));
            $upperFloorCount = max(1, (int) ceil($config['count'] * 0.6));
            $atticCount = $config['count'] - $groundFloorCount - $upperFloorCount;
            
            $roomIndex = 1;
            
            // Tạo phòng tầng trệt
            for ($i = 0; $i < $groundFloorCount && $roomIndex <= $config['count']; $i++, $roomIndex++) {
                Room::create([
                    'property_id' => $property->id,
                    'room_type_id' => $roomType->id,
                    'name' => $roomType->name . ' ' . $roomIndex,
                    'description' => $commonDescription, // Cùng description cho tất cả phòng cùng loại
                    'floor_number' => 0,
                    'floor_category' => 'ground_floor',
                    'max_adults' => $config['max_adults'], // Cùng max_adults
                    'max_children' => $config['max_children'], // Cùng max_children
                    'price_per_night' => $config['price'], // Cùng giá
                    'status' => 'available',
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                ]);
            }
            
            // Tạo phòng tầng cao (1-3)
            $currentFloor = 1;
            for ($i = 0; $i < $upperFloorCount && $roomIndex <= $config['count']; $i++, $roomIndex++) {
                Room::create([
                    'property_id' => $property->id,
                    'room_type_id' => $roomType->id,
                    'name' => $roomType->name . ' ' . $roomIndex,
                    'description' => $commonDescription, // Cùng description cho tất cả phòng cùng loại
                    'floor_number' => $currentFloor,
                    'floor_category' => 'upper_floor',
                    'max_adults' => $config['max_adults'], // Cùng max_adults
                    'max_children' => $config['max_children'], // Cùng max_children
                    'price_per_night' => $config['price'], // Cùng giá
                    'status' => 'available',
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                ]);
                
                // Luân phiên giữa tầng 1, 2, 3
                $currentFloor = ($currentFloor % 3) + 1;
            }
            
            // Tạo phòng gác mái (tầng 4+)
            for ($i = 0; $i < $atticCount && $roomIndex <= $config['count']; $i++, $roomIndex++) {
                Room::create([
                    'property_id' => $property->id,
                    'room_type_id' => $roomType->id,
                    'name' => $roomType->name . ' ' . $roomIndex,
                    'description' => $commonDescription, // Cùng description cho tất cả phòng cùng loại
                    'floor_number' => 4,
                    'floor_category' => 'attic',
                    'max_adults' => $config['max_adults'], // Cùng max_adults
                    'max_children' => $config['max_children'], // Cùng max_children
                    'price_per_night' => $config['price'], // Cùng giá
                    'status' => 'available',
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                ]);
            }
        }

        $this->command->info('✅ Created rooms for property');
    }
}
