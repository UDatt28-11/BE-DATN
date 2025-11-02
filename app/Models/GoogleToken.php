<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleToken extends Model
{
    /**
     * Các cột có thể fill
     */
    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    /**
     * Cast expires_at thành datetime
     */
    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Quan hệ: GoogleToken thuộc về 1 User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
