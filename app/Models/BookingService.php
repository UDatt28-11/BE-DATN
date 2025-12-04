<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingService extends Model
{
    protected $fillable = [
        'booking_details_id',
        'service_id',
        'quantity',
        'price_at_booking',
    ];

    protected $casts = [
        'price_at_booking' => 'decimal:2',
    ];

    // Relationships
    public function bookingDetail(): BelongsTo
    {
        return $this->belongsTo(BookingDetail::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
