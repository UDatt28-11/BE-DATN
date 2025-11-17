<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\Supply;
use Illuminate\Database\Seeder;

class SupplySeeder extends Seeder
{
    public function run(): void
    {
        $rooms = Room::all();

        if ($rooms->isEmpty()) {
            $this->command->warn('Không có phòng nào. Vui lòng chạy RoomSeeder trước.');
            return;
        }

        $supplies = [
            // Vật tư phòng tắm
            [
                'name' => 'Khăn tắm cao cấp',
                'description' => 'Khăn tắm cotton 100%, kích thước 70x140cm',
                'category' => 'Phòng tắm',
                'unit' => 'cái',
                'current_stock' => 150,
                'min_stock_level' => 50,
                'max_stock_level' => 300,
                'unit_price' => 50000,
                'supplier' => 'Công ty TNHH Dệt May ABC',
                'supplier_contact' => '0901234567',
                'status' => 'active',
            ],
            [
                'name' => 'Dầu gội Head & Shoulders',
                'description' => 'Dầu gội trị gàu, chai 400ml',
                'category' => 'Phòng tắm',
                'unit' => 'chai',
                'current_stock' => 200,
                'min_stock_level' => 80,
                'max_stock_level' => 400,
                'unit_price' => 85000,
                'supplier' => 'P&G Việt Nam',
                'supplier_contact' => '0902345678',
                'status' => 'active',
            ],
            [
                'name' => 'Sữa tắm Dove',
                'description' => 'Sữa tắm dưỡng ẩm, chai 500ml',
                'category' => 'Phòng tắm',
                'unit' => 'chai',
                'current_stock' => 180,
                'min_stock_level' => 70,
                'max_stock_level' => 350,
                'unit_price' => 75000,
                'supplier' => 'Unilever Việt Nam',
                'supplier_contact' => '0903456789',
                'status' => 'active',
            ],
            
            // Vật tư giường ngủ
            [
                'name' => 'Bộ chăn ga gối Everon',
                'description' => 'Bộ chăn ga gối cotton cao cấp, size queen',
                'category' => 'Giường ngủ',
                'unit' => 'bộ',
                'current_stock' => 45,
                'min_stock_level' => 20,
                'max_stock_level' => 100,
                'unit_price' => 1200000,
                'supplier' => 'Everon Việt Nam',
                'supplier_contact' => '0904567890',
                'status' => 'active',
            ],
            [
                'name' => 'Gối nằm Memory Foam',
                'description' => 'Gối nằm chống oxy hóa, chống khuẩn',
                'category' => 'Giường ngủ',
                'unit' => 'cái',
                'current_stock' => 80,
                'min_stock_level' => 30,
                'max_stock_level' => 150,
                'unit_price' => 350000,
                'supplier' => 'Hanvico',
                'supplier_contact' => '0905678901',
                'status' => 'active',
            ],
            
            // Vật tư vệ sinh
            [
                'name' => 'Giấy vệ sinh Pulppy',
                'description' => 'Giấy vệ sinh 3 lớp, cuộn 300m',
                'category' => 'Vệ sinh',
                'unit' => 'cuộn',
                'current_stock' => 25,
                'min_stock_level' => 100,
                'max_stock_level' => 500,
                'unit_price' => 35000,
                'supplier' => 'SAPP Việt Nam',
                'supplier_contact' => '0906789012',
                'status' => 'active',
            ],
            [
                'name' => 'Nước lau sàn Sunlight',
                'description' => 'Nước lau sàn diệt khuẩn, chai 1L',
                'category' => 'Vệ sinh',
                'unit' => 'chai',
                'current_stock' => 60,
                'min_stock_level' => 30,
                'max_stock_level' => 150,
                'unit_price' => 45000,
                'supplier' => 'Sunlight VN',
                'supplier_contact' => '0907890123',
                'status' => 'active',
            ],
            
            // Vật tư mini bar
            [
                'name' => 'Nước suối Lavie',
                'description' => 'Nước khoáng thiên nhiên, chai 500ml',
                'category' => 'Mini Bar',
                'unit' => 'chai',
                'current_stock' => 500,
                'min_stock_level' => 200,
                'max_stock_level' => 1000,
                'unit_price' => 5000,
                'supplier' => 'Lavie',
                'supplier_contact' => '0908901234',
                'status' => 'active',
            ],
            [
                'name' => 'Cà phê G7 3in1',
                'description' => 'Cà phê hòa tan, gói 16g',
                'category' => 'Mini Bar',
                'unit' => 'gói',
                'current_stock' => 300,
                'min_stock_level' => 150,
                'max_stock_level' => 600,
                'unit_price' => 3500,
                'supplier' => 'Trung Nguyên',
                'supplier_contact' => '0909012345',
                'status' => 'active',
            ],
            [
                'name' => 'Trà Lipton túi lọc',
                'description' => 'Trà đen Lipton, hộp 20 túi',
                'category' => 'Mini Bar',
                'unit' => 'hộp',
                'current_stock' => 100,
                'min_stock_level' => 50,
                'max_stock_level' => 200,
                'unit_price' => 45000,
                'supplier' => 'Unilever',
                'supplier_contact' => '0910123456',
                'status' => 'active',
            ],
            
            // Vật tư điện tử
            [
                'name' => 'Remote điều hòa',
                'description' => 'Remote điều khiển máy lạnh đa năng',
                'category' => 'Điện tử',
                'unit' => 'cái',
                'current_stock' => 30,
                'min_stock_level' => 15,
                'max_stock_level' => 50,
                'unit_price' => 120000,
                'supplier' => 'Điện máy Xanh',
                'supplier_contact' => '0911234567',
                'status' => 'active',
            ],
            [
                'name' => 'Bóng đèn LED 9W',
                'description' => 'Bóng đèn LED tiết kiệm điện',
                'category' => 'Điện tử',
                'unit' => 'bóng',
                'current_stock' => 80,
                'min_stock_level' => 40,
                'max_stock_level' => 150,
                'unit_price' => 45000,
                'supplier' => 'Điện Quang',
                'supplier_contact' => '0912345678',
                'status' => 'active',
            ],
            
            // Vật tư khác
            [
                'name' => 'Dép đi trong phòng',
                'description' => 'Dép đi trong phòng, size free',
                'category' => 'Tiện ích',
                'unit' => 'đôi',
                'current_stock' => 120,
                'min_stock_level' => 60,
                'max_stock_level' => 250,
                'unit_price' => 25000,
                'supplier' => 'Biti\'s',
                'supplier_contact' => '0913456789',
                'status' => 'active',
            ],
            [
                'name' => 'Kem đánh răng Close Up',
                'description' => 'Kem đánh răng bạc hà, tuýp 150g',
                'category' => 'Phòng tắm',
                'unit' => 'tuýp',
                'current_stock' => 5,
                'min_stock_level' => 50,
                'max_stock_level' => 200,
                'unit_price' => 35000,
                'supplier' => 'Unilever',
                'supplier_contact' => '0914567890',
                'status' => 'active',
            ],
        ];

        // Tạo vật tư cho mỗi phòng
        $totalCreated = 0;
        foreach ($rooms as $room) {
            foreach ($supplies as $supplyData) {
                Supply::create([
                    'room_id' => $room->id,
                    ...$supplyData
                ]);
                $totalCreated++;
            }
        }

        $this->command->info('✅ Đã tạo ' . $totalCreated . ' vật tư cho ' . $rooms->count() . ' phòng');
    }
}
