<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Property;
use App\Models\Amenity;
use Illuminate\Support\Facades\Schema;

class AmenitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Tạo đầy đủ amenities theo yêu cầu filter:
     * - Key Amenities: Bồn tắm, Ban công, Bếp riêng, Khép kín
     * - View: View vườn, View núi, View bể bơi, View thành phố
     * - Floor: Tầng trệt, Tầng cao, Gác mái
     * - Tiện ích khác: WiFi, Điều hòa, TV, etc.
     */
    public function run(): void
    {
        $property = Property::first();
        
        if (!$property) {
            $this->command->warn('⚠️  No property found. Skipping amenities creation.');
            return;
        }

        // Xóa amenities cũ của property này
        Amenity::where('property_id', $property->id)->delete();

        // Tạo danh sách amenities đầy đủ theo yêu cầu filter
        $amenities = [
            // ========== KEY AMENITIES (Tiện nghi đặc biệt) ==========
            ['name' => 'Bồn tắm', 'type' => 'advanced', 'category' => 'facility', 'filter_category' => 'key_amenity'],
            ['name' => 'Ban công', 'type' => 'advanced', 'category' => 'facility', 'filter_category' => 'key_amenity'],
            ['name' => 'Bếp riêng', 'type' => 'advanced', 'category' => 'facility', 'filter_category' => 'key_amenity'],
            ['name' => 'Phòng tắm khép kín', 'type' => 'advanced', 'category' => 'facility', 'filter_category' => 'key_amenity'],
            
            // ========== VIEW (Hướng nhìn) ==========
            ['name' => 'View vườn', 'type' => 'advanced', 'category' => 'facility', 'filter_category' => 'view'],
            ['name' => 'View núi', 'type' => 'advanced', 'category' => 'facility', 'filter_category' => 'view'],
            ['name' => 'View bể bơi', 'type' => 'advanced', 'category' => 'facility', 'filter_category' => 'view'],
            ['name' => 'View thành phố', 'type' => 'advanced', 'category' => 'facility', 'filter_category' => 'view'],
            ['name' => 'View biển', 'type' => 'advanced', 'category' => 'facility', 'filter_category' => 'view'],
            
            // ========== FLOOR (Vị trí tầng) ==========
            ['name' => 'Tầng trệt', 'type' => 'basic', 'category' => 'facility', 'filter_category' => 'floor'],
            ['name' => 'Tầng cao', 'type' => 'basic', 'category' => 'facility', 'filter_category' => 'floor'],
            ['name' => 'Gác mái', 'type' => 'advanced', 'category' => 'facility', 'filter_category' => 'floor'],
            
            // ========== TIỆN ÍCH KHÁC (Không có filter_category) ==========
            ['name' => 'WiFi miễn phí', 'type' => 'basic', 'category' => 'facility', 'filter_category' => null],
            ['name' => 'Điều hòa nhiệt độ', 'type' => 'basic', 'category' => 'facility', 'filter_category' => null],
            ['name' => 'TV màn hình phẳng', 'type' => 'basic', 'category' => 'facility', 'filter_category' => null],
            ['name' => 'Tủ lạnh mini', 'type' => 'basic', 'category' => 'facility', 'filter_category' => null],
            ['name' => 'Máy nước nóng', 'type' => 'basic', 'category' => 'facility', 'filter_category' => null],
            ['name' => 'Bếp đầy đủ', 'type' => 'advanced', 'category' => 'facility', 'filter_category' => null],
            ['name' => 'Máy giặt', 'type' => 'advanced', 'category' => 'service', 'filter_category' => null],
            ['name' => 'Bãi đỗ xe', 'type' => 'basic', 'category' => 'facility', 'filter_category' => null],
            ['name' => 'An ninh 24/7', 'type' => 'safety', 'category' => 'service', 'filter_category' => null],
            ['name' => 'Thang máy', 'type' => 'basic', 'category' => 'facility', 'filter_category' => null],
            ['name' => 'Bàn làm việc', 'type' => 'basic', 'category' => 'facility', 'filter_category' => null],
            ['name' => 'Tủ quần áo', 'type' => 'basic', 'category' => 'facility', 'filter_category' => null],
            ['name' => 'Máy sấy tóc', 'type' => 'basic', 'category' => 'facility', 'filter_category' => null],
            ['name' => 'Dịch vụ dọn phòng', 'type' => 'advanced', 'category' => 'service', 'filter_category' => null],
        ];

        foreach ($amenities as $amenityData) {
            $amenity = Amenity::create([
                'property_id' => $property->id,
                'name' => $amenityData['name'],
                'type' => $amenityData['type'],
            ]);
            
            // Thêm category nếu cột tồn tại
            if (isset($amenityData['category']) && Schema::hasColumn('amenities', 'category')) {
                $amenity->category = $amenityData['category'];
            }
            
            // Thêm filter_category nếu cột tồn tại
            if (isset($amenityData['filter_category']) && Schema::hasColumn('amenities', 'filter_category')) {
                $amenity->filter_category = $amenityData['filter_category'];
            }
            
            $amenity->save();
        }

        $this->command->info('✅ Created ' . count($amenities) . ' amenities for property');
        $this->command->info('   - Key Amenities: ' . count(array_filter($amenities, fn($a) => $a['filter_category'] === 'key_amenity')));
        $this->command->info('   - View: ' . count(array_filter($amenities, fn($a) => $a['filter_category'] === 'view')));
        $this->command->info('   - Floor: ' . count(array_filter($amenities, fn($a) => $a['filter_category'] === 'floor')));
        $this->command->info('   - Other: ' . count(array_filter($amenities, fn($a) => $a['filter_category'] === null)));
    }
}
