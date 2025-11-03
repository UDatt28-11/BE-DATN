<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplySeeder extends Seeder
{
    public function run(): void
    {
        $supplies = [
            [
                'name' => 'Khăn tắm',
                'description' => 'Khăn tắm cao cấp',
                'category' => 'Dụng cụ vệ sinh',
                'unit' => 'Chiếc',
                'current_stock' => 50,
                'min_stock_level' => 10,
                'max_stock_level' => 100,
                'unit_price' => 50000.00,
                'supplier' => 'Công ty Textiles XYZ',
                'supplier_contact' => '0123456789',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Nước rửa bát',
                'description' => 'Nước rửa bát chuyên dụng',
                'category' => 'Hóa chất làm sạch',
                'unit' => 'Chai',
                'current_stock' => 30,
                'min_stock_level' => 5,
                'max_stock_level' => 50,
                'unit_price' => 25000.00,
                'supplier' => 'Công ty Hóa chất ABC',
                'supplier_contact' => '0987654321',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Dầu gội đầu',
                'description' => 'Dầu gội đầu cao cấp',
                'category' => 'Sản phẩm cá nhân',
                'unit' => 'Chai',
                'current_stock' => 25,
                'min_stock_level' => 5,
                'max_stock_level' => 40,
                'unit_price' => 35000.00,
                'supplier' => 'Công ty Mỹ phẩm 123',
                'supplier_contact' => '0912345678',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Sữa tắm',
                'description' => 'Sữa tắm dịu nhẹ',
                'category' => 'Sản phẩm cá nhân',
                'unit' => 'Chai',
                'current_stock' => 20,
                'min_stock_level' => 5,
                'max_stock_level' => 35,
                'unit_price' => 30000.00,
                'supplier' => 'Công ty Mỹ phẩm 123',
                'supplier_contact' => '0912345678',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Giấy vệ sinh',
                'description' => 'Giấy vệ sinh chất lượng cao',
                'category' => 'Dụng cụ vệ sinh',
                'unit' => 'Cuộn',
                'current_stock' => 100,
                'min_stock_level' => 20,
                'max_stock_level' => 150,
                'unit_price' => 12000.00,
                'supplier' => 'Công ty Giấy DEF',
                'supplier_contact' => '0834567890',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bàn chải đánh răng',
                'description' => 'Bàn chải đánh răng cao cấp',
                'category' => 'Sản phẩm cá nhân',
                'unit' => 'Chiếc',
                'current_stock' => 40,
                'min_stock_level' => 10,
                'max_stock_level' => 60,
                'unit_price' => 15000.00,
                'supplier' => 'Công ty Dụng cụ GHI',
                'supplier_contact' => '0945678901',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('supplies')->insert($supplies);
    }
}
