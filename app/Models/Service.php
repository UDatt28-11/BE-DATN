<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'property_id',
        'name',
        'price',
        'unit',
        'description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    // Relationships
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function bookingServices(): HasMany
    {
        return $this->hasMany(BookingService::class);
    }

    // Accessor for unit_price (alias of price)
    public function getUnitPriceAttribute()
    {
        return $this->price;
    }
}

