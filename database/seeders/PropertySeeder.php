<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Property;

class PropertySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy tất cả người dùng hiện có để gán làm chủ sở hữu
        $users = User::all();

        // Kiểm tra xem có người dùng nào không, nếu không thì dừng lại
        if ($users->isEmpty()) {
            $this->command->info('No users found, skipping property creation.');
            return;
        }

        // Tạo 10 homestay mẫu
        foreach (range(1, 10) as $index) {
            Property::create([
                'owner_id' => $users->random()->id, // Lấy ngẫu nhiên một user ID
                'name' => 'Homestay Mẫu ' . $index,
                'address' => $index . ' Đường ABC, Quận ' . $index . ', TP. HCM',
                'description' => 'Đây là mô tả chi tiết cho homestay mẫu số ' . $index . '.',
                'check_in_time' => '14:00',
                'check_out_time' => '12:00',
                'status' => 'active',
            ]);
        }
    }
}
