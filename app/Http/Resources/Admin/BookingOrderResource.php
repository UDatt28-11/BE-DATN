<?php
// app/Http/Resources/Admin/BookingOrderResource.php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Lấy thông tin customer từ booking_order hoặc từ guest (user)
        $customerName = $this->customer_name ?? $this->guest?->full_name ?? null;
        $customerPhone = $this->customer_phone ?? $this->guest?->phone_number ?? null;
        $customerEmail = $this->customer_email ?? $this->guest?->email ?? null;

        // Sử dụng checkin/checkout dates từ query nếu có (từ QueryService)
        // Nếu không có, fallback về tính từ details collection
        $firstCheckin = $this->details_min_check_in_date ?? null;
        $lastCheckout = $this->details_max_check_out_date ?? null;
        
        // Chỉ tính từ details nếu details đã được load và không có giá trị từ query
        if (!$firstCheckin && $this->relationLoaded('details') && $this->details->isNotEmpty()) {
            $firstCheckin = $this->details->min('check_in_date');
        }
        if (!$lastCheckout && $this->relationLoaded('details') && $this->details->isNotEmpty()) {
            $lastCheckout = $this->details->max('check_out_date');
        }

        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'code' => $this->order_code, // Alias để tương thích với frontend
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'customer_email' => $customerEmail,
            'total_amount' => (int) round($this->total_amount ?? 0),
            'deposit_amount' => $this->deposit_amount ? (int) round($this->deposit_amount) : null,
            'paid_amount' => $this->paid_amount ? (int) round($this->paid_amount) : 0,
            'payment_status' => $this->payment_status ?? 'unpaid',
            'remaining_amount' => $this->calculateRemainingAmount(),
            'payment_method' => $this->payment_method ?? null,
            'notes' => $this->notes ?? null,
            'status' => $this->status,
            // Thêm checkin/checkout dates từ query results
            'checkin_date' => $firstCheckin ? (is_string($firstCheckin) ? $firstCheckin : (\Carbon\Carbon::parse($firstCheckin)->format('Y-m-d'))) : null,
            'checkout_date' => $lastCheckout ? (is_string($lastCheckout) ? $lastCheckout : (\Carbon\Carbon::parse($lastCheckout)->format('Y-m-d'))) : null,
            'details_count' => (int) ($this->details_count ?? ($this->relationLoaded('details') ? $this->details->count() : 0)),
            'guest' => $this->whenLoaded('guest', fn() => [
                'id' => $this->guest->id,
                'full_name' => $this->guest->full_name,
                'email' => $this->guest->email,
                'phone_number' => $this->guest->phone_number,
            ]),
            'details' => $this->whenLoaded('details', function() {
                if (!$this->details || $this->details->isEmpty()) {
                    return [];
                }
                return $this->details->map(function($detail) {
                    $room = null;
                    if ($detail->relationLoaded('room') && $detail->room) {
                        $room = [
                            'id' => $detail->room->id,
                            'name' => $detail->room->name,
                            'room_type' => $detail->room->relationLoaded('roomType') && $detail->room->roomType 
                                ? $detail->room->roomType->name 
                                : null,
                            'property' => $detail->room->relationLoaded('property') && $detail->room->property 
                                ? $detail->room->property->name 
                                : null,
                        ];
                        
                        // Thêm images nếu đã được load
                        if ($detail->room->relationLoaded('images')) {
                            $room['images'] = $detail->room->images->map(function($image) {
                                return [
                                    'id' => $image->id,
                                    'url' => $image->image_url ?? $image->web_view_link ?? null,
                                    'is_primary' => $image->is_primary ?? false,
                                ];
                            })->values()->all();
                        }
                    }
                    
                    return [
                        'id' => $detail->id,
                        'room' => $room,
                        'check_in_date' => $detail->check_in_date?->format('Y-m-d'),
                        'check_out_date' => $detail->check_out_date?->format('Y-m-d'),
                        'num_adults' => $detail->num_adults,
                        'num_children' => $detail->num_children,
                        'sub_total' => $detail->sub_total,
                        'status' => $detail->status,
                    ];
                });
            }),
            'created_at' => $this->created_at?->toISOString() ?? $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->toISOString() ?? $this->updated_at?->format('Y-m-d H:i:s'),
            'check_in_requests' => $this->whenLoaded('checkInRequests', function() {
                if (!$this->checkInRequests || $this->checkInRequests->isEmpty()) {
                    return [];
                }
                return $this->checkInRequests->map(function($request) {
                    return [
                        'id' => $request->id,
                        'booking_order_id' => $request->booking_order_id,
                        'booking_detail_id' => $request->booking_detail_id,
                        'full_name' => $request->full_name,
                        'date_of_birth' => $request->date_of_birth?->format('Y-m-d'),
                        'identity_type' => $request->identity_type,
                        'identity_number' => $request->identity_number,
                        'identity_image_url' => $request->identity_image_url,
                        'status' => $request->status,
                        'rejection_reason' => $request->rejection_reason,
                        'reviewed_by' => $request->reviewed_by,
                        'reviewed_at' => $request->reviewed_at?->toISOString(),
                        'notes' => $request->notes,
                        'created_at' => $request->created_at?->toISOString(),
                        'updated_at' => $request->updated_at?->toISOString(),
                    ];
                });
            }),
            'invoices' => $this->whenLoaded('invoices', function() {
                if (!$this->invoices || $this->invoices->isEmpty()) {
                    return [];
                }
                return $this->invoices->map(function($invoice) {
                    // Tính tổng tiền đã thanh toán từ payments
                    $paidAmount = 0;
                    if ($invoice->relationLoaded('payments')) {
                        $paidAmount = $invoice->payments
                            ->where('status', 'success')
                            ->sum('amount');
                    }
                    
                    return [
                        'id' => $invoice->id,
                        'total_amount' => (int) round($invoice->total_amount ?? 0),
                        'status' => $invoice->status ?? 'pending',
                        'issue_date' => $invoice->issue_date?->format('Y-m-d'),
                        'due_date' => $invoice->due_date?->format('Y-m-d'),
                        'paid_amount' => (int) round($paidAmount),
                        'remaining_amount' => (int) round(max(0, ($invoice->total_amount ?? 0) - $paidAmount)),
                        'payments' => $invoice->whenLoaded('payments', function() use ($invoice) {
                            return $invoice->payments->map(function($payment) {
                                return [
                                    'id' => $payment->id,
                                    'amount' => (int) round($payment->amount ?? 0),
                                    'status' => $payment->status ?? 'pending',
                                    'payment_method' => $payment->payment_method ?? null,
                                    'paid_at' => $payment->paid_at?->toISOString(),
                                ];
                            });
                        }),
                    ];
                });
            }),
        ];
    }

    /**
     * Tính số tiền còn phải thanh toán
     * Ưu tiên tính từ invoice nếu có, nếu không thì tính từ booking
     */
    private function calculateRemainingAmount(): int
    {
        // Nếu có invoice, tính dựa trên invoice
        if ($this->relationLoaded('invoices') && $this->invoices->isNotEmpty()) {
            $invoice = $this->invoices->first();
            $invoiceTotal = $invoice->total_amount ?? 0;
            
            // Tính tổng tiền đã thanh toán từ payments
            $paidAmount = 0;
            if ($invoice->relationLoaded('payments')) {
                $paidAmount = $invoice->payments
                    ->where('status', 'success')
                    ->sum('amount');
            }
            
            return (int) round(max(0, $invoiceTotal - $paidAmount));
        }
        
        // Nếu chưa có invoice, tính dựa trên booking
        $bookingTotal = $this->total_amount ?? 0;
        $bookingPaid = $this->paid_amount ?? 0;
        return (int) round(max(0, $bookingTotal - $bookingPaid));
    }
}
