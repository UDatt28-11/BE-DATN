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

        // Tạo 1 property với dữ liệu sạch và thực tế
        Property::create([
            'owner_id' => $owner->id,
            'name' => 'Sunrise Beach Resort & Spa',
            'address' => '88 Đường Trần Phú, Bãi Trước, Thành phố Vũng Tàu, Bà Rịa - Vũng Tàu',
            'description' => 'Resort 5 sao nằm ngay bờ biển Vũng Tàu với view hoàng hôn tuyệt đẹp. Hồ bơi vô cực, spa cao cấp, nhà hàng buffet quốc tế. Phù hợp cho du lịch nghỉ dưỡng, team building và hội nghị. Đa dạng loại phòng từ phòng đơn đến villa mặt biển.',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'status' => 'active',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        $this->command->info('✅ Created 1 property: Sunrise Beach Resort & Spa');
    }
}
