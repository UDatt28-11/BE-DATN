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
        
        if ($roomTypes->isEmpty()) {
            $this->command->warn('⚠️  No room types found. Skipping room amenities creation.');
            return;
        }

        // Xóa dữ liệu cũ
        DB::table('room_amenities')->truncate();

        // Định nghĩa amenities cho từng loại phòng
        // Tất cả phòng cùng loại sẽ có cùng bộ amenities
        $roomTypeAmenities = [
            'Phòng Single' => [
                'WiFi miễn phí', 'Điều hòa nhiệt độ', 'TV màn hình phẳng', 
                'Tủ lạnh mini', 'Phòng tắm khép kín', 'Máy nước nóng',
            ],
            'Phòng Standard Double' => [
                'WiFi miễn phí', 'Điều hòa nhiệt độ', 'TV màn hình phẳng', 
                'Tủ lạnh mini', 'Phòng tắm khép kín', 'Máy nước nóng',
                'Ban công', 'Két sắt', 'Tầng cao',
            ],
            'Phòng Superior Twin' => [
                'WiFi miễn phí', 'Điều hòa nhiệt độ', 'TV màn hình phẳng', 
                'Tủ lạnh mini', 'Phòng tắm khép kín', 'Máy nước nóng',
                'Bàn làm việc', 'View bể bơi',
            ],
            'Phòng Deluxe Ocean View' => [
                'WiFi miễn phí', 'Điều hòa nhiệt độ', 'TV màn hình phẳng', 
                'Tủ lạnh mini', 'Phòng tắm khép kín', 'Máy nước nóng',
                'Ban công', 'Bồn tắm', 'View biển', 'Tầng cao',
                'Máy sấy tóc', 'Két sắt', 'Máy pha cà phê',
            ],
            'Phòng Triple' => [
                'WiFi miễn phí', 'Điều hòa nhiệt độ', 'TV màn hình phẳng', 
                'Tủ lạnh mini', 'Phòng tắm khép kín', 'Máy nước nóng',
                'Tủ quần áo', 'View vườn',
            ],
            'Phòng Family Deluxe' => [
                'WiFi miễn phí', 'Điều hòa nhiệt độ', 'TV màn hình phẳng', 
                'Tủ lạnh mini', 'Phòng tắm khép kín', 'Máy nước nóng',
                'Ban công', 'Bồn tắm', 'Tủ quần áo', 'Máy sấy tóc',
                'Két sắt', 'View biển',
            ],
            'Phòng Family Suite' => [
                'WiFi miễn phí', 'Điều hòa nhiệt độ', 'TV màn hình phẳng', 
                'Tủ lạnh mini', 'Phòng tắm khép kín', 'Máy nước nóng',
                'Ban công', 'Bồn tắm', 'Tủ quần áo', 'Máy sấy tóc',
                'Bếp riêng', 'Sofa bed', 'View biển', 'Két sắt',
            ],
            'Phòng Group Room' => [
                'WiFi miễn phí', 'Điều hòa nhiệt độ', 'TV màn hình phẳng', 
                'Tủ lạnh mini', 'Phòng tắm khép kín', 'Máy nước nóng',
                'Tủ quần áo', 'View bể bơi', 'Tầng trệt',
            ],
            'Villa Garden View' => [
                'WiFi miễn phí', 'Điều hòa nhiệt độ', 'TV màn hình phẳng', 
                'Tủ lạnh mini', 'Phòng tắm khép kín', 'Máy nước nóng',
                'Bếp đầy đủ', 'Sân vườn', 'BBQ', 'Bãi đỗ xe',
                'Máy giặt', 'View vườn', 'Tầng trệt',
            ],
            'Villa Beach Front' => [
                'WiFi miễn phí', 'Điều hòa nhiệt độ', 'TV màn hình phẳng', 
                'Tủ lạnh mini', 'Phòng tắm khép kín', 'Máy nước nóng',
                'Bếp đầy đủ', 'Hồ bơi riêng', 'Sân vườn', 'BBQ', 
                'Bãi đỗ xe', 'Máy giặt', 'View biển', 'Tầng trệt',
                'Bồn tắm', 'Ban công',
            ],
        ];

        $totalAssigned = 0;

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
                        $totalAssigned++;
                    }
                }
            }
        }

        $this->command->info("✅ Assigned {$totalAssigned} amenity relationships to rooms");
    }
}
