<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\RoomType;
use App\Models\Amenity;

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

    public function owner() {
        return $this->belongsTo(User::class, 'owner_id');
    }
    public function roomTypes() {
        return $this->hasMany(RoomType::class);
    }
    public function amenities() {
        return $this->hasMany(Amenity::class);
    }
}
