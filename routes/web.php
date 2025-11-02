<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Auth\GoogleDriveAuthController;

// Trang chủ
Route::get('/', function () {
    return view('welcome');
});



Route::get('/auth/google', [GoogleDriveAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleDriveAuthController::class, 'callback']);
