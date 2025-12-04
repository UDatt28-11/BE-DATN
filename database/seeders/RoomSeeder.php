<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            // Homestay Sapa View - Phòng Standard
            [
                'property_id' => 1,
                'room_type_id' => 1,
                'name' => 'Sapa Standard 101',
                'description' => 'Phòng tiêu chuẩn tầng 1, view núi đẹp',
                'max_adults' => 2,
                'max_children' => 1,
                'price_per_night' => 500000.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'property_id' => 1,
                'room_type_id' => 1,
                'name' => 'Sapa Standard 102',
                'description' => 'Phòng tiêu chuẩn tầng 1, gần lối ra vào',
                'max_adults' => 2,
                'max_children' => 1,
                'price_per_night' => 500000.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Homestay Sapa View - Phòng Deluxe
            [
                'property_id' => 1,
                'room_type_id' => 2,
                'name' => 'Sapa Deluxe 201',
                'description' => 'Phòng deluxe tầng 2, ban công riêng view núi',
                'max_adults' => 3,
                'max_children' => 2,
                'price_per_night' => 800000.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Mountain Lodge Đà Lạt - Phòng Family
            [
                'property_id' => 2,
                'room_type_id' => 3,
                'name' => 'Dalat Family 301',
                'description' => 'Phòng gia đình tầng 3, view hồ Tuyền Lâm',
                'max_adults' => 4,
                'max_children' => 2,
                'price_per_night' => 1200000.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'property_id' => 2,
                'room_type_id' => 3,
                'name' => 'Dalat Family 302',
                'description' => 'Phòng gia đình tầng 3, không gian rộng rãi',
                'max_adults' => 4,
                'max_children' => 2,
                'price_per_night' => 1200000.00,
                'status' => 'maintenance',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Mountain Lodge Đà Lạt - Phòng Suite
            [
                'property_id' => 2,
                'room_type_id' => 4,
                'name' => 'Dalat Suite 401',
                'description' => 'Phòng suite tầng 4, view toàn cảnh hồ',
                'max_adults' => 2,
                'max_children' => 1,
                'price_per_night' => 2000000.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Beach House Phú Quốc - Phòng Ocean View
            [
                'property_id' => 3,
                'room_type_id' => 5,
                'name' => 'PhuQuoc Ocean 101',
                'description' => 'Phòng view biển tầng 1, ban công riêng',
                'max_adults' => 2,
                'max_children' => 1,
                'price_per_night' => 1500000.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'property_id' => 3,
                'room_type_id' => 5,
                'name' => 'PhuQuoc Ocean 102',
                'description' => 'Phòng view biển tầng 1, gần bãi biển',
                'max_adults' => 2,
                'max_children' => 1,
                'price_per_night' => 1500000.00,
                'status' => 'occupied',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Beach House Phú Quốc - Phòng Garden View
            [
                'property_id' => 3,
                'room_type_id' => 6,
                'name' => 'PhuQuoc Garden 201',
                'description' => 'Phòng view vườn tầng 2, yên tĩnh',
                'max_adults' => 2,
                'max_children' => 1,
                'price_per_night' => 1000000.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        DB::table('rooms')->insert($rooms);
    }
}
