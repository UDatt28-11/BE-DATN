<?php

namespace App\Listeners;

use App\Events\LoginSuccessful;
use Illuminate\Support\Facades\Log;

class LogSuccessfulLogin
{
    public function handle(LoginSuccessful $event)
    {
        activity()
            ->performedOn($event->user)
            ->causedBy($event->user)
            ->withProperties([
                'role' => $event->role,
                'ip'   => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('login');
    }
}
