<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class BookingOrderResource extends JsonResource
{
    /**
     * Get details array, always try to load if not loaded
     */
    protected function getDetailsArray()
    {
        // Thử lấy từ details relationship (alias)
        $details = $this->details ?? null;
        
        // Nếu không có, thử lấy từ bookingDetails
        if (!$details) {
            $details = $this->bookingDetails ?? null;
        }
        
        // Nếu vẫn không có và chưa được load, thử load lại
        if (!$details && !$this->relationLoaded('details') && !$this->relationLoaded('bookingDetails')) {
            $this->load('details');
            $details = $this->details ?? $this->bookingDetails ?? null;
        }
        
        // Trả về collection nếu có, nếu không trả về array rỗng
        if ($details && ($details->count() > 0 || (is_array($details) && count($details) > 0))) {
            return BookingDetailResource::collection($details);
        }
        
        return [];
    }

    public function toArray(Request $request): array
    {
        // Safely get check-in/check-out dates
        $firstCheckin = $this->details_min_check_in_date;
        $lastCheckout = $this->details_max_check_out_date;
        
        // Only access relationship if not already provided from aggregation
        if (!$firstCheckin && $this->relationLoaded('details')) {
            $firstCheckin = $this->details->min('check_in_date');
        }
        if (!$lastCheckout && $this->relationLoaded('details')) {
            $lastCheckout = $this->details->max('check_out_date');
        }

        return [
            'id' => $this->id,
            'code' => $this->order_code,
            'customer_name' => $this->customer_name ?? optional($this->guest)->full_name,
            'customer_phone' => $this->customer_phone ?? optional($this->guest)->phone_number,
            'customer_email' => $this->customer_email ?? optional($this->guest)->email,
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            'status' => $this->status,
            'total_amount' => (int) round($this->total_amount),
            'checkin_date' => $firstCheckin ? (new Carbon($firstCheckin))->format('Y-m-d') : null,
            'checkout_date' => $lastCheckout ? (new Carbon($lastCheckout))->format('Y-m-d') : null,
            'details_count' => (int) ($this->details_count ?? 0),
            'created_at' => $this->created_at?->toISOString(),
            'details' => $this->getDetailsArray(),
            'invoice' => $this->whenLoaded('invoice', function () {
                // Trả về danh sách hóa đơn đơn giản (id, số HĐ, trạng thái)
                if ($this->invoice && $this->invoice->isNotEmpty()) {
                    return $this->invoice->map(function ($inv) {
                        return [
                            'id' => $inv->id,
                            'invoice_number' => $inv->invoice_number ?? null,
                            'payment_status' => $inv->payment_status ?? null,
                            'total_amount' => isset($inv->total_amount) ? (int) round($inv->total_amount) : null,
                        ];
                    });
                }
                return [];
            }),
        ];
    }
}


