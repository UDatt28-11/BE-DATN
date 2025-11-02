<?php

namespace App\Models;



use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $table = 'rooms';

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

    public function bookingDetails(): HasMany
    {
        return $this->hasMany(BookingDetail::class, 'room_id');
    }
}




