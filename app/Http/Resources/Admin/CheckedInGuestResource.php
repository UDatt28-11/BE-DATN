<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckedInGuestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'date_of_birth' => $this->date_of_birth ? $this->date_of_birth->format('Y-m-d') : null,
            'identity_type' => $this->identity_type,
            'identity_number' => $this->identity_number,
            'identity_image_url' => $this->identity_image_url,
            'check_in_time' => $this->check_in_time ? $this->check_in_time->toISOString() : null,
        ];
    }
}


