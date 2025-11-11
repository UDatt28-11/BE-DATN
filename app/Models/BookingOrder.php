<?php
// app/Models/BookingOrder.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BookingOrder extends Model
{
    protected $table = 'booking_orders';

    protected $fillable = [
        'guest_id',
        'order_code',
        'total_amount',
        'status',
        'customer_name',
        'customer_phone',
        'customer_email',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    // === RELATIONSHIPS ===

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'booking_order_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(BookingDetail::class, 'booking_order_id');
    }

    public function checkedInGuests(): HasManyThrough
    {
        return $this->hasManyThrough(
            CheckedInGuest::class,
            BookingDetail::class,
            'booking_order_id',   // FK trên booking_details
            'booking_details_id', // FK trên checked_in_guests
            'id',                 // PK của booking_orders
            'id'                  // PK của booking_details
        );
    }

    public function bookingServices(): HasManyThrough
    {
        return $this->hasManyThrough(
            BookingService::class,
            BookingDetail::class,
            'booking_order_id',
            'booking_details_id',
            'id',
            'id'
        );
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(
            Promotion::class,
            'promotion_usage',
            'booking_order_id',
            'promotion_id'
        )
        ->withPivot('applied_discount_amount')
        ->withTimestamps();
        // ->using(PromotionUsage::class); // Optional: nếu có pivot model
    }

    // === ACCESSORS ===

    public function getPropertyAttribute()
    {
        return $this->details->first()?->room?->property;
    }

    // === SCOPES ===

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }
}
