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
        $staffRole = Role::where('name', 'staff')->first();

        if (!$staffRole) {
            $this->command->warn('⚠️  Staff role not found. Please run RoleSeeder first.');
            return;
        }

        // Xóa staff user cũ nếu có
        $oldStaff = User::where('email', 'staff@staybook.com')->first();
        if ($oldStaff) {
            $oldStaff->roles()->detach();
            $oldStaff->delete();
        }

        // Tạo Staff user mới
        $staff = User::create([
            'full_name'    => 'Lê Văn Nhân Viên',
            'email'        => 'staff@staybook.com',
            'role'         => 'staff',
            'password'     => Hash::make('password'),
            'status'       => 'active',
            'phone_number' => '0123456788',
        ]);
        $staff->roles()->attach($staffRole->id);

        $this->command->info('✅ Staff user created: staff@staybook.com / password');
    }
}

