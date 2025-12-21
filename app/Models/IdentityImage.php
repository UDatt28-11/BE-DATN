<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityImage extends Model
{
    protected $table = 'identity_images';

    protected $fillable = [
        'checked_in_guest_id',
        'image_url',
        'side',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function checkedInGuest(): BelongsTo
    {
        return $this->belongsTo(CheckedInGuest::class, 'checked_in_guest_id');
    }
}

