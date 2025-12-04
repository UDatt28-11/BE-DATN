<?php
// app/Models/CheckedInGuest.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckedInGuest extends Model
{
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
        'date_of_birth' => 'date',
        'check_in_time' => 'datetime',
    ];

    public function detail(): BelongsTo
    {
        return $this->belongsTo(BookingDetail::class, 'booking_details_id');
    }
}
