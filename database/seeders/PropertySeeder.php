<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'owner@homestay.com')->first();

        $properties = [
            [
                'owner_id' => $owner->id,
                'name' => 'Homestay Sapa View',
                'address' => '123 Đường Fansipan, Sapa, Lào Cai',
                'description' => 'Homestay với view đẹp ra núi Fansipan, không gian ấm cúng và hiện đại',
                'check_in_time' => '14:00:00',
                'check_out_time' => '12:00:00',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'owner_id' => $owner->id,
                'name' => 'Mountain Lodge Đà Lạt',
                'address' => '456 Đường Tùng Lâm, Đà Lạt, Lâm Đồng',
                'description' => 'Lodge cao cấp với view hồ Tuyền Lâm, phù hợp cho gia đình và nhóm bạn',
                'check_in_time' => '15:00:00',
                'check_out_time' => '11:00:00',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'owner_id' => $owner->id,
                'name' => 'Beach House Phú Quốc',
                'address' => '789 Bãi Trường, Phú Quốc, Kiên Giang',
                'description' => 'Nhà nghỉ gần biển với không gian mở, lý tưởng cho du lịch gia đình',
                'check_in_time' => '14:00:00',
                'check_out_time' => '12:00:00',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        DB::table('properties')->insert($properties);
    }
}
