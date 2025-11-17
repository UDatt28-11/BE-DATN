<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        User::create([
            'full_name' => 'Nguyễn Văn A',
            'email' => 'admin@homestayhub.com',
            'password' => Hash::make('admin123'),
            'phone_number' => '0912345678',
            'role' => 'admin',
            'status' => 'active',
            'date_of_birth' => '1990-01-15',
            'gender' => 'male',
            'address' => '123 Đường ABC, Quận 1, TP.HCM',
            'preferred_language' => 'vi',
            'email_verified_at' => now(),
        ]);

        // Staff users
        User::create([
            'full_name' => 'Trần Thị B',
            'email' => 'staff1@homestayhub.com',
            'password' => Hash::make('staff123'),
            'phone_number' => '0912345679',
            'role' => 'staff',
            'status' => 'active',
            'date_of_birth' => '1995-05-20',
            'gender' => 'female',
            'address' => '456 Đường XYZ, Quận 2, TP.HCM',
            'preferred_language' => 'vi',
            'email_verified_at' => now(),
        ]);

        User::create([
            'full_name' => 'Lê Văn C',
            'email' => 'staff2@homestayhub.com',
            'password' => Hash::make('staff123'),
            'phone_number' => '0912345680',
            'role' => 'staff',
            'status' => 'active',
            'date_of_birth' => '1992-08-10',
            'gender' => 'male',
            'address' => '789 Đường DEF, Quận 3, TP.HCM',
            'preferred_language' => 'vi',
            'email_verified_at' => now(),
        ]);

        // Regular users
        $users = [
            [
                'full_name' => 'Phạm Thị D',
                'email' => 'user1@example.com',
                'phone_number' => '0912345681',
                'date_of_birth' => '1998-03-25',
                'gender' => 'female',
            ],
            [
                'full_name' => 'Hoàng Văn E',
                'email' => 'user2@example.com',
                'phone_number' => '0912345682',
                'date_of_birth' => '1996-07-12',
                'gender' => 'male',
            ],
            [
                'full_name' => 'Võ Thị F',
                'email' => 'user3@example.com',
                'phone_number' => '0912345683',
                'date_of_birth' => '2000-11-30',
                'gender' => 'female',
            ],
            [
                'full_name' => 'Đặng Văn G',
                'email' => 'user4@example.com',
                'phone_number' => '0912345684',
                'date_of_birth' => '1994-09-18',
                'gender' => 'male',
            ],
            [
                'full_name' => 'Bùi Thị H',
                'email' => 'user5@example.com',
                'phone_number' => '0912345685',
                'date_of_birth' => '1997-12-05',
                'gender' => 'female',
            ],
        ];

        foreach ($users as $userData) {
            User::create([
                'full_name' => $userData['full_name'],
                'email' => $userData['email'],
                'password' => Hash::make('user123'),
                'phone_number' => $userData['phone_number'],
                'role' => 'user',
                'status' => 'active',
                'date_of_birth' => $userData['date_of_birth'],
                'gender' => $userData['gender'],
                'address' => 'Địa chỉ mẫu, TP.HCM',
                'preferred_language' => 'vi',
                'email_verified_at' => now(),
            ]);
        }
    }
}

