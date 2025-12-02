<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Supply;

class SupplySeeder extends Seeder
{
    public function run(): void
    {
        // Xóa supplies cũ
        Supply::query()->delete();

        // Tạo danh sách supplies sạch và thực tế (không gắn với room cụ thể)
        $supplies = [
            [
                'name' => 'Khăn tắm lớn',
                'category' => 'Vật dụng phòng tắm',
                'unit' => 'cái',
                'current_stock' => 50,
                'min_stock_level' => 20,
                'max_stock_level' => 100,
                'unit_price' => 150000,
            ],
            [
                'name' => 'Khăn tắm nhỏ',
                'category' => 'Vật dụng phòng tắm',
                'unit' => 'cái',
                'current_stock' => 50,
                'min_stock_level' => 20,
                'max_stock_level' => 100,
                'unit_price' => 80000,
            ],
            [
                'name' => 'Chăn ga gối',
                'category' => 'Đồ dùng giường',
                'unit' => 'bộ',
                'current_stock' => 30,
                'min_stock_level' => 10,
                'max_stock_level' => 50,
                'unit_price' => 500000,
            ],
            [
                'name' => 'Dầu gội đầu',
                'category' => 'Vật dụng phòng tắm',
                'unit' => 'chai',
                'current_stock' => 40,
                'min_stock_level' => 15,
                'max_stock_level' => 80,
                'unit_price' => 120000,
            ],
            [
                'name' => 'Sữa tắm',
                'category' => 'Vật dụng phòng tắm',
                'unit' => 'chai',
                'current_stock' => 40,
                'min_stock_level' => 15,
                'max_stock_level' => 80,
                'unit_price' => 120000,
            ],
            [
                'name' => 'Bàn chải đánh răng',
                'category' => 'Vật dụng phòng tắm',
                'unit' => 'cái',
                'current_stock' => 60,
                'min_stock_level' => 25,
                'max_stock_level' => 120,
                'unit_price' => 25000,
            ],
            [
                'name' => 'Kem đánh răng',
                'category' => 'Vật dụng phòng tắm',
                'unit' => 'tuýp',
                'current_stock' => 50,
                'min_stock_level' => 20,
                'max_stock_level' => 100,
                'unit_price' => 45000,
            ],
            [
                'name' => 'Nước uống đóng chai',
                'category' => 'Đồ uống',
                'unit' => 'chai',
                'current_stock' => 200,
                'min_stock_level' => 100,
                'max_stock_level' => 500,
                'unit_price' => 10000,
            ],
            [
                'name' => 'Cà phê hòa tan',
                'category' => 'Đồ uống',
                'unit' => 'gói',
                'current_stock' => 150,
                'min_stock_level' => 50,
                'max_stock_level' => 300,
                'unit_price' => 5000,
            ],
            [
                'name' => 'Trà túi lọc',
                'category' => 'Đồ uống',
                'unit' => 'gói',
                'current_stock' => 150,
                'min_stock_level' => 50,
                'max_stock_level' => 300,
                'unit_price' => 3000,
            ],
        ];

        foreach ($supplies as $supplyData) {
            Supply::create($supplyData);
        }

        $this->command->info('✅ Created ' . count($supplies) . ' supplies');
    }
}

