<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $properties = Property::all();

        foreach ($properties as $property) {
            $roomTypes = RoomType::where('property_id', $property->id)->get();

            foreach ($roomTypes as $roomType) {
                // Tạo 2-3 phòng cho mỗi loại phòng
                $roomCount = rand(2, 3);
                
                for ($i = 1; $i <= $roomCount; $i++) {
                    $maxAdults = match($roomType->name) {
                        'Phòng Standard' => 2,
                        'Phòng Deluxe' => 2,
                        'Phòng Suite' => 3,
                        'Phòng Family' => 4,
                        default => 2,
                    };

                    $maxChildren = match($roomType->name) {
                        'Phòng Standard' => 1,
                        'Phòng Deluxe' => 1,
                        'Phòng Suite' => 2,
                        'Phòng Family' => 3,
                        default => 1,
                    };

                    $pricePerNight = match($roomType->name) {
                        'Phòng Standard' => rand(500000, 800000),
                        'Phòng Deluxe' => rand(1000000, 1500000),
                        'Phòng Suite' => rand(2000000, 3000000),
                        'Phòng Family' => rand(1500000, 2500000),
                        default => 500000,
                    };

                    Room::create([
                        'property_id' => $property->id,
                        'room_type_id' => $roomType->id,
                        'name' => $roomType->name . ' ' . $i,
                        'description' => $roomType->description . ' Phòng số ' . $i . ' của ' . $property->name . '.',
                        'max_adults' => $maxAdults,
                        'max_children' => $maxChildren,
                        'price_per_night' => $pricePerNight,
                        'status' => 'available',
                    ]);
                }
            }
        }
    }
}

