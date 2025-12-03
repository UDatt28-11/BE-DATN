<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\RoomTypeImage;

class RoomType extends Model
{
    use HasFactory, SoftDeletes;

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

    public function images(): HasMany {
        return $this->hasMany(RoomTypeImage::class);
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'promotion_room_type', 'room_type_id', 'promotion_id')
            ->withTimestamps();
    }
}
