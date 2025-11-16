<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Property;
use App\Models\RoomType;

class RoomTypeSeeder extends Seeder
{
    public function run(): void
    {
        $properties = Property::all();
        
        if ($properties->isEmpty()) {
            $this->command->warn('⚠️  No properties found. Skipping room types creation.');
            return;
        }

        $roomTypes = [
            ['name' => 'Phòng Standard', 'description' => 'Phòng tiêu chuẩn với đầy đủ tiện nghi cơ bản'],
            ['name' => 'Phòng Deluxe', 'description' => 'Phòng cao cấp với không gian rộng rãi và tiện nghi hiện đại'],
            ['name' => 'Phòng Suite', 'description' => 'Phòng suite sang trọng với phòng khách riêng'],
            ['name' => 'Phòng Family', 'description' => 'Phòng gia đình phù hợp cho 4-6 người'],
            ['name' => 'Villa', 'description' => 'Villa riêng biệt với sân vườn và hồ bơi'],
            ['name' => 'Studio', 'description' => 'Phòng studio với bếp và không gian sống tích hợp'],
        ];

        foreach ($properties as $property) {
            foreach ($roomTypes as $index => $roomTypeData) {
                RoomType::firstOrCreate(
                    [
                        'property_id' => $property->id,
                        'name' => $roomTypeData['name'],
                    ],
                    [
                        'description' => $roomTypeData['description'],
                        'status' => 'active',
                    ]
                );
            }
        }

        $this->command->info('✅ Created room types for all properties');
    }
}

