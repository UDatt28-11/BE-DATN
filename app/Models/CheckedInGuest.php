<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class CheckedInGuest extends Model
{
    use HasFactory;
    protected $table = 'checked_in_guests';
    protected $fillable = [
        'booking_details_id',
        'full_name',
        'date_of_birth',
        'identity_type',
        'identity_number',
        'identity_image_url',
        'check_in_time',
    ];
    protected $casts = [
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
    ];

    // Relationships
    public function bookingOrder(): BelongsTo
    {
        return $this->belongsTo(BookingDetail::class, 'booking_details_id');
    }
}
