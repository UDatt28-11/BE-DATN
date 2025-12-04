<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'content' => $this->content,
            'read_at' => $this->read_at?->format('Y-m-d H:i:s'),
            'is_read' => $this->read_at !== null,
            'is_hidden' => (bool) $this->is_hidden,
            'sender' => $this->whenLoaded('sender', function () {
                return [
                    'id' => $this->sender->id,
                    'full_name' => $this->sender->full_name,
                    'email' => $this->sender->email,
                    'avatar_url' => $this->sender->avatar_url,
                ];
            }),
            'conversation' => $this->whenLoaded('conversation', function () {
                return [
                    'id' => $this->conversation->id,
                ];
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

