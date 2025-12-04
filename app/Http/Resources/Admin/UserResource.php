<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'avatar_url' => $this->avatar_url,
            'date_of_birth' => $this->date_of_birth ? (is_string($this->date_of_birth) ? $this->date_of_birth : $this->date_of_birth->format('Y-m-d')) : null,
            'gender' => $this->gender,
            'address' => $this->address,
            'status' => $this->status,
            'role' => $this->role,
            'preferred_language' => $this->preferred_language,
            'identity_verified' => (bool) $this->identity_verified,
            'identity_type' => $this->identity_type,
            'identity_number' => $this->identity_number,
            'identity_image_url' => $this->identity_image_url,
            'verified_at' => $this->verified_at ? $this->verified_at->toISOString() : null,
            'verifier' => $this->whenLoaded('verifier', function () {
                return [
                    'id' => $this->verifier->id,
                    'full_name' => $this->verifier->full_name,
                ];
            }),
            'email_verified_at' => $this->email_verified_at ? $this->email_verified_at->toISOString() : null,
            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toISOString() : null,
        ];
    }
}
