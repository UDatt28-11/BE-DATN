<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'room_name' => optional($this->room)->name,
            'check_in_date' => optional($this->check_in_date)->format('Y-m-d'),
            'check_out_date' => optional($this->check_out_date)->format('Y-m-d'),
            'num_adults' => (int) $this->num_adults,
            'num_children' => (int) $this->num_children,
            'sub_total' => (int) round($this->sub_total),
            'status' => $this->status,
            'guests' => $this->whenLoaded('guests', fn () => CheckedInGuestResource::collection($this->guests)),
        ];
    }
}


