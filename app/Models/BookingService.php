<?php
// app/Models/BookingService.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingService extends Model
{
    protected $table = 'booking_services';

    protected $fillable = [
        'booking_details_id',
        'service_id',
        'quantity',
        'price_at_booking',
    ];

    protected $casts = [
        'price_at_booking' => 'decimal:2',
    ];

    public function detail(): BelongsTo
    {
        return $this->belongsTo(BookingDetail::class, 'booking_details_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
