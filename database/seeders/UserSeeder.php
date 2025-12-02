<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tạo / Lấy đúng role bằng firstOrCreate (tránh nhầm lẫn)
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'Quản trị viên cấp cao nhất']
        );

        $ownerRole = Role::firstOrCreate(
            ['name' => 'owner'],
            ['description' => 'Chủ sở hữu homestay']
        );

        $userRole = Role::firstOrCreate(
            ['name' => 'user'],
            ['description' => 'Khách hàng']
        );

        // 2. Tạo Super Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@staybook.com'],
            [
                'full_name'    => 'Nguyễn Văn A',
                'role'         => 'admin',
                'password'     => Hash::make('password'),
                'status'       => 'active',
                'phone_number' => '0123456789',
            ]
        );
        $admin->roles()->syncWithoutDetaching($adminRole->id);

        // 3. Tạo Owner
        $owner = User::firstOrCreate(
            ['email' => 'owner@staybook.com'],
            [
                'full_name'    => 'Trần Văn Chủ',
                'role'         => 'owner',
                'password'     => Hash::make('password'),
                'status'       => 'active',
                'phone_number' => '0987654321',
            ]
        );
        $owner->roles()->syncWithoutDetaching($ownerRole->id);

        // 4. Tạo User
        $user = User::firstOrCreate(
            ['email' => 'user@staybook.com'],
            [
                'full_name'    => 'Nguyễn Văn Khách',
                'role'         => 'user',
                'password'     => Hash::make('password'),
                'status'       => 'active',
                'phone_number' => '0111222333',
            ]
        );
        $user->roles()->syncWithoutDetaching($userRole->id);

        // 5. Tạo thêm 2 User để test (giảm từ 10 xuống 2)
        User::factory(2)->create()->each(function ($user) use ($userRole) {
            $user->roles()->syncWithoutDetaching($userRole->id);
            $user->phone_number = '0' . rand(100000000, 999999999);
            $user->save();
        });

        $this->command->info('✅ Created users: admin, owner, staff, and 2 test users');
    }
}
