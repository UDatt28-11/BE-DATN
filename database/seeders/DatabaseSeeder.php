<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,      // <-- Chạy trước
            UserSeeder::class,      // <-- Chạy sau để có thể gán vai trò
            PropertySeeder::class,
            RoomSeeder::class,
        ]);
    }
}
