<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Property;
use App\Models\Amenity;

class AmenitySeeder extends Seeder
{
    public function run(): void
    {
        $properties = Property::all();
        
        if ($properties->isEmpty()) {
            $this->command->warn('⚠️  No properties found. Skipping amenities creation.');
            return;
        }

        $amenities = [
            ['name' => 'WiFi miễn phí', 'type' => 'basic'],
            ['name' => 'Điều hòa', 'type' => 'basic'],
            ['name' => 'TV', 'type' => 'basic'],
            ['name' => 'Tủ lạnh', 'type' => 'basic'],
            ['name' => 'Máy nước nóng', 'type' => 'basic'],
            ['name' => 'Bếp', 'type' => 'advanced'],
            ['name' => 'Máy giặt', 'type' => 'advanced'],
            ['name' => 'Hồ bơi', 'type' => 'advanced'],
            ['name' => 'Gym', 'type' => 'advanced'],
            ['name' => 'Parking', 'type' => 'basic'],
            ['name' => 'Balcony', 'type' => 'advanced'],
            ['name' => 'Sea view', 'type' => 'advanced'],
        ];

        foreach ($properties as $property) {
            // Mỗi property có 6-8 amenities ngẫu nhiên
            $selectedAmenities = array_rand($amenities, rand(6, 8));
            
            foreach ($selectedAmenities as $index) {
                Amenity::firstOrCreate(
                    [
                        'property_id' => $property->id,
                        'name' => $amenities[$index]['name'],
                    ],
                    [
                        'type' => $amenities[$index]['type'],
                    ]
                );
            }
        }

        $this->command->info('✅ Created amenities for all properties');
    }
}

