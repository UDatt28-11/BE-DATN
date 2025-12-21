<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\BookingService;

class BookingDetail extends Model
{
    protected $fillable = [
        'booking_order_id',
        'room_id',
        'check_in_date',
        'check_out_date',
        'num_adults',
        'num_children',
        'sub_total',
        'status',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'sub_total' => 'decimal:2',
    ];

    // Relationships
    public function bookingOrder(): BelongsTo
    {
        return $this->belongsTo(BookingOrder::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function bookingServices(): HasMany
    {
        return $this->hasMany(BookingService::class, 'booking_details_id');
    }

    public function checkedInGuests(): HasMany
    {
        return $this->hasMany(CheckedInGuest::class, 'booking_details_id');
    }

    /**
     * Alias cho checkedInGuests để tương thích với include 'details.guests' ở BookingOrderController@showUser
     */
    public function guests(): HasMany
    {
        return $this->hasMany(CheckedInGuest::class, 'booking_details_id');
    }

    public function review(): HasMany
    {
        return $this->hasMany(Review::class, 'booking_details_id');
    }

    public function checkInRequests(): HasMany
    {
        return $this->hasMany(CheckInRequest::class, 'booking_detail_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
