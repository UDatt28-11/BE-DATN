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

        // Xóa room types cũ của property này
        RoomType::where('property_id', $property->id)->delete();

        // Tạo các loại phòng thực tế và sạch sẽ
        $roomTypes = [
            [
                'name' => 'Phòng Standard',
                'description' => 'Phòng tiêu chuẩn với đầy đủ tiện nghi cơ bản, phù hợp cho 2 người. Có giường đôi, TV, tủ lạnh mini, phòng tắm riêng.',
            ],
            [
                'name' => 'Phòng Deluxe',
                'description' => 'Phòng cao cấp với không gian rộng rãi hơn, view đẹp. Có ban công, bàn làm việc, tiện nghi hiện đại.',
            ],
            [
                'name' => 'Phòng Family',
                'description' => 'Phòng gia đình rộng rãi, phù hợp cho 4-6 người. Có 2 giường, sofa, không gian sinh hoạt chung.',
            ],
            [
                'name' => 'Studio',
                'description' => 'Phòng studio với bếp mini đầy đủ, không gian sống tích hợp. Phù hợp cho khách ở dài ngày.',
            ],
        ];

        foreach ($roomTypes as $roomTypeData) {
            RoomType::create([
                'property_id' => $property->id,
                'name' => $roomTypeData['name'],
                'description' => $roomTypeData['description'],
                'status' => 'active',
            ]);
        }

        $this->command->info('✅ Created ' . count($roomTypes) . ' room types for property');
    }
}

