<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role; // Import thêm Model Role

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy các vai trò từ database để sử dụng
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $guestRole = Role::where('name', 'guest')->first();

        // 1. Tạo Super Admin và gán vai trò
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'full_name' => 'Admin User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        // Gán vai trò super_admin cho user này
        $adminUser->roles()->syncWithoutDetaching([$superAdminRole->id]);


        // 2. Tạo 5 người dùng ngẫu nhiên và gán vai trò 'guest'
        User::factory()->count(5)->create()->each(function ($user) use ($guestRole) {
            $user->roles()->attach($guestRole->id);
        });
    }
}
