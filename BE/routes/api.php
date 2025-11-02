<?php

use Illuminate\Support\Facades\Route;

// Quản lý người dùng
Route::middleware([])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/users', [\App\Http\Controllers\Api\Admin\UserController::class, 'index']);
        Route::get('/users/{id}', [\App\Http\Controllers\Api\Admin\UserController::class, 'show']);
        Route::post('/users', [\App\Http\Controllers\Api\Admin\UserController::class, 'store']);
        Route::put('/users/{id}', [\App\Http\Controllers\Api\Admin\UserController::class, 'update']);
        Route::delete('/users/{id}', [\App\Http\Controllers\Api\Admin\UserController::class, 'destroy']);
    });
