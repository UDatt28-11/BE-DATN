<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'password',
        'phone_number',
        'avatar_url',
        'status',
        'date_of_birth',
        'gender',
        'address',
        'preferred_language',
        'google_id',
        'facebook_id',
        'role',
        'identity_verified',
        'identity_type',
        'identity_number',
        'identity_image_url',
        'verified_at',
        'verified_by',
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
    // Check phân quyền
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isStaff()
    {
        return $this->role === 'staff';
    }

    public function isUser()
    {
        return $this->role === 'user';
    }
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'identity_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    // Properties (as owner)
    public function properties()
    {
        return $this->hasMany(Property::class, 'owner_id');
    }

    // Booking orders (as guest)
    public function bookingOrders()
    {
        return $this->hasMany(BookingOrder::class, 'guest_id');
    }

    // Conversations
    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants', 'user_id', 'conversation_id')
            ->withTimestamps();
    }

    // Messages sent
    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    // Vouchers
    public function vouchers()
    {
        return $this->belongsToMany(Voucher::class, 'user_vouchers', 'user_id', 'voucher_id')
            ->withPivot('booking_order_id', 'claimed_at', 'used_at')
            ->withTimestamps();
    }

    // User vouchers (pivot table)
    public function userVouchers()
    {
        return $this->hasMany(UserVoucher::class);
    }

    // Reviews
    public function reviews()
    {
        return $this->hasMany(Review::class, 'user_id');
    }

    // Verifier relationships
    public function verifiedRooms()
    {
        return $this->hasMany(Room::class, 'verified_by');
    }

    public function verifiedProperties()
    {
        return $this->hasMany(Property::class, 'verified_by');
    }

    public function verifiedUsers()
    {
        return $this->hasMany(User::class, 'verified_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // Scopes
    public function scopeIdentityVerified($query)
    {
        return $query->where('identity_verified', true);
    }

    public function scopeIdentityNotVerified($query)
    {
        return $query->where('identity_verified', false);
    }
}
