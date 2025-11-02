<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'drive_file_id',
        'web_view_link',
        'mime_type',
        'size_bytes',
        'image_url',
        'is_primary',
    ];

    /**
     * Lâý phòng (room) mà ảnh này thuộc về.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
