<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class BookingOrder extends Model
{
    use HasFactory;

    protected $table = 'booking_orders';
    protected $fillable = [
        'guest_id',
        'order_code',
        'customer_name',
        'customer_phone',
        'customer_email',
        'total_amount',
        'payment_method',
        'notes',
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
        return $this->hasMany(BookingDetail::class, 'booking_order_id');
    }

    public function checkedInGuests(): HasManyThrough
    {
        return $this->hasManyThrough(
            CheckedInGuest::class,
            BookingDetail::class,
            'booking_order_id',      // Foreign key on booking_details
            'booking_details_id',    // Foreign key on checked_in_guests
            'id',                    // Local key on booking_orders
            'id'                     // Local key on booking_details
        );
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

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'promotion_usage', 'booking_order_id', 'promotion_id')
            ->withTimestamps()
            ->withPivot('applied_discount_amount');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
