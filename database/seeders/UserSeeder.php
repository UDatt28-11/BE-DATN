<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Tạo Super Admin
        User::create([
            'full_name' => 'Super Admin',
            'email' => 'admin@homestay.com',
            'password' => Hash::make('password123'),
            'phone_number' => '0123456789',
            'status' => 'active',
            'gender' => 'male',
            'preferred_language' => 'vi',
            'email_verified_at' => now(),
        ]);

        // Tạo Owner
        User::create([
            'full_name' => 'Nguyễn Văn A',
            'email' => 'owner@homestay.com',
            'password' => Hash::make('password123'),
            'phone_number' => '0987654321',
            'status' => 'active',
            'gender' => 'male',
            'preferred_language' => 'vi',
            'email_verified_at' => now(),
        ]);

        // Tạo Staff
        User::create([
            'full_name' => 'Trần Thị B',
            'email' => 'staff@homestay.com',
            'password' => Hash::make('password123'),
            'phone_number' => '0912345678',
            'status' => 'active',
            'gender' => 'female',
            'preferred_language' => 'vi',
            'email_verified_at' => now(),
        ]);

        // Tạo Guests
        User::create([
            'full_name' => 'Lê Văn C',
            'email' => 'guest1@email.com',
            'password' => Hash::make('password123'),
            'phone_number' => '0923456789',
            'status' => 'active',
            'gender' => 'male',
            'preferred_language' => 'vi',
            'email_verified_at' => now(),
        ]);

        User::create([
            'full_name' => 'Phạm Thị D',
            'email' => 'guest2@email.com',
            'password' => Hash::make('password123'),
            'phone_number' => '0934567890',
            'status' => 'active',
            'gender' => 'female',
            'preferred_language' => 'vi',
            'email_verified_at' => now(),
        ]);

        User::create([
            'full_name' => 'Hoàng Văn E',
            'email' => 'guest3@email.com',
            'password' => Hash::make('password123'),
            'phone_number' => '0945678901',
            'status' => 'active',
            'gender' => 'male',
            'preferred_language' => 'vi',
            'email_verified_at' => now(),
        ]);
    }
}
