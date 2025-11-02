<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\User\AuthController as UserAuthController;
use App\Http\Controllers\Staff\AuthController as StaffAuthController;
use App\Http\Controllers\User\ResetPasswordController;
use App\Http\Controllers\User\VerifyEmailController;
use App\Http\Controllers\Api\V1\PropertyController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\RoomTypeController;
use App\Http\Controllers\Api\V1\AmenityController;
// use App\Http\Controllers\Api\V1\DriveController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\RoomImageController;

Route::prefix('admin')->group(function () {
    Route::post('login', [AdminAuthController::class, 'login']);
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::post('logout', [AdminAuthController::class, 'logout']);
        // thêm các route quản lý admin
        // 1. Mở toàn bộ API CRUD cho Property
        Route::apiResource('v1/properties', PropertyController::class);

        // 2. Mở API lấy danh sách Owner (cho Form)
        Route::get('v1/users/lookup', [UserController::class, 'lookup']);

        // 3. RoomType: mở toàn bộ CRUD (upload LOCAL)
        Route::apiResource('v1/room-types', RoomTypeController::class);

        // 4. Mở API lấy danh sách Amenities
        Route::get('v1/amenities', [AmenityController::class, 'index']);

        Route::apiResource('v1/amenities', AmenityController::class);

        Route::apiResource('v1/rooms', RoomController::class);

        // Route để upload MỘT HOẶC NHIỀU ảnh cho 1 phòng
        Route::post('v1/rooms/{room}/upload-images', [RoomImageController::class, 'store']);

        // Route để xóa 1 ảnh cụ thể
        Route::delete('v1/room-images/{roomImage}', [RoomImageController::class, 'destroy']);
    });
});


Route::prefix('staff')->group(function () {
    Route::post('register', [StaffAuthController::class, 'register']);
    Route::post('login', [StaffAuthController::class, 'login']);
    Route::middleware(['auth:sanctum', 'role:staff'])->group(function () {
        Route::post('logout', [StaffAuthController::class, 'logout']);
        // thêm các route dành cho nhân viên
    });
});

Route::prefix('user')->group(function () {
    Route::post('/register', [UserAuthController::class, 'register']);
    Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->middleware(['signed'])
        ->name('verification.verify');
    Route::post('login', [UserAuthController::class, 'login']);
    Route::post('forgot-password', [UserAuthController::class, 'forgotPassword']);
    Route::post('reset-password', [UserAuthController::class, 'resetPassword']);
    Route::get('reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])
        ->name('password.reset');
    Route::middleware(['auth:sanctum', 'role:user'])->group(function () {
        Route::post('logout', [UserAuthController::class, 'logout']);
        // thêm route người dùng (giỏ hàng, đơn hàng, ...)
    });
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
