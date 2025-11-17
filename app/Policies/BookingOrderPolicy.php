<?php
// app/Policies/BookingOrderPolicy.php

namespace App\Policies;

use App\Models\BookingOrder;
use App\Models\User;

class BookingOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, BookingOrder $order): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, BookingOrder $order): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, BookingOrder $order): bool
    {
        return $user->role === 'admin';
    }
}
