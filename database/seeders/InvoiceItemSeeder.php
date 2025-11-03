<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoiceItemSeeder extends Seeder
{
    public function run(): void
    {
        $invoices = DB::table('invoices')->get();

        $invoiceItems = [
            // Invoice 1 - Room charges
            [
                'invoice_id' => $invoices[0]->id,
                'description' => 'Phòng Sapa Standard 101 - 2 đêm',
                'quantity' => 2,
                'unit_price' => 500000.00,
                'total_line' => 1000000.00,
                'item_type' => 'room_charge',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ],
            [
                'invoice_id' => $invoices[0]->id,
                'description' => 'Dịch vụ đón khách tại ga',
                'quantity' => 1,
                'unit_price' => 200000.00,
                'total_line' => 200000.00,
                'item_type' => 'service_charge',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ],
            [
                'invoice_id' => $invoices[0]->id,
                'description' => 'Bữa sáng buffet',
                'quantity' => 2,
                'unit_price' => 150000.00,
                'total_line' => 300000.00,
                'item_type' => 'service_charge',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ],

            // Invoice 2 - Room charges
            [
                'invoice_id' => $invoices[1]->id,
                'description' => 'Phòng Sapa Deluxe 201 - 2 đêm',
                'quantity' => 2,
                'unit_price' => 800000.00,
                'total_line' => 1600000.00,
                'item_type' => 'room_charge',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ],
            [
                'invoice_id' => $invoices[1]->id,
                'description' => 'Dịch vụ massage',
                'quantity' => 1,
                'unit_price' => 500000.00,
                'total_line' => 500000.00,
                'item_type' => 'service_charge',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ],
            [
                'invoice_id' => $invoices[1]->id,
                'description' => 'Phí phát sinh - Hủy tour',
                'quantity' => 1,
                'unit_price' => 300000.00,
                'total_line' => 300000.00,
                'item_type' => 'other',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ],

            // Invoice 3 - Room charges
            [
                'invoice_id' => $invoices[2]->id,
                'description' => 'Phòng Dalat Family 301 - 3 đêm',
                'quantity' => 3,
                'unit_price' => 1200000.00,
                'total_line' => 3600000.00,
                'item_type' => 'room_charge',
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ],

            // Invoice 4 - Room charges
            [
                'invoice_id' => $invoices[3]->id,
                'description' => 'Phòng Dalat Suite 401 - 1 đêm',
                'quantity' => 1,
                'unit_price' => 2000000.00,
                'total_line' => 2000000.00,
                'item_type' => 'room_charge',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ],
            [
                'invoice_id' => $invoices[3]->id,
                'description' => 'Dịch vụ spa cao cấp',
                'quantity' => 1,
                'unit_price' => 1000000.00,
                'total_line' => 1000000.00,
                'item_type' => 'service_charge',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ],

            // Invoice 5 - Room charges (cancelled)
            [
                'invoice_id' => $invoices[4]->id,
                'description' => 'Phòng PhuQuoc Ocean 101 - 3 đêm',
                'quantity' => 3,
                'unit_price' => 1500000.00,
                'total_line' => 4500000.00,
                'item_type' => 'room_charge',
                'created_at' => now()->subDays(7),
                'updated_at' => now()->subDays(7),
            ]
        ];

        DB::table('invoice_items')->insert($invoiceItems);
    }
}
