<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplyLogSeeder extends Seeder
{
    public function run(): void
    {
        $staff = DB::table('users')->where('email', 'staff@homestay.com')->first();
        $supplies = DB::table('supplies')->get();

        $supplyLogs = [
            // Logs cho khăn tắm phòng Sapa Standard
            [
                'supply_id' => $supplies[0]->id, // Khăn tắm phòng 1
                'user_id' => $staff->id,
                'change_quantity' => 10,
                'reason' => 'Nhập kho ban đầu - Khăn tắm mới',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ],
            [
                'supply_id' => $supplies[0]->id,
                'user_id' => $staff->id,
                'change_quantity' => -2,
                'reason' => 'Khách sử dụng - Phòng 101',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ],

            // Logs cho khăn mặt phòng Sapa Standard
            [
                'supply_id' => $supplies[1]->id, // Khăn mặt phòng 1
                'user_id' => $staff->id,
                'change_quantity' => 15,
                'reason' => 'Nhập kho ban đầu - Khăn mặt mới',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ],
            [
                'supply_id' => $supplies[1]->id,
                'user_id' => $staff->id,
                'change_quantity' => -3,
                'reason' => 'Khách sử dụng - Phòng 101',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ],

            // Logs cho dầu gội phòng Sapa Standard
            [
                'supply_id' => $supplies[2]->id, // Dầu gội phòng 1
                'user_id' => $staff->id,
                'change_quantity' => 8,
                'reason' => 'Nhập kho ban đầu - Dầu gội mới',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ],
            [
                'supply_id' => $supplies[2]->id,
                'user_id' => $staff->id,
                'change_quantity' => -1,
                'reason' => 'Khách sử dụng hết - Phòng 101',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ],

            // Logs cho khăn tắm cao cấp phòng Sapa Deluxe
            [
                'supply_id' => $supplies[5]->id, // Khăn tắm cao cấp phòng 3
                'user_id' => $staff->id,
                'change_quantity' => 12,
                'reason' => 'Nhập kho ban đầu - Khăn tắm cao cấp',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ],
            [
                'supply_id' => $supplies[5]->id,
                'user_id' => $staff->id,
                'change_quantity' => -1,
                'reason' => 'Khách sử dụng - Phòng 201',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],

            // Logs cho áo choàng tắm phòng Sapa Deluxe
            [
                'supply_id' => $supplies[6]->id, // Áo choàng tắm phòng 3
                'user_id' => $staff->id,
                'change_quantity' => 6,
                'reason' => 'Nhập kho ban đầu - Áo choàng tắm',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ],

            // Logs cho khăn tắm gia đình phòng Dalat Family
            [
                'supply_id' => $supplies[8]->id, // Khăn tắm gia đình phòng 4
                'user_id' => $staff->id,
                'change_quantity' => 20,
                'reason' => 'Nhập kho ban đầu - Khăn tắm gia đình',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ],
            [
                'supply_id' => $supplies[8]->id,
                'user_id' => $staff->id,
                'change_quantity' => -4,
                'reason' => 'Khách sử dụng - Phòng 301 (gia đình 4 người)',
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ],

            // Logs cho bộ đồ giường phòng Dalat Family
            [
                'supply_id' => $supplies[9]->id, // Bộ đồ giường phòng 4
                'user_id' => $staff->id,
                'change_quantity' => 8,
                'reason' => 'Nhập kho ban đầu - Bộ đồ giường gia đình',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ],
            [
                'supply_id' => $supplies[9]->id,
                'user_id' => $staff->id,
                'change_quantity' => -1,
                'reason' => 'Thay đổi - Phòng 301',
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ],

            // Logs cho khăn tắm cao cấp phòng Dalat Suite
            [
                'supply_id' => $supplies[11]->id, // Khăn tắm cao cấp phòng 6
                'user_id' => $staff->id,
                'change_quantity' => 8,
                'reason' => 'Nhập kho ban đầu - Khăn tắm cao cấp suite',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ],

            // Logs cho khăn tắm biển phòng PhuQuoc Ocean
            [
                'supply_id' => $supplies[14]->id, // Khăn tắm biển phòng 7
                'user_id' => $staff->id,
                'change_quantity' => 10,
                'reason' => 'Nhập kho ban đầu - Khăn tắm biển',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ],
            [
                'supply_id' => $supplies[14]->id,
                'user_id' => $staff->id,
                'change_quantity' => -2,
                'reason' => 'Khách sử dụng - Phòng 101 (đang occupied)',
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ],

            // Logs cho kem chống nắng phòng PhuQuoc Ocean
            [
                'supply_id' => $supplies[15]->id, // Kem chống nắng phòng 7
                'user_id' => $staff->id,
                'change_quantity' => 5,
                'reason' => 'Nhập kho ban đầu - Kem chống nắng',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ],
            [
                'supply_id' => $supplies[15]->id,
                'user_id' => $staff->id,
                'change_quantity' => -1,
                'reason' => 'Khách sử dụng hết - Phòng 101',
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ]
        ];

        DB::table('supply_logs')->insert($supplyLogs);
    }
}
