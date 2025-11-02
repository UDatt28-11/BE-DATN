<?php

use Illuminate\Support\Facades\Route;

// TEMP: Disable auth to unblock API while Sanctum is not configured
Route::middleware([])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/booking-orders', [\App\Http\Controllers\Api\Admin\BookingOrderController::class, 'index']);
        Route::get('/booking-orders/{id}', [\App\Http\Controllers\Api\Admin\BookingOrderController::class, 'show']);
        Route::patch('/booking-orders/{id}/status', [\App\Http\Controllers\Api\Admin\BookingOrderController::class, 'updateStatus']);
    });


