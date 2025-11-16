<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $bookingOrders = DB::table('booking_orders')->get();

        if ($bookingOrders->isEmpty()) {
            $this->command->warn('Không có booking orders để tạo hóa đơn. Vui lòng chạy BookingOrderSeeder trước.');
            return;
        }

        $invoiceCount = 0;

        foreach ($bookingOrders->take(4) as $index => $booking) {
            $subtotal = [3000000, 5000000, 2500000, 1800000][$index] ?? 2000000;
            $taxRate = 10;
            $taxAmount = ($subtotal * $taxRate) / 100;
            $totalAmount = $subtotal + $taxAmount;
            $status = ['paid', 'pending', 'pending', 'cancelled'][$index] ?? 'pending';
            $paymentStatus = $status === 'paid' ? 'paid' : ($status === 'cancelled' ? 'cancelled' : 'pending');
            $invoiceStatus = $status === 'paid' ? 'paid' : ($status === 'cancelled' ? 'cancelled' : 'sent');
            $paidAmount = $status === 'paid' ? $totalAmount : 0;
            $balance = $totalAmount - $paidAmount;

            $invoiceId = DB::table('invoices')->insertGetId([
                'property_id' => $booking->property_id ?? null,
                'booking_order_id' => $booking->id,
                'invoice_number' => 'INV-' . strtoupper(Str::random(8)),
                'issue_date' => now()->subDays(10 - $index * 2)->toDateString(),
                'due_date' => now()->subDays(7 - $index * 2)->toDateString(),
                'customer_name' => $booking->customer_name ?? 'Khách hàng ' . ($index + 1),
                'customer_email' => $booking->customer_email ?? null,
                'customer_phone' => $booking->customer_phone ?? null,
                'customer_address' => null,
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'discount_amount' => 0,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'balance' => $balance,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'invoice_status' => $invoiceStatus,
                'payment_method' => $status === 'paid' ? 'cash' : null,
                'payment_date' => $status === 'paid' ? now()->subDays(8 - $index * 2)->toDateString() : null,
                'payment_notes' => null,
                'notes' => null,
                'terms_conditions' => null,
                'refund_amount' => 0,
                'refund_policy_id' => null,
                'refund_date' => null,
                'calculation_method' => 'automatic',
                'created_at' => now()->subDays(10 - $index * 2),
                'updated_at' => now()->subDays(8 - $index * 2),
            ]);

            // Tạo invoice items mẫu
            $itemSubtotal = $subtotal / 2; // Chia làm 2 items
            $itemTaxAmount = ($itemSubtotal * $taxRate) / 100;
            $itemTotal = $itemSubtotal + $itemTaxAmount;

            // Item 1: Phòng
            DB::table('invoice_items')->insert([
                'invoice_id' => $invoiceId,
                'description' => 'Phòng Standard - 2 đêm',
                'quantity' => 2,
                'unit_price' => $itemSubtotal / 2,
                'tax_rate' => $taxRate,
                'tax_amount' => $itemTaxAmount,
                'amount' => $itemSubtotal,
                'total_line' => $itemTotal,
                'total' => $itemTotal,
                'item_type' => 'room_charge',
                'booking_detail_id' => null,
                'room_id' => null,
                'service_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Item 2: Dịch vụ
            DB::table('invoice_items')->insert([
                'invoice_id' => $invoiceId,
                'description' => 'Dịch vụ bổ sung',
                'quantity' => 1,
                'unit_price' => $itemSubtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $itemTaxAmount,
                'amount' => $itemSubtotal,
                'total_line' => $itemTotal,
                'total' => $itemTotal,
                'item_type' => 'service_charge',
                'booking_detail_id' => null,
                'room_id' => null,
                'service_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $invoiceCount++;
        }

        $this->command->info("Đã tạo {$invoiceCount} hóa đơn mẫu với các field mới.");
    }
}
