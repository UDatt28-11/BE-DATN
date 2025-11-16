<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class RoomType extends Model
{
    use HasFactory;

    protected $table = 'room_types';
    protected $fillable = [
        'property_id',
        'name',
        'description',
        'image_url',
    ];

    // Relationships
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'room_type_id');
    }
}
