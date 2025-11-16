<?php

use Illuminate\Support\Facades\Route;

// TEMP: Disable auth to unblock API while Sanctum is not configured
Route::middleware([])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/booking-orders', [\App\Http\Controllers\Api\Admin\BookingOrderController::class, 'index']);
        Route::get('/booking-orders/{id}', [\App\Http\Controllers\Api\Admin\BookingOrderController::class, 'show']);
        Route::post('/booking-orders', [\App\Http\Controllers\Api\Admin\BookingOrderController::class, 'store']);
        Route::put('/booking-orders/{id}', [\App\Http\Controllers\Api\Admin\BookingOrderController::class, 'update']);
        Route::patch('/booking-orders/{id}/status', [\App\Http\Controllers\Api\Admin\BookingOrderController::class, 'updateStatus']);
        Route::delete('/booking-orders/{id}', [\App\Http\Controllers\Api\Admin\BookingOrderController::class, 'destroy']);
        
        // Route cho rooms
        Route::get('/rooms', [\App\Http\Controllers\Api\Admin\RoomController::class, 'index']);
        Route::get('/rooms/{id}', [\App\Http\Controllers\Api\Admin\RoomController::class, 'show']);
        
        // Route cho room types
        Route::get('/room-types', [\App\Http\Controllers\Api\Admin\RoomTypeController::class, 'index']);
        Route::get('/room-types/{id}', [\App\Http\Controllers\Api\Admin\RoomTypeController::class, 'show']);
        
        // Route cho checked-in guests (quản lý lưu trú)
        Route::get('/checked-in-guests', [\App\Http\Controllers\Api\Admin\CheckedInGuestController::class, 'index']);
        Route::get('/booking-details/{bookingDetail}/guests', [\App\Http\Controllers\Api\Admin\CheckedInGuestController::class, 'getByBookingDetail']);
        Route::post('/booking-details/{bookingDetail}/guests', [\App\Http\Controllers\Api\Admin\CheckedInGuestController::class, 'storeForBookingDetail']);
        Route::put('/checked-in-guests/{checkedInGuest}', [\App\Http\Controllers\Api\Admin\CheckedInGuestController::class, 'update']);
        Route::delete('/checked-in-guests/{checkedInGuest}', [\App\Http\Controllers\Api\Admin\CheckedInGuestController::class, 'destroy']);
    });


