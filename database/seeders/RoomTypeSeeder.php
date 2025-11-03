<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoomTypeSeeder extends Seeder
{
    public function run(): void
    {
        $property1 = DB::table('properties')->where('name', 'Homestay Sapa View')->first();
        $property2 = DB::table('properties')->where('name', 'Mountain Lodge Đà Lạt')->first();
        $property3 = DB::table('properties')->where('name', 'Beach House Phú Quốc')->first();

        $roomTypes = [
            // Homestay Sapa View
            [
                'property_id' => $property1->id,
                'name' => 'Phòng Standard',
                'description' => 'Phòng tiêu chuẩn với giường đôi, phù hợp cho 2 người',
                'image_url' => 'https://example.com/standard-room.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'property_id' => $property1->id,
                'name' => 'Phòng Deluxe',
                'description' => 'Phòng cao cấp với view núi, ban công riêng',
                'image_url' => 'https://example.com/deluxe-room.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Mountain Lodge Đà Lạt
            [
                'property_id' => $property2->id,
                'name' => 'Phòng Family',
                'description' => 'Phòng gia đình với 2 giường, phù hợp cho 4 người',
                'image_url' => 'https://example.com/family-room.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'property_id' => $property2->id,
                'name' => 'Phòng Suite',
                'description' => 'Phòng suite cao cấp với view hồ, không gian rộng rãi',
                'image_url' => 'https://example.com/suite-room.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Beach House Phú Quốc
            [
                'property_id' => $property3->id,
                'name' => 'Phòng Ocean View',
                'description' => 'Phòng view biển với ban công riêng',
                'image_url' => 'https://example.com/ocean-view-room.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'property_id' => $property3->id,
                'name' => 'Phòng Garden View',
                'description' => 'Phòng view vườn, yên tĩnh và mát mẻ',
                'image_url' => 'https://example.com/garden-view-room.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        DB::table('room_types')->insert($roomTypes);
    }
}
