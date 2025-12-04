<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Xóa users cũ (trừ admin nếu cần giữ lại, nhưng để fresh thì xóa hết)
        // Lưu ý: Xóa user_roles trước để tránh foreign key constraint
        DB::table('user_roles')->delete();
        User::query()->delete();

        // Lấy roles (đã được tạo bởi RoleSeeder)
        $adminRole = Role::where('name', 'admin')->first();
        $ownerRole = Role::where('name', 'owner')->first();
        $userRole = Role::where('name', 'user')->first();

        if (!$adminRole || !$ownerRole || !$userRole) {
            $this->command->error('❌ Roles not found. Please run RoleSeeder first.');
            return;
        }

        // 1. Tạo Super Admin
        $admin = User::create([
            'full_name'    => 'Nguyễn Văn A',
            'email'        => 'admin@staybook.com',
            'role'         => 'admin',
            'password'     => Hash::make('password'),
            'status'       => 'active',
            'phone_number' => '0823456789',
        ]);
        $admin->roles()->attach($adminRole->id);

        // 2. Tạo Owner
        $owner = User::create([
            'full_name'    => 'Trần Văn Chủ',
            'email'        => 'owner@staybook.com',
            'role'         => 'owner',
            'password'     => Hash::make('password'),
            'status'       => 'active',
            'phone_number' => '0987654321',
        ]);
        $owner->roles()->attach($ownerRole->id);

        // 3. Tạo User
        $user = User::create([
            'full_name'    => 'Nguyễn Văn Khách',
            'email'        => 'user@staybook.com',
            'role'         => 'user',
            'password'     => Hash::make('password'),
            'status'       => 'active',
            'phone_number' => '0111222333',
        ]);
        $user->roles()->attach($userRole->id);

        // 4. Tạo thêm 2 User để test
        for ($i = 1; $i <= 2; $i++) {
            $testUser = User::create([
                'full_name'    => 'User Test ' . $i,
                'email'        => 'usertest' . $i . '@staybook.com',
                'role'         => 'user',
                'password'     => Hash::make('password'),
                'status'       => 'active',
                'phone_number' => '0' . rand(100000000, 999999999),
            ]);
            $testUser->roles()->attach($userRole->id);
        }

        $this->command->info('✅ Created users: admin, owner, and 2 test users');
    }
}
