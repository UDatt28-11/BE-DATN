<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'super_admin',
                'description' => 'Quản trị viên hệ thống'
            ],
            [
                'name' => 'owner',
                'description' => 'Chủ sở hữu homestay'
            ],
            [
                'name' => 'staff',
                'description' => 'Nhân viên homestay'
            ],
            [
                'name' => 'guest',
                'description' => 'Khách hàng'
            ]
        ];

        DB::table('roles')->insert($roles);
    }
}
