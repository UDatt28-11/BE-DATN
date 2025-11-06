<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\RoomType;
use App\Models\Amenity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Property extends Model
{
    use HasFactory;

    // Khớp CSDL bookstay.sql
    protected $fillable = [
        'owner_id',
        'name',
        'address',
        'description',
        'check_in_time',
        'check_out_time',
        'status',
    ];

    public function owner(): BelongsTo
    {
        // 'owner_id' là cột khóa ngoại trong bảng 'properties'
        // 'id' là khóa chính trong bảng 'users'
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    public function amenities(): HasMany
 {
        return $this->hasMany(Amenity::class);
    }
}
