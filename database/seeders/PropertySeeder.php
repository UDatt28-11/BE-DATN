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
        // Lấy owner user (hoặc tạo nếu chưa có)
        $owner = User::where('role', 'owner')->orWhere('email', 'owner@staybook.com')->first();

        if (!$owner) {
            $this->command->warn('⚠️  No owner user found. Please run UserSeeder first.');
            return;
        }

        // Xóa properties cũ nếu có
        Property::query()->delete();

        // Chỉ tạo 1 property với dữ liệu sạch và thực tế
        Property::create([
            'owner_id' => $owner->id,
            'name' => 'Homestay Sài Gòn View',
            'address' => '123 Đường Nguyễn Huệ, Quận 1, Thành phố Hồ Chí Minh',
            'description' => 'Homestay hiện đại nằm tại trung tâm Quận 1, gần các điểm du lịch nổi tiếng. Không gian rộng rãi, tiện nghi đầy đủ, view đẹp. Phù hợp cho gia đình và nhóm bạn.',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'status' => 'active',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        $this->command->info('✅ Created 1 property: Homestay Sài Gòn View');
    }
}
