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
        // Xóa roles cũ để fresh data
        Role::query()->delete();

        $roles = [
            'admin' => 'Quản trị viên cấp cao nhất',
            'owner' => 'Chủ sở hữu homestay',
            'staff' => 'Nhân viên homestay',
            'user'  => 'Khách hàng',
        ];

        foreach ($roles as $name => $desc) {
            Role::create([
                'name' => $name,
                'description' => $desc,
            ]);
        }

        $this->command->info('✅ Created ' . count($roles) . ' roles');
    }
}
