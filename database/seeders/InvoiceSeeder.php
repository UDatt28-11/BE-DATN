<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\BookingOrder;
use App\Models\BookingDetail;
use Carbon\Carbon;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $bookingOrders = BookingOrder::with('details.room')->get();
        
        if ($bookingOrders->isEmpty()) {
            $this->command->warn('⚠️  No booking orders found. Skipping invoices creation.');
            return;
        }

        // Tạo invoices cho một số booking orders
        $selectedBookings = $bookingOrders->random(min(10, $bookingOrders->count()));
        
        foreach ($selectedBookings as $booking) {
            // Kiểm tra xem đã có invoice chưa
            if (Invoice::where('booking_order_id', $booking->id)->exists()) {
                continue;
            }

            $issueDate = Carbon::now()->subDays(rand(1, 30));
            $dueDate = $issueDate->copy()->addDays(rand(7, 14));
            
            $invoice = Invoice::create([
                'booking_order_id' => $booking->id,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'total_amount' => 0, // Sẽ được tính sau
                'status' => ['pending', 'paid', 'overdue'][rand(0, 2)],
            ]);

            // Tạo invoice items từ booking details
            $totalAmount = 0;
            foreach ($booking->details as $detail) {
                $nights = $detail->check_out_date->diffInDays($detail->check_in_date);
                if ($nights <= 0) $nights = 1;
                
                $roomPrice = $detail->room->price_per_night * $nights;
                
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => "Phòng {$detail->room->name} - {$nights} đêm",
                    'quantity' => 1,
                    'unit_price' => $detail->room->price_per_night,
                    'total_line' => $roomPrice,
                    'item_type' => 'room_charge',
                ]);
                
                $totalAmount += $roomPrice;
            }

            // Cập nhật total_amount
            $invoice->update(['total_amount' => $totalAmount]);
        }

        $this->command->info('✅ Created invoices for booking orders');
    }
}

