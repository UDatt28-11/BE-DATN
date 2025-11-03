<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class BookingOrder extends Model
{
    protected $fillable = [
        'guest_id',
        'order_code',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    // Relationships
    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_id');
    }

    public function invoice(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function bookingDetails(): HasMany
    {
        return $this->hasMany(BookingDetail::class);
    }

    public function checkedInGuests(): HasMany
    {
        return $this->hasMany(CheckedInGuest::class);
    }

    public function bookingServices(): HasManyThrough
    {
        return $this->hasManyThrough(
            BookingService::class,
            BookingDetail::class,
            'booking_order_id',  // Foreign key on booking_details table
            'booking_details_id', // Foreign key on booking_services table
            'id',                 // Local key on booking_orders table
            'id'                  // Local key on booking_details table
        );
    }
}
