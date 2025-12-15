<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Property;
use App\Models\RoomType;

class RoomTypeSeeder extends Seeder
{
    public function run(): void
    {
        $property = Property::first();
        
        if (!$property) {
            $this->command->warn('⚠️  No property found. Skipping room types creation.');
            return;
        }

        // Xóa room types cũ
        RoomType::query()->delete();

        // Tạo đầy đủ các loại phòng với giá, sức chứa
        $roomTypes = [
            [
                'name' => 'Phòng Single',
                'description' => 'Phòng đơn tiêu chuẩn dành cho 1 người, đầy đủ tiện nghi. Giường đơn 1m2, TV 32 inch, minibar, phòng tắm riêng với vòi sen.',
                'base_price' => 500000,
                'max_adults' => 1,
                'max_children' => 0,
            ],
            [
                'name' => 'Phòng Standard Double',
                'description' => 'Phòng tiêu chuẩn với giường đôi King size, phù hợp cho 2 người. Có ban công nhỏ, TV 43 inch, minibar, két sắt.',
                'base_price' => 800000,
                'max_adults' => 2,
                'max_children' => 1,
            ],
            [
                'name' => 'Phòng Superior Twin',
                'description' => 'Phòng cao cấp với 2 giường đơn, lý tưởng cho bạn bè hoặc đồng nghiệp đi công tác. View hồ bơi hoặc vườn.',
                'base_price' => 900000,
                'max_adults' => 2,
                'max_children' => 1,
            ],
            [
                'name' => 'Phòng Deluxe Ocean View',
                'description' => 'Phòng cao cấp view biển trực diện, ban công rộng. Giường King, bồn tắm, tiện nghi 5 sao. Bao gồm bữa sáng.',
                'base_price' => 1500000,
                'max_adults' => 2,
                'max_children' => 2,
            ],
            [
                'name' => 'Phòng Triple',
                'description' => 'Phòng 3 người với 3 giường đơn hoặc 1 giường đôi + 1 giường đơn. Không gian rộng 35m², phù hợp nhóm nhỏ.',
                'base_price' => 1200000,
                'max_adults' => 3,
                'max_children' => 1,
            ],
            [
                'name' => 'Phòng Family Deluxe',
                'description' => 'Phòng gia đình rộng 45m² với 1 giường King + 2 giường đơn. Có sofa bed, bàn ăn nhỏ, view biển hoặc vườn.',
                'base_price' => 1800000,
                'max_adults' => 4,
                'max_children' => 2,
            ],
            [
                'name' => 'Phòng Family Suite',
                'description' => 'Suite gia đình 2 phòng ngủ liên thông, 65m². Phòng master có giường King, phòng con có 2 giường đơn. Phòng khách riêng.',
                'base_price' => 2500000,
                'max_adults' => 5,
                'max_children' => 2,
            ],
            [
                'name' => 'Phòng Group Room',
                'description' => 'Phòng dành cho nhóm với 3 giường đôi, chứa tối đa 6 người. Rộng 55m², có 2 phòng tắm. Lý tưởng cho team building.',
                'base_price' => 2800000,
                'max_adults' => 6,
                'max_children' => 2,
            ],
            [
                'name' => 'Villa Garden View',
                'description' => 'Villa riêng biệt với sân vườn, 2 phòng ngủ, phòng khách rộng. Bếp mini, bàn nướng BBQ. Chứa 6-8 người.',
                'base_price' => 4500000,
                'max_adults' => 6,
                'max_children' => 2,
            ],
            [
                'name' => 'Villa Beach Front',
                'description' => 'Villa cao cấp mặt biển, 3 phòng ngủ, hồ bơi riêng. Bếp đầy đủ, phòng khách + phòng ăn. Tối đa 10 người.',
                'base_price' => 8000000,
                'max_adults' => 8,
                'max_children' => 2,
            ],
        ];

        foreach ($roomTypes as $data) {
            RoomType::create([
                'property_id' => $property->id,
                'name' => $data['name'],
                'description' => $data['description'],
                'base_price' => $data['base_price'],
                'max_adults' => $data['max_adults'],
                'max_children' => $data['max_children'],
                'status' => 'active',
            ]);
        }

        $this->command->info('✅ Created ' . count($roomTypes) . ' room types with prices and capacities');
    }
}
