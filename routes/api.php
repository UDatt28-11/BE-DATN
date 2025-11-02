<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\PropertyController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\RoomTypeController;
use App\Http\Controllers\Api\V1\AmenityController;
use App\Http\Controllers\Api\V1\DriveController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\RoomImageController;

/*
|--------------------------------------------------------------------------
| API Routes MỞ (KHÔNG CẦN LOGIN - DÙNG ĐỂ TEST)
|--------------------------------------------------------------------------
*/
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
/*
|--------------------------------------------------------------------------
| API Routes BẢO MẬT (Cho các Epic sau)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::post('drive/upload-room-image', [DriveController::class, 'uploadRoomImage']);
    Route::get('rooms/{room}/images', [DriveController::class, 'listRoomImages']);
    Route::delete('room-images/{image}', [DriveController::class, 'deleteRoomImage']);
    Route::patch('room-images/{image}/primary', [DriveController::class, 'setPrimary']);
});
