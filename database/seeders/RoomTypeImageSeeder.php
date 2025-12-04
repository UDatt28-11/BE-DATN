<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RoomType;
use App\Models\RoomTypeImage;

class RoomTypeImageSeeder extends Seeder
{
    public function run(): void
    {
        $roomTypes = RoomType::all();
        
        if ($roomTypes->isEmpty()) {
            $this->command->warn('⚠️  No room types found. Skipping room type images creation.');
            return;
        }

        // Xóa tất cả ảnh cũ
        RoomTypeImage::truncate();

        // Ảnh mẫu cho từng loại phòng (có thể thay bằng URL thực tế)
        $sampleImages = [
            'Phòng Standard' => [
                'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=800',
                'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=800',
                'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=800',
            ],
            'Phòng Deluxe' => [
                'https://images.unsplash.com/photo-1596394516093-501ba68a0ba6?w=800',
                'https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=800',
                'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?w=800',
            ],
            'Phòng Family' => [
                'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800',
                'https://images.unsplash.com/photo-1618221195710-dd6b41faaea8?w=800',
                'https://images.unsplash.com/photo-1595576508896-5b3a0b8b8c8c?w=800',
            ],
            'Studio' => [
                'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=800',
                'https://images.unsplash.com/photo-1556912172-45b7abe8b7e1?w=800',
                'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=800',
            ],
        ];

        foreach ($roomTypes as $roomType) {
            $images = $sampleImages[$roomType->name] ?? [];
            
            // Nếu có image_url trong room_type, thêm vào đầu danh sách
            if ($roomType->image_url) {
                array_unshift($images, $roomType->image_url);
            }

            // Nếu không có ảnh nào, dùng ảnh mặc định
            if (empty($images)) {
                $images = ['https://via.placeholder.com/800x600?text=' . urlencode($roomType->name)];
            }

            // Tạo ảnh cho room type
            foreach ($images as $index => $imageUrl) {
                RoomTypeImage::create([
                    'room_type_id' => $roomType->id,
                    'image_url' => $imageUrl,
                    'is_primary' => $index === 0, // Ảnh đầu tiên là primary
                ]);
            }
        }

        $this->command->info('✅ Created room type images');
    }
}

