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
        // Sử dụng firstOrCreate để không tạo trùng lặp nếu chạy seeder nhiều lần
        Role::firstOrCreate(['name' => 'super_admin'], ['description' => 'Quản trị viên cấp cao nhất']);
        Role::firstOrCreate(['name' => 'owner'], ['description' => 'Chủ sở hữu homestay']);
        Role::firstOrCreate(['name' => 'staff'], ['description' => 'Nhân viên homestay']);
        Role::firstOrCreate(['name' => 'guest'], ['description' => 'Khách hàng']);
    }
}
