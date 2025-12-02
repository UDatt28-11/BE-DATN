<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Property;
use App\Models\Service;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $property = Property::first();
        
        if (!$property) {
            $this->command->warn('⚠️  No property found. Skipping services creation.');
            return;
        }

        // Xóa services cũ của property này
        Service::where('property_id', $property->id)->delete();

        // Tạo danh sách services sạch và thực tế
        $services = [
            ['name' => 'Ăn sáng buffet', 'unit' => 'người', 'price' => 150000],
            ['name' => 'Dịch vụ giặt là', 'unit' => 'kg', 'price' => 50000],
            ['name' => 'Xe đưa đón sân bay', 'unit' => 'chuyến', 'price' => 400000],
            ['name' => 'Thuê xe máy', 'unit' => 'ngày', 'price' => 200000],
            ['name' => 'Dịch vụ massage', 'unit' => 'lượt', 'price' => 300000],
            ['name' => 'Tour du lịch nội thành', 'unit' => 'người', 'price' => 600000],
        ];

        foreach ($services as $serviceData) {
            Service::create([
                'property_id' => $property->id,
                'name' => $serviceData['name'],
                'price' => $serviceData['price'],
                'unit' => $serviceData['unit'],
            ]);
        }

        $this->command->info('✅ Created ' . count($services) . ' services for property');
    }
}

