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
        $firstCheckin = $this->details_min_check_in_date ?? $this->details->min('check_in_date');
        $lastCheckout = $this->details_max_check_out_date ?? $this->details->max('check_out_date');

        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'code' => $this->order_code, // Alias để tương thích với frontend
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'customer_email' => $customerEmail,
            'total_amount' => (int) round($this->total_amount ?? 0),
            'payment_method' => $this->payment_method ?? null,
            'notes' => $this->notes ?? null,
            'status' => $this->status,
            // Thêm checkin/checkout dates từ query results
            'checkin_date' => $firstCheckin ? (is_string($firstCheckin) ? $firstCheckin : (new \Carbon\Carbon($firstCheckin))->format('Y-m-d')) : null,
            'checkout_date' => $lastCheckout ? (is_string($lastCheckout) ? $lastCheckout : (new \Carbon\Carbon($lastCheckout))->format('Y-m-d')) : null,
            'details_count' => (int) ($this->details_count ?? $this->details()->count()),
            'guest' => $this->whenLoaded('guest', fn() => [
                'id' => $this->guest->id,
                'full_name' => $this->guest->full_name,
                'email' => $this->guest->email,
                'phone_number' => $this->guest->phone_number,
            ]),
            'details' => $this->whenLoaded('details', function() {
                return $this->details->map(function($detail) {
                    return [
                        'id' => $detail->id,
                        'room' => $detail->relationLoaded('room') && $detail->room ? [
                            'id' => $detail->room->id,
                            'name' => $detail->room->name,
                            'room_type' => $detail->room->relationLoaded('roomType') && $detail->room->roomType 
                                ? $detail->room->roomType->name 
                                : null,
                            'property' => $detail->room->relationLoaded('property') && $detail->room->property 
                                ? $detail->room->property->name 
                                : null,
                        ] : null,
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
        ];
    }
}
