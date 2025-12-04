<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckedInGuest extends Model
{
    protected $fillable = [
        'booking_details_id',
        'full_name',
        'date_of_birth',
        'identity_type',
        'identity_number',
        'identity_image_url',
        'check_in_time',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'check_in_time' => 'datetime',
    ];

    // Relationships
    public function bookingDetail(): BelongsTo
    {
        return $this->belongsTo(BookingDetail::class, 'booking_details_id');
    }

    // Giữ lại để tương thích nếu có code cũ dùng
    public function bookingOrder(): BelongsTo
    {
        return $this->belongsTo(BookingOrder::class, 'booking_order_id');
    }
}
