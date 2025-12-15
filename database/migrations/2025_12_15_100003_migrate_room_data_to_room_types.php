<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Di chuyển dữ liệu từ rooms sang room_types
     */
    public function up(): void
    {
        // 1. Cập nhật room_types với thông tin từ room đầu tiên của mỗi loại
        $roomTypes = DB::table('room_types')->get();
        
        foreach ($roomTypes as $roomType) {
            $firstRoom = DB::table('rooms')
                ->where('room_type_id', $roomType->id)
                ->first();
            
            if ($firstRoom) {
                DB::table('room_types')
                    ->where('id', $roomType->id)
                    ->update([
                        'base_price' => $firstRoom->price_per_night,
                        'max_adults' => $firstRoom->max_adults,
                        'max_children' => $firstRoom->max_children,
                    ]);
            }
        }

        // 2. Di chuyển amenities từ room_amenities sang room_type_amenities
        // Lấy tất cả room_amenities và nhóm theo room_type
        $roomAmenities = DB::table('room_amenities')
            ->join('rooms', 'rooms.id', '=', 'room_amenities.room_id')
            ->select('rooms.room_type_id', 'room_amenities.amenity_id')
            ->distinct()
            ->get();

        foreach ($roomAmenities as $ra) {
            // Kiểm tra xem đã tồn tại chưa
            $exists = DB::table('room_type_amenities')
                ->where('room_type_id', $ra->room_type_id)
                ->where('amenity_id', $ra->amenity_id)
                ->exists();
            
            if (!$exists) {
                DB::table('room_type_amenities')->insert([
                    'room_type_id' => $ra->room_type_id,
                    'amenity_id' => $ra->amenity_id,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Xóa dữ liệu trong room_type_amenities
        DB::table('room_type_amenities')->truncate();
        
        // Reset room_types về giá trị mặc định
        DB::table('room_types')->update([
            'base_price' => 0,
            'max_adults' => 2,
            'max_children' => 0,
        ]);
    }
};

