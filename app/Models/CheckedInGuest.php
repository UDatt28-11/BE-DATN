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
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function bookingDetail(): BelongsTo
    {
        return $this->belongsTo(BookingDetail::class, 'booking_details_id');
    }
}




