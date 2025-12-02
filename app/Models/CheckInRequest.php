<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckInRequest extends Model
{
    protected $fillable = [
        'booking_order_id',
        'booking_detail_id',
        'full_name',
        'date_of_birth',
        'identity_type',
        'identity_number',
        'identity_image_url',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function bookingOrder(): BelongsTo
    {
        return $this->belongsTo(BookingOrder::class);
    }

    public function bookingDetail(): BelongsTo
    {
        return $this->belongsTo(BookingDetail::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
