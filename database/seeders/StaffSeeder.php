<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $staffRole = Role::firstOrCreate(
            ['name' => 'staff'],
            ['description' => 'Nhân viên homestay']
        );

        // Tạo Staff user để test
        $staff = User::firstOrCreate(
            ['email' => 'staff@staybook.com'],
            [
                'full_name'    => 'Nhân viên Test',
                'role'         => 'staff',
                'password'     => Hash::make('password'),
                'status'       => 'active',
                'phone_number' => '0123456788',
            ]
        );
        $staff->roles()->syncWithoutDetaching($staffRole->id);

        $this->command->info('✅ Staff user created: staff@staybook.com / password');
    }
}

