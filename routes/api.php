
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\User\AuthController as UserAuthController;
use App\Http\Controllers\Staff\AuthController as StaffAuthController;
use App\Http\Controllers\User\ResetPasswordController;
use App\Http\Controllers\User\VerifyEmailController;

Route::prefix('admin')->group(function () {
    Route::post('login', [AdminAuthController::class, 'login']);
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::post('logout', [AdminAuthController::class, 'logout']);
        // thêm các route quản lý admin
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
