<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Room;
use App\Models\Property;

class Amenity extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['property_id', 'name', 'icon_url', 'type', 'category'];

    public function property(): BelongsTo {
        return $this->belongsTo(Property::class);
    }
    public function rooms(): BelongsToMany {
        return $this->belongsToMany(Room::class, 'room_amenities');
    }
}
