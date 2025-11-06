<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role; // Đảm bảo bạn đã import Model Role

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'admin' => 'Quản trị viên cấp cao nhất',
            'owner'       => 'Chủ sở hữu homestay',
            'staff'       => 'Nhân viên homestay',
            'guest'       => 'Khách hàng',
        ];

        foreach ($roles as $name => $desc) {
            Role::firstOrCreate(['name' => $name], ['description' => $desc]);
        }
    }
}
