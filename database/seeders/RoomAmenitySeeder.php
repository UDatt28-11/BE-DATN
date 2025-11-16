<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;
use App\Models\Amenity;
use Illuminate\Support\Facades\DB;

class RoomAmenitySeeder extends Seeder
{
    public function run(): void
    {
        $rooms = Room::all();
        $amenities = Amenity::all();
        
        if ($rooms->isEmpty() || $amenities->isEmpty()) {
            $this->command->warn('⚠️  No rooms or amenities found. Skipping room amenities creation.');
            return;
        }

        // Xóa dữ liệu cũ
        DB::table('room_amenities')->truncate();

        foreach ($rooms as $room) {
            // Mỗi phòng có 4-6 amenities ngẫu nhiên từ property của nó
            $propertyAmenities = Amenity::where('property_id', $room->property_id)->get();
            
            if ($propertyAmenities->isEmpty()) {
                continue;
            }

            $selectedCount = min(rand(4, 6), $propertyAmenities->count());
            $selectedAmenities = $propertyAmenities->random($selectedCount);
            
            foreach ($selectedAmenities as $amenity) {
                // Kiểm tra xem relationship đã tồn tại chưa
                $exists = DB::table('room_amenities')
                    ->where('room_id', $room->id)
                    ->where('amenity_id', $amenity->id)
                    ->exists();
                
                if (!$exists) {
                    DB::table('room_amenities')->insert([
                        'room_id' => $room->id,
                        'amenity_id' => $amenity->id,
                    ]);
                }
            }
        }

        $this->command->info('✅ Created room amenities relationships');
    }
}

