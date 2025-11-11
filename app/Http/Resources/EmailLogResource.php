<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmailLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'recipient_email' => $this->recipient_email,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'sent_at' => $this->sent_at?->format('Y-m-d H:i:s'),
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'template' => $this->whenLoaded('template', function () {
                return [
                    'id' => $this->template->id,
                    'name' => $this->template->name,
                ];
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

