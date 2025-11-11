<?php
// app/Policies/AmenityPolicy.php

namespace App\Policies;

use App\Models\Amenity;
use App\Models\User;

class AmenityPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'staff']);
    }

    public function view(User $user, Amenity $amenity): bool
    {
        return in_array($user->role, ['admin', 'staff']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'staff']);
    }

    public function update(User $user, Amenity $amenity): bool
    {
        return in_array($user->role, ['admin', 'staff']);
    }

    public function delete(User $user, Amenity $amenity): bool
    {
        return $user->role === 'admin';
    }
}
