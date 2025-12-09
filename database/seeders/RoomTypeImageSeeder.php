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

        // Ảnh mẫu cho từng loại phòng (Unsplash images)
        $sampleImages = [
            // Resort Room Types
            'Phòng Single' => [
                'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=800',
                'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=800',
            ],
            'Phòng Standard Double' => [
                'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=800',
                'https://images.unsplash.com/photo-1618773928121-c32242e63f39?w=800',
                'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=800',
            ],
            'Phòng Superior Twin' => [
                'https://images.unsplash.com/photo-1595576508896-5b3a0b8b8c8c?w=800',
                'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=800',
                'https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=800',
            ],
            'Phòng Deluxe Ocean View' => [
                'https://images.unsplash.com/photo-1596394516093-501ba68a0ba6?w=800',
                'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?w=800',
                'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800',
            ],
            'Phòng Triple' => [
                'https://images.unsplash.com/photo-1618221195710-dd6b41faaea8?w=800',
                'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=800',
                'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=800',
            ],
            'Phòng Family Deluxe' => [
                'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800',
                'https://images.unsplash.com/photo-1618221195710-dd6b41faaea8?w=800',
                'https://images.unsplash.com/photo-1595576508896-5b3a0b8b8c8c?w=800',
            ],
            'Phòng Family Suite' => [
                'https://images.unsplash.com/photo-1591088398332-8a7791972843?w=800',
                'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=800',
                'https://images.unsplash.com/photo-1618221195710-dd6b41faaea8?w=800',
            ],
            'Phòng Group Room' => [
                'https://images.unsplash.com/photo-1595576508896-5b3a0b8b8c8c?w=800',
                'https://images.unsplash.com/photo-1618773928121-c32242e63f39?w=800',
                'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=800',
            ],
            'Villa Garden View' => [
                'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=800',
                'https://images.unsplash.com/photo-1613490493576-7fde63acd811?w=800',
                'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=800',
            ],
            'Villa Beach Front' => [
                'https://images.unsplash.com/photo-1499793983690-e29da59ef1c2?w=800',
                'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800',
                'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=800',
            ],

            // Homestay Room Types
            'Phòng Đơn Cozy' => [
                'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?w=800',
                'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=800',
            ],
            'Phòng Đôi Romantic' => [
                'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=800',
                'https://images.unsplash.com/photo-1515362778563-6a8d0e44bc0b?w=800',
                'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=800',
            ],
            'Phòng Twin Pine' => [
                'https://images.unsplash.com/photo-1595576508896-5b3a0b8b8c8c?w=800',
                'https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=800',
            ],
            'Phòng Gia Đình Sunflower' => [
                'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800',
                'https://images.unsplash.com/photo-1618221195710-dd6b41faaea8?w=800',
            ],
            'Phòng Gác Mái Attic' => [
                'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=800',
                'https://images.unsplash.com/photo-1515362778563-6a8d0e44bc0b?w=800',
                'https://images.unsplash.com/photo-1523755231516-e43fd2e8dca5?w=800',
            ],
            'Căn Hộ Studio Forest' => [
                'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=800',
                'https://images.unsplash.com/photo-1556912172-45b7abe8b7e1?w=800',
                'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=800',
            ],
        ];

        $totalCreated = 0;

        foreach ($roomTypes as $roomType) {
            $images = $sampleImages[$roomType->name] ?? [];
            
            // Nếu có image_url trong room_type, thêm vào đầu danh sách
            if ($roomType->image_url) {
                array_unshift($images, $roomType->image_url);
            }

            // Nếu không có ảnh nào, dùng ảnh mặc định
            if (empty($images)) {
                $images = [
                    'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=800',
                    'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=800',
                ];
            }

            // Tạo ảnh cho room type
            foreach ($images as $index => $imageUrl) {
                RoomTypeImage::create([
                    'room_type_id' => $roomType->id,
                    'image_url' => $imageUrl,
                    'is_primary' => $index === 0, // Ảnh đầu tiên là primary
                ]);
                $totalCreated++;
            }
        }

        $this->command->info("✅ Created {$totalCreated} room type images");
    }
}
