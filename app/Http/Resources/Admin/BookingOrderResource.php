<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $firstCheckin = $this->details_min_check_in_date ?? $this->details->min('check_in_date');
        $lastCheckout = $this->details_max_check_out_date ?? $this->details->max('check_out_date');

        return [
            'id' => $this->id,
            'code' => $this->order_code,
            'customer_name' => optional($this->guest)->full_name,
            'customer_phone' => optional($this->guest)->phone_number,
            'status' => $this->status,
            'total_amount' => (int) round($this->total_amount),
            'checkin_date' => $firstCheckin ? (new \Carbon\Carbon($firstCheckin))->format('Y-m-d') : null,
            'checkout_date' => $lastCheckout ? (new \Carbon\Carbon($lastCheckout))->format('Y-m-d') : null,
            'details_count' => (int) ($this->details_count ?? $this->details()->count()),
            'created_at' => $this->created_at?->toISOString(),
            'details' => $this->whenLoaded('details', fn () => BookingDetailResource::collection($this->details)),
        ];
    }
}




