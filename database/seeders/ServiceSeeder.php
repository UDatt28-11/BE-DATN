<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Property;
use App\Models\Service;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $properties = Property::all();
        
        if ($properties->isEmpty()) {
            $this->command->warn('⚠️  No properties found. Skipping services creation.');
            return;
        }

        $services = [
            ['name' => 'Ăn sáng', 'unit' => 'per_person', 'price' => 50000],
            ['name' => 'Giặt là', 'unit' => 'per_item', 'price' => 30000],
            ['name' => 'Xe đưa đón sân bay', 'unit' => 'per_booking', 'price' => 300000],
            ['name' => 'Thuê xe máy', 'unit' => 'per_day', 'price' => 150000],
            ['name' => 'Massage', 'unit' => 'per_item', 'price' => 200000],
            ['name' => 'Tour du lịch', 'unit' => 'per_person', 'price' => 500000],
        ];

        foreach ($properties as $property) {
            // Mỗi property có 4-6 services
            $selectedServices = array_rand($services, rand(4, 6));
            
            foreach ($selectedServices as $index) {
                // Kiểm tra xem service đã tồn tại chưa
                $exists = \Illuminate\Support\Facades\DB::table('services')
                    ->where('property_id', $property->id)
                    ->where('name', $services[$index]['name'])
                    ->exists();
                
                if (!$exists) {
                    \Illuminate\Support\Facades\DB::table('services')->insert([
                        'property_id' => $property->id,
                        'name' => $services[$index]['name'],
                        'price' => $services[$index]['price'],
                        'unit' => $services[$index]['unit'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('✅ Created services for all properties');
    }
}

