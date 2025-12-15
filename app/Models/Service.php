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
     * Các loại phòng có thể sử dụng dịch vụ này
     */
    public function roomTypes()
    {
        return $this->belongsToMany(RoomType::class, 'room_type_services', 'service_id', 'room_type_id');
    }
}
