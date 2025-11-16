<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;
use App\Models\Supply;

class SupplySeeder extends Seeder
{
    public function run(): void
    {
        $rooms = Room::all();
        
        if ($rooms->isEmpty()) {
            $this->command->warn('⚠️  No rooms found. Skipping supplies creation.');
            return;
        }

        $supplies = [
            ['name' => 'Khăn tắm', 'category' => 'Vật dụng phòng tắm', 'unit' => 'cái'],
            ['name' => 'Chăn', 'category' => 'Đồ dùng giường', 'unit' => 'cái'],
            ['name' => 'Gối', 'category' => 'Đồ dùng giường', 'unit' => 'cái'],
            ['name' => 'Dầu gội', 'category' => 'Vật dụng phòng tắm', 'unit' => 'chai'],
            ['name' => 'Sữa tắm', 'category' => 'Vật dụng phòng tắm', 'unit' => 'chai'],
            ['name' => 'Bàn chải đánh răng', 'category' => 'Vật dụng phòng tắm', 'unit' => 'cái'],
            ['name' => 'Kem đánh răng', 'category' => 'Vật dụng phòng tắm', 'unit' => 'tuýp'],
            ['name' => 'Nước uống', 'category' => 'Đồ uống', 'unit' => 'chai'],
            ['name' => 'Cà phê', 'category' => 'Đồ uống', 'unit' => 'gói'],
            ['name' => 'Trà', 'category' => 'Đồ uống', 'unit' => 'gói'],
        ];

        // Tạo supplies cho một số phòng ngẫu nhiên
        $selectedRooms = $rooms->random(min(10, $rooms->count()));
        
        foreach ($selectedRooms as $room) {
            // Mỗi phòng có 5-8 supplies
            $selectedSupplies = array_rand($supplies, rand(5, 8));
            
            foreach ($selectedSupplies as $index) {
                Supply::create([
                    'room_id' => $room->id,
                    'name' => $supplies[$index]['name'],
                    'category' => $supplies[$index]['category'],
                    'unit' => $supplies[$index]['unit'],
                    'current_stock' => rand(10, 50),
                    'min_stock_level' => 5,
                    'max_stock_level' => 100,
                    'unit_price' => rand(10000, 100000),
                    'status' => 'active',
                ]);
            }
        }

        $this->command->info('✅ Created supplies for rooms');
    }
}

