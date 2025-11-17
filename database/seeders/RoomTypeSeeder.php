<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomTypeSeeder extends Seeder
{
    public function run(): void
    {
        $properties = Property::all();

        foreach ($properties as $property) {
            $roomTypes = [
                [
                    'name' => 'Phòng Standard',
                    'description' => 'Phòng tiêu chuẩn với đầy đủ tiện nghi cơ bản, phù hợp cho 2 người.',
                    'image_url' => '/img/rooms/standard.jpg',
                ],
                [
                    'name' => 'Phòng Deluxe',
                    'description' => 'Phòng cao cấp với không gian rộng rãi, view đẹp, tiện nghi hiện đại.',
                    'image_url' => '/img/rooms/deluxe.jpg',
                ],
                [
                    'name' => 'Phòng Suite',
                    'description' => 'Phòng suite sang trọng với phòng khách riêng, view panorama, dịch vụ cao cấp.',
                    'image_url' => '/img/rooms/suite.jpg',
                ],
                [
                    'name' => 'Phòng Family',
                    'description' => 'Phòng gia đình rộng rãi, có thể chứa 4-6 người, phù hợp cho gia đình có trẻ em.',
                    'image_url' => '/img/rooms/family.jpg',
                ],
            ];

            foreach ($roomTypes as $roomTypeData) {
                RoomType::create([
                    'property_id' => $property->id,
                    'name' => $roomTypeData['name'],
                    'description' => $roomTypeData['description'],
                    'image_url' => $roomTypeData['image_url'],
                ]);
            }
        }
    }
}

