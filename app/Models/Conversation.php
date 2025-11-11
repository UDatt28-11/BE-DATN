<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [];

    // Relationships
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants', 'conversation_id', 'user_id')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    // Helper Methods
    public function getOtherParticipant($userId)
    {
        return $this->participants()->where('user_id', '!=', $userId)->first();
    }

    public function getLatestMessage()
    {
        return $this->messages()->visible()->latest()->first();
    }

    public function getUnreadCount($userId)
    {
        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->visible() // Chỉ đếm messages chưa bị ẩn
            ->count();
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->visible()->latestOfMany();
    }
}

