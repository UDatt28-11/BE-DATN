<?php
// app/Policies/BookingOrderPolicy.php

namespace App\Policies;

use App\Models\BookingOrder;
use App\Models\User;

class BookingOrderPolicy
{
    public function viewAny(User $user) { return $user->hasRole('admin'); }
    public function view(User $user, BookingOrder $order)
    {
        return $user->hasRole('admin');
    }
    public function create(User $user) { return $user->hasRole('admin'); }
    public function update(User $user, BookingOrder $order) { return $user->hasRole('admin'); }
    public function delete(User $user, BookingOrder $order) { return $user->hasRole('admin'); }
}
