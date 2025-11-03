<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $bookingOrders = DB::table('booking_orders')->get();

        $invoices = [
            [
                'booking_order_id' => $bookingOrders[0]->id,
                'issue_date' => now()->subDays(5)->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'total_amount' => 1500000.00,
                'status' => 'paid',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ],
            [
                'booking_order_id' => $bookingOrders[1]->id,
                'issue_date' => now()->subDays(3)->toDateString(),
                'due_date' => now()->addDays(5)->toDateString(),
                'total_amount' => 2400000.00,
                'status' => 'pending',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ],
            [
                'booking_order_id' => $bookingOrders[2]->id,
                'issue_date' => now()->subDays(1)->toDateString(),
                'due_date' => now()->addDays(3)->toDateString(),
                'total_amount' => 3600000.00,
                'status' => 'pending',
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ],
            [
                'booking_order_id' => $bookingOrders[3]->id,
                'issue_date' => now()->subDays(10)->toDateString(),
                'due_date' => now()->subDays(7)->toDateString(),
                'total_amount' => 3000000.00,
                'status' => 'paid',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(8),
            ],
            [
                'booking_order_id' => $bookingOrders[4]->id,
                'issue_date' => now()->subDays(7)->toDateString(),
                'due_date' => now()->subDays(4)->toDateString(),
                'total_amount' => 4500000.00,
                'status' => 'cancelled',
                'created_at' => now()->subDays(7),
                'updated_at' => now()->subDays(6),
            ]
        ];

        DB::table('invoices')->insert($invoices);
    }
}
