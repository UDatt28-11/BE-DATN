<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailLog extends Model
{
    protected $fillable = [
        'template_id',
        'recipient_email',
        'subject',
        'body',
        'status',
        'error_message',
        'sent_at',
        'related_type',
        'related_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    // Relationships
    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByRecipient($query, $email)
    {
        return $query->where('recipient_email', $email);
    }

    public function scopeByRelated($query, $type, $id)
    {
        return $query->where('related_type', $type)->where('related_id', $id);
    }
}

