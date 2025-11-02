<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'full_name', // Khớp CSDL
        'email', 'password', 'phone_number', 'avatar_url',
        'status', 'date_of_birth', 'gender', 'address',
    ];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed'];

    public function roles(): BelongsToMany {
        return $this->belongsToMany(Role::class, 'user_roles');
    }
    public function properties(): HasMany {
        return $this->hasMany(Property::class, 'owner_id');
    }
    public function googleToken()
    {
        return $this->hasOne(GoogleToken::class);
    }
}
