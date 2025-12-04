<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Voucher extends Model
{
    protected $fillable = [
        'property_id',
        'code',
        'discount_type',
        'discount_value',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_vouchers', 'voucher_id', 'user_id')
            ->withPivot('booking_order_id', 'claimed_at', 'used_at')
            ->withTimestamps();
    }

    public function bookingOrders(): BelongsToMany
    {
        return $this->belongsToMany(BookingOrder::class, 'user_vouchers', 'voucher_id', 'booking_order_id')
            ->withPivot('user_id', 'claimed_at', 'used_at')
            ->withTimestamps();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    public function scopeValid($query)
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }
}

