<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AdminPasswordReset extends Model
{
    protected $table = 'admin_password_resets';

    protected $fillable = [
        'email',
        'otp',
        'expires_at',
        'is_used',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    // Scopes
    public function scopeValid($query)
    {
        return $query->where('is_used', false)
            ->where('expires_at', '>', now());
    }

    public function scopeByEmail($query, $email)
    {
        return $query->where('email', $email);
    }

    public function scopeByOtp($query, $otp)
    {
        return $query->where('otp', $otp);
    }

    // Helper Methods
    public function isValid(): bool
    {
        return !$this->is_used && $this->expires_at > now();
    }

    public function markAsUsed(): void
    {
        $this->update([
            'is_used' => true,
            'used_at' => now(),
        ]);
    }

    public static function generateOtp($email): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        self::create([
            'email' => $email,
            'otp' => $otp,
            'expires_at' => now()->addMinutes(10), // OTP hết hạn sau 10 phút
        ]);

        return $otp;
    }
}

