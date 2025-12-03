<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Amenity;
use Illuminate\Support\Facades\DB;

class RoomAmenitySeeder extends Seeder
{
    public function run(): void
    {
        $roomTypes = RoomType::with('rooms')->get();
        $amenities = Amenity::all();
        
        if ($roomTypes->isEmpty() || $amenities->isEmpty()) {
            $this->command->warn('⚠️  No room types or amenities found. Skipping room amenities creation.');
            return;
        }

        // Xóa dữ liệu cũ
        DB::table('room_amenities')->truncate();

        // Định nghĩa amenities cho từng loại phòng
        // Tất cả phòng cùng loại sẽ có cùng bộ amenities
        // Lưu ý: Tên amenities phải khớp chính xác với tên trong AmenitySeeder
        $roomTypeAmenities = [
            'Phòng Standard' => [
                'WiFi miễn phí',
                'Điều hòa nhiệt độ',
                'TV màn hình phẳng',
                'Tủ lạnh mini',
                'Phòng tắm khép kín',
                'Máy nước nóng',
            ],
            'Phòng Deluxe' => [
                'WiFi miễn phí',
                'Điều hòa nhiệt độ',
                'TV màn hình phẳng',
                'Tủ lạnh mini',
                'Phòng tắm khép kín',
                'Máy nước nóng',
                'Ban công',
                'Bàn làm việc',
                'Bồn tắm',
            ],
            'Phòng Family' => [
                'WiFi miễn phí',
                'Điều hòa nhiệt độ',
                'TV màn hình phẳng',
                'Tủ lạnh mini',
                'Phòng tắm khép kín',
                'Máy nước nóng',
                'Bồn tắm',
                'Tủ quần áo',
                'Máy sấy tóc',
            ],
            'Studio' => [
                'WiFi miễn phí',
                'Điều hòa nhiệt độ',
                'TV màn hình phẳng',
                'Tủ lạnh mini',
                'Phòng tắm khép kín',
                'Máy nước nóng',
                'Bếp đầy đủ',
                'Bàn làm việc',
                'Tủ quần áo',
            ],
        ];

        foreach ($roomTypes as $roomType) {
            // Lấy danh sách amenities cho loại phòng này
            $amenityNames = $roomTypeAmenities[$roomType->name] ?? [];
            
            if (empty($amenityNames)) {
                // Nếu không có config, lấy 5-7 amenities ngẫu nhiên từ property
                $propertyAmenities = Amenity::where('property_id', $roomType->property_id)->get();
                if ($propertyAmenities->isEmpty()) {
                    continue;
                }
                $selectedCount = min(rand(5, 7), $propertyAmenities->count());
                $selectedAmenities = $propertyAmenities->random($selectedCount);
            } else {
                // Tìm amenities theo tên
                $selectedAmenities = Amenity::where('property_id', $roomType->property_id)
                    ->whereIn('name', $amenityNames)
                    ->get();
            }
            
            if ($selectedAmenities->isEmpty()) {
                $this->command->warn("⚠️  No amenities found for room type: {$roomType->name}");
                continue;
            }

            // Gán cùng bộ amenities cho TẤT CẢ phòng thuộc loại này
            foreach ($roomType->rooms as $room) {
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
            
            $this->command->info("✅ Assigned " . $selectedAmenities->count() . " amenities to all rooms of type: {$roomType->name}");
        }

        $this->command->info('✅ Created room amenities relationships (grouped by room type)');
    }
}

