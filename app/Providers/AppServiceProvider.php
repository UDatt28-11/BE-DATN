<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Event;
use App\Events\LoginSuccessful;
use App\Listeners\LogSuccessfulLogin;
use App\Models\BookingOrder;
use App\Observers\BookingOrderObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        Event::listen(LoginSuccessful::class, LogSuccessfulLogin::class);
        
        // Register BookingOrder observer for cache invalidation
        BookingOrder::observe(BookingOrderObserver::class);
    }
}
