<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type ?? null,
            'session_id' => $this->session_id ?? null,
            'context_data' => $this->context_data ?? null,
            'participants' => $this->whenLoaded('participants', function () {
                return $this->participants->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                        'avatar_url' => $user->avatar_url,
                    ];
                });
            }, []),
            'latest_message' => $this->whenLoaded('messages', function () {
                $latest = $this->messages->sortByDesc('created_at')->first();
                if (!$latest) {
                    return null;
                }
                return [
                    'id' => $latest->id,
                    'content' => $latest->content,
                    'sender_id' => $latest->sender_id,
                    'sender' => $latest->sender ? [
                        'id' => $latest->sender->id,
                        'full_name' => $latest->sender->full_name,
                        'email' => $latest->sender->email,
                        'avatar_url' => $latest->sender->avatar_url,
                    ] : null,
                    'message_type' => $latest->message_type ?? 'user',
                    'read_at' => $latest->read_at?->format('Y-m-d H:i:s'),
                    'created_at' => $latest->created_at?->format('Y-m-d H:i:s'),
                ];
            }, null),
            'unread_count' => $this->when(isset($this->unread_count), $this->unread_count, 0),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

