<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Room extends Model
{
    protected $fillable = [
        'property_id',
        'room_type_id',
        'name',
        'description',
        'max_adults',
        'max_children',
        'price_per_night',
        'status',
    ];

    protected $casts = [
        'price_per_night' => 'decimal:2',
    ];

    // Relationships
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function supplies(): HasMany
    {
        return $this->hasMany(Supply::class);
    }

    public function bookingDetails(): HasMany
    {
        return $this->hasMany(BookingDetail::class);
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'promotion_room', 'room_id', 'promotion_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
