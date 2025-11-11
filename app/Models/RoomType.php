<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RoomType extends Model
{
    use HasFactory;

    // Khớp CSDL bookstay.sql
    protected $fillable = [
        'property_id',
        'name',
        'description',
        'image_url',
        'status',
    ];

    public function property(): BelongsTo {
        return $this->belongsTo(Property::class);
    }

    public function rooms(): HasMany {
        return $this->hasMany(Room::class);
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'promotion_room_type', 'room_type_id', 'promotion_id')
            ->withTimestamps();
    }
}
