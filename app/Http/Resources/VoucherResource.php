<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'code' => $this->code,
            'name' => $this->name ?? $this->code,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'discount_text' => $this->discount_text ?? null,
            'min_order_amount' => (float) ($this->min_order_amount ?? 0),
            'max_discount_amount' => $this->max_discount_amount ? (float) $this->max_discount_amount : null,
            'usage_limit' => $this->usage_limit,
            'usage_count' => (int) ($this->usage_count ?? 0),
            'max_usage_per_user' => (int) ($this->max_usage_per_user ?? 1),
            'start_date' => $this->start_date?->toISOString(),
            'end_date' => $this->end_date?->toISOString(),
            'is_active' => (bool) $this->is_active,
            'is_public' => (bool) ($this->is_public ?? true),
            'can_be_used' => $this->canBeUsed(),
            'remaining_uses' => $this->usage_limit 
                ? max(0, $this->usage_limit - ($this->usage_count ?? 0)) 
                : null,
            'property' => $this->whenLoaded('property', function () {
                return [
                    'id' => $this->property->id,
                    'name' => $this->property->name,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

