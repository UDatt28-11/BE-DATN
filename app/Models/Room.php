<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Property; // <-- Import
use App\Models\RoomType; // <-- Import
use App\Models\Amenity; // <-- Import
use App\Models\RoomImage; // <-- Import

class Room extends Model
{
    use HasFactory;

    /**
     * Các trường được phép gán hàng loạt (dựa trên bookstay.sql)
     */
    protected $fillable = [
        'property_id', // Quan trọng: Room cũng thuộc về 1 Property
        'room_type_id',
        'name',
        'description',
        'max_adults',
        'max_children',
        'price_per_night',
        'status', // (available, maintenance, occupied)
        'verification_status', // (pending, verified, rejected)
        'verification_notes',
        'verified_at',
        'verified_by',
    ];

    // ----- CÁC QUAN HỆ -----
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
    public function images(): HasMany
    {
        return $this->hasMany(RoomImage::class);
    }
    public function amenities(): BelongsToMany
    {
        // Quan hệ nhiều-nhiều qua bảng 'room_amenities'
        return $this->belongsToMany(Amenity::class, 'room_amenities');
    }

    public function priceRules(): HasMany
    {
        return $this->hasMany(PriceRule::class);
    }

    public function bookingDetails(): HasMany
    {
        return $this->hasMany(BookingDetail::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function supplies(): HasMany
    {
        return $this->hasMany(Supply::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    // Scopes
    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    public function scopePendingVerification($query)
    {
        return $query->where('verification_status', 'pending');
    }

    public function scopeRejected($query)
    {
        return $query->where('verification_status', 'rejected');
    }
}
