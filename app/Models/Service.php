<?php
// app/Models/Service.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use SoftDeletes;

    protected $table = 'services';

    protected $fillable = [
        'property_id',
        'name',
        'price',
        'unit',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    /**
     * Thuộc về một property (homestay)
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Các booking service sử dụng dịch vụ này
     */
    public function bookingServices(): HasMany
    {
        return $this->hasMany(BookingService::class);
    }
}
