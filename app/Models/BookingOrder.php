<?php

namespace App\Models;

/*
 FILE GUARD – ADMIN BOOKING MODULE ONLY
 - KHÔNG chạm schema/migrations; dùng đúng tên cột từ migrations.
 - Guests FK: 'booking_details_id'.
 - Chỉ index/show/updateStatus; bắt buộc middleware/policy admin.
*/

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingOrder extends Model
{
    use HasFactory;

    protected $table = 'booking_orders';

    protected $fillable = [
        'guest_id',
        'order_code',
        'total_amount',
        'status',
    ];

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_id');
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
            'booking_order_id',      // Foreign key on booking_details
            'booking_details_id',    // Foreign key on checked_in_guests
            'id',                    // Local key on booking_orders
            'id'                     // Local key on booking_details
        );
    }
}


