<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    use HasFactory;

    // Khớp CSDL bookstay.sql
    protected $fillable = [
        'property_id',
        'name',
        'description',
        'image_url',
    ];

    public function property(): BelongsTo {
        return $this->belongsTo(Property::class);
    }

    public function rooms(): HasMany {
        return $this->hasMany(Room::class);
    }
}
