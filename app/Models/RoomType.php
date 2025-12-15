<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\RoomTypeImage;
use App\Models\Service;

class RoomType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'property_id',
        'name',
        'description',
        'image_url',
        'base_price',
        'max_adults',
        'max_children',
        'status',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'max_adults' => 'integer',
        'max_children' => 'integer',
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

    /**
     * Tiện ích của loại phòng
     */
    public function amenities(): BelongsToMany {
        return $this->belongsToMany(Amenity::class, 'room_type_amenities', 'room_type_id', 'amenity_id');
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'promotion_room_type', 'room_type_id', 'promotion_id')
            ->withTimestamps();
    }

    /**
     * Các dịch vụ mà loại phòng này có thể sử dụng
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'room_type_services', 'room_type_id', 'service_id');
    }

    /**
     * Tính tổng sức chứa
     */
    public function getTotalCapacityAttribute(): int
    {
        return $this->max_adults + $this->max_children;
    }
}
