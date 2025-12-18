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
        'actual_quantity',
        'price_at_booking',
        'actual_price',
        'status',
        'notes',
        'started_at',
        'completed_at',
        'staff_id',
    ];

    protected $casts = [
        'price_at_booking' => 'decimal:2',
        'actual_price' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function detail(): BelongsTo
    {
        return $this->belongsTo(BookingDetail::class, 'booking_details_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
