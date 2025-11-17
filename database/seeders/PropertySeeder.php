<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();

        $properties = [
            [
                'name' => 'Homestay Sài Gòn View',
                'address' => '123 Nguyễn Huệ, Quận 1, TP.HCM',
                'description' => 'Homestay hiện đại với view đẹp ra sông Sài Gòn, gần trung tâm thành phố, tiện nghi đầy đủ.',
                'check_in_time' => '14:00',
                'check_out_time' => '12:00',
                'status' => 'active',
            ],
            [
                'name' => 'Palatin Boutique Homestay',
                'address' => '456 Lê Lợi, Quận 1, TP.HCM',
                'description' => 'Homestay sang trọng với thiết kế boutique, không gian ấm cúng, phù hợp cho gia đình và nhóm bạn.',
                'check_in_time' => '15:00',
                'check_out_time' => '11:00',
                'status' => 'active',
            ],
            [
                'name' => 'Green Valley Homestay',
                'address' => '789 Điện Biên Phủ, Quận Bình Thạnh, TP.HCM',
                'description' => 'Homestay xanh mát với vườn cây, không khí trong lành, yên tĩnh, cách xa ồn ào thành phố.',
                'check_in_time' => '14:00',
                'check_out_time' => '12:00',
                'status' => 'active',
            ],
        ];

        foreach ($properties as $propertyData) {
            Property::create([
                'owner_id' => $admin->id,
                'name' => $propertyData['name'],
                'address' => $propertyData['address'],
                'description' => $propertyData['description'],
                'check_in_time' => $propertyData['check_in_time'],
                'check_out_time' => $propertyData['check_out_time'],
                'status' => $propertyData['status'],
            ]);
        }
    }
}

