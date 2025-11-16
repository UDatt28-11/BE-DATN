<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Property;
use App\Models\RoomType;
use App\Models\Room;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $properties = Property::all();
        
        if ($properties->isEmpty()) {
            $this->command->warn('⚠️  No properties found. Skipping rooms creation.');
            return;
        }

        foreach ($properties as $property) {
            $roomTypes = RoomType::where('property_id', $property->id)->get();
            
            if ($roomTypes->isEmpty()) {
                continue;
            }

            // Tạo 2-3 phòng cho mỗi room type
            foreach ($roomTypes as $roomType) {
                $roomCount = rand(2, 3);
                
                for ($i = 1; $i <= $roomCount; $i++) {
                    Room::firstOrCreate(
                        [
                            'property_id' => $property->id,
                            'room_type_id' => $roomType->id,
                            'name' => $roomType->name . ' ' . $i,
                        ],
                        [
                            'description' => 'Phòng ' . $i . ' thuộc loại ' . $roomType->name,
                            'max_adults' => rand(2, 4),
                            'max_children' => rand(0, 2),
                            'price_per_night' => rand(300000, 2000000),
                            'status' => ['available', 'available', 'available', 'maintenance'][rand(0, 3)],
                            'verification_status' => ['pending', 'verified', 'verified'][rand(0, 2)],
                        ]
                    );
                }
            }
        }

        $this->command->info('✅ Created rooms for all properties');
    }
}
