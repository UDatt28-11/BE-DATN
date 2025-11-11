<?php
// app/Http/Resources/AmenityResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="Amenity",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Wi-Fi miễn phí"),
 *     @OA\Property(property="type", type="string", enum={"basic", "advanced", "safety"}, example="basic"),
 *     @OA\Property(property="icon_url", type="string", nullable=true, example="http://example.com/storage/amenity_icons/icon.png"),
 *     @OA\Property(property="property", type="object",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Sunset Homestay")
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-11-01 12:00:00"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-11-01 12:00:00")
 * )
 */
class AmenityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'icon_url' => $this->icon_url,
            'property' => $this->whenLoaded('property', function () {
                return [
                    'id' => $this->property->id,
                    'name' => $this->property->name,
                ];
            }, null),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
