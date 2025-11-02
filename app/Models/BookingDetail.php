<?php

namespace App\Models;



use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingDetail extends Model
{
    use HasFactory;

    protected $table = 'booking_details';

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

    public function bookingOrder(): BelongsTo
    {
        return $this->belongsTo(BookingOrder::class, 'booking_order_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function checkedInGuests(): HasMany
    {
        return $this->hasMany(CheckedInGuest::class, 'booking_details_id');
    }
}
