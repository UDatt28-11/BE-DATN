<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoomTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roomTypes = [
            [
                'property_id' => 1,
                'name' => 'Deluxe',
                'description' => 'Phòng cao cấp với đầy đủ tiện nghi',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'property_id' => 1,
                'name' => 'Standard',
                'description' => 'Phòng tiêu chuẩn thoải mái',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'property_id' => 1,
                'name' => 'Suite',
                'description' => 'Phòng suite sang trọng',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'property_id' => 1,
                'name' => 'VIP',
                'description' => 'Phòng VIP dành cho khách hàng đặc biệt',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('room_types')->insert($roomTypes);
    }
}
