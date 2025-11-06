<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// === AUTH CONTROLLER CHUNG (1 FILE DUY NHẤT) ===
use App\Http\Controllers\AuthController;

// === USER AUTH (register, forgot, reset, verify) ===
use App\Http\Controllers\User\AuthController as UserAuthController;
use App\Http\Controllers\User\VerifyEmailController;
use App\Http\Controllers\User\ResetPasswordController;

// === GOOGLE LOGIN (theo role) ===
use App\Http\Controllers\Auth\GoogleController;

// === RESOURCE CONTROLLERS ===
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AmenityController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\RoomTypeController;
use App\Http\Controllers\Api\RoomImageController;

// === NEW CONTROLLERS ===
use App\Http\Controllers\Api\Admin\BookingOrderController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\VoucherController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SupplyController;
use App\Http\Controllers\Api\SupplyLogController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\InvoiceItemController;

// === ADMIN & STAFF AUTH ===
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Staff\AuthController as StaffAuthController;

// ==================================================================
// 1. AUTH CHUNG: LOGIN + LOGOUT (TẤT CẢ ROLE DÙNG CHUNG)
// ==================================================================
Route::post('login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('logout', [AuthController::class, 'logout']);

// ==================================================================
// 2. GOOGLE LOGIN: RIÊNG THEO ROLE (dùng defaults)
// ==================================================================
Route::prefix('google')->group(function () {
    Route::get('redirect/{role}', [GoogleController::class, 'redirectToGoogle'])
        ->where('role', 'admin|staff|user');
    Route::get('callback/{role}', [GoogleController::class, 'handleGoogleCallback'])
        ->where('role', 'admin|staff|user');
});

// ==================================================================
// 2.1. ADMIN AUTH: login, logout (riêng cho admin)
// ==================================================================
Route::prefix('admin')->group(function () {
    Route::post('login', [AdminAuthController::class, 'login']);
    Route::middleware('auth:sanctum')->post('logout', [AdminAuthController::class, 'logout']);
});

// ==================================================================
// 2.2. STAFF AUTH: register, login, logout (riêng cho staff)
// ==================================================================
Route::prefix('staff')->group(function () {
    Route::post('register', [StaffAuthController::class, 'register']);
    Route::post('login', [StaffAuthController::class, 'login']);
    Route::middleware('auth:sanctum')->post('logout', [StaffAuthController::class, 'logout']);
});

// ==================================================================
// 3. USER AUTH: register, verify, forgot, reset
// ==================================================================
Route::prefix('user')->group(function () {
    Route::post('register', [UserAuthController::class, 'register']);
    Route::post('forgot-password', [UserAuthController::class, 'forgotPassword']);
    Route::post('reset-password', [UserAuthController::class, 'resetPassword']);

    // Verify email
    Route::get('email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->middleware(['signed'])
        ->name('verification.verify');

    // Reset password form (nếu dùng web)
    Route::get('reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])
        ->name('password.reset');
});

// ==================================================================
// 4. ADMIN ROUTES
// ==================================================================
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    // Properties
    Route::apiResource('properties', PropertyController::class);

    // Users
    Route::get('users/lookup', [UserController::class, 'lookup']);
    Route::apiResource('users', AdminUserController::class);

    // Amenities
    Route::apiResource('amenities', AmenityController::class);

    // Room Types
    Route::apiResource('room-types', RoomTypeController::class);

    // Rooms
    Route::apiResource('rooms', RoomController::class);

    // Room Images (đặc biệt: upload nhiều ảnh cho 1 phòng)
    Route::post('rooms/{room}/upload-images', [RoomImageController::class, 'store']);
    Route::delete('room-images/{roomImage}', [RoomImageController::class, 'destroy']);

    // Booking Orders
    Route::get('booking-orders', [BookingOrderController::class, 'index']);
    Route::get('booking-orders/{id}', [BookingOrderController::class, 'show']);
    Route::post('booking-orders', [BookingOrderController::class, 'store']);
    Route::put('booking-orders/{id}', [BookingOrderController::class, 'update']);
    Route::patch('booking-orders/{id}/status', [BookingOrderController::class, 'updateStatus']);
    Route::delete('booking-orders/{id}', [BookingOrderController::class, 'destroy']);

    // Vouchers (CRUD trong admin)
    Route::apiResource('Vouchers', VoucherController::class);
    Route::get('Vouchers/statistics/overview', [VoucherController::class, 'statistics']);
    Route::post('Vouchers/validate', [VoucherController::class, 'validate']);

    // Vouchers (alias cho Vouchers)
    Route::apiResource('vouchers', VoucherController::class);
    Route::get('vouchers/statistics/overview', [VoucherController::class, 'statistics']);
    Route::post('vouchers/validate', [VoucherController::class, 'validate']);

    // Reviews (CRUD trong admin)
    Route::apiResource('reviews', ReviewController::class);
    Route::get('reviews/statistics/overview', [ReviewController::class, 'statistics']);
    Route::post('reviews/{id}/approve', [ReviewController::class, 'approve'])->where('id', '[0-9]+');
    Route::post('reviews/{id}/reject', [ReviewController::class, 'reject'])->where('id', '[0-9]+');

    // Supplies (CRUD trong admin)
    Route::apiResource('supplies', SupplyController::class);
    Route::get('supplies/low-stock/items', [SupplyController::class, 'getLowStockItems']);
    Route::get('supplies/out-of-stock/items', [SupplyController::class, 'getOutOfStockItems']);
    Route::get('supplies/statistics/overview', [SupplyController::class, 'getStatistics']);
    Route::post('supplies/{id}/adjust-stock', [SupplyController::class, 'adjustStock'])->where('id', '[0-9]+');

    // Supply Logs trong admin
    Route::prefix('supply-logs')->group(function () {
        Route::get('/', [SupplyLogController::class, 'index']);
        Route::get('/activities/recent', [SupplyLogController::class, 'getRecentActivities']);
        Route::get('/summary/movement', [SupplyLogController::class, 'getMovementSummary']);
        Route::get('/supply/{supplyId}', [SupplyLogController::class, 'getSupplyLogs']);
        Route::get('/{id}', [SupplyLogController::class, 'show'])->where('id', '[0-9]+');
    });

    // Invoices (CRUD trong admin)
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::get('/config/calculation', [InvoiceController::class, 'getCalculationConfig']);
        Route::get('/config/refund-policies', [InvoiceController::class, 'getRefundPolicyConfig']);
        Route::get('/statistics/overview', [InvoiceController::class, 'statistics']);
        Route::get('/{id}', [InvoiceController::class, 'show'])->where('id', '[0-9]+');

        Route::post('/', [InvoiceController::class, 'store']);
        Route::post('/create-from-booking', [InvoiceController::class, 'createFromBooking']);
        Route::put('/{id}', [InvoiceController::class, 'update'])->where('id', '[0-9]+');
        Route::match(['post', 'patch'], '/{id}/mark-paid', [InvoiceController::class, 'markAsPaid'])->where('id', '[0-9]+');
        Route::patch('/{id}/status', [InvoiceController::class, 'updateStatus'])->where('id', '[0-9]+');

        Route::post('/config/calculation', [InvoiceController::class, 'setCalculationConfig']);
        Route::post('/config/refund-policies', [InvoiceController::class, 'createRefundPolicy']);
        Route::put('/config/refund-policies/{policyId}', [InvoiceController::class, 'updateRefundPolicy'])->where('policyId', '[0-9]+');
        Route::delete('/{id}', [InvoiceController::class, 'destroy'])->where('id', '[0-9]+');
        Route::post('/merge', [InvoiceController::class, 'mergeInvoices']);
        Route::post('/{id}/split', [InvoiceController::class, 'splitInvoice'])->where('id', '[0-9]+');
        Route::post('/{id}/discounts', [InvoiceController::class, 'applyDiscount'])->where('id', '[0-9]+');
        Route::delete('/{id}/discounts/{discountId}', [InvoiceController::class, 'removeDiscount'])->whereNumber('id')->whereNumber('discountId');
        Route::post('/{id}/apply-discount', [InvoiceController::class, 'applyDiscount'])->where('id', '[0-9]+');
        Route::post('/{id}/apply-refund-policy', [InvoiceController::class, 'applyRefundPolicy'])->where('id', '[0-9]+');
    });

    Route::prefix('invoices/{invoiceId}/items')->group(function () {
        Route::get('/', [InvoiceItemController::class, 'index']);
        Route::get('/penalties', [InvoiceItemController::class, 'getPenaltyItems']);
        Route::get('/regular', [InvoiceItemController::class, 'getRegularItems']);
        Route::post('/penalty', [InvoiceItemController::class, 'addPenaltyItem']);
        Route::post('/regular', [InvoiceItemController::class, 'addRegularItem']);
    });

    Route::prefix('invoice-items')->group(function () {
        Route::get('/', [InvoiceItemController::class, 'index']);
        Route::get('/{id}', [InvoiceItemController::class, 'show'])->where('id', '[0-9]+');
        Route::post('/', [InvoiceItemController::class, 'store']);
        Route::put('/{id}', [InvoiceItemController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [InvoiceItemController::class, 'destroy'])->where('id', '[0-9]+');
        Route::post('/bulk/create', [InvoiceItemController::class, 'bulkCreate']);
        Route::delete('/bulk/delete', [InvoiceItemController::class, 'bulkDelete']);
    });

    // (Giữ admin modules chính: properties/amenities/room-types/rooms/booking-orders)

    // Thêm route quản lý staff, user, system settings...
    // Route::apiResource('staff', App\Http\Controllers\Admin\StaffController::class);
});

// ==================================================================
// 5. STAFF ROUTES
// ==================================================================
Route::middleware(['auth:sanctum', 'role:staff'])->prefix('staff')->group(function () {
    // Route::apiResource('bookings', App\Http\Controllers\Staff\BookingController::class);
    // Route::get('dashboard', [StaffDashboardController::class, 'index']);
});

// ==================================================================
// 6. USER ROUTES (người dùng cuối)
// ==================================================================
Route::middleware(['auth:sanctum', 'role:user'])->prefix('user')->group(function () {
    // Route::apiResource('my-bookings', App\Http\Controllers\User\BookingController::class);
    // Route::apiResource('cart', App\Http\Controllers\User\CartController::class);
});

// ==================================================================
// 7. TEST: LẤY USER HIỆN TẠI
// ==================================================================
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
// ==================================================================
// 8. VoucherS (Mã giảm giá)
// ==================================================================
Route::prefix('Vouchers')->group(function () {
    Route::get('/', [VoucherController::class, 'index']);
    Route::post('/validate', [VoucherController::class, 'validate']);
    Route::get('/active', [VoucherController::class, 'activeVouchers']);
    Route::get('/{id}', [VoucherController::class, 'show'])->where('id', '[0-9]+');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/statistics/overview', [VoucherController::class, 'statistics']);
    });

    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::post('/', [VoucherController::class, 'store']);
        Route::put('/{id}', [VoucherController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [VoucherController::class, 'destroy'])->where('id', '[0-9]+');
    });
});

// ==================================================================
// 9. REVIEWS (Đánh giá)
// ==================================================================
Route::prefix('reviews')->group(function () {
    Route::get('/', [ReviewController::class, 'index']);
    Route::get('/property/{propertyId}', [ReviewController::class, 'getPropertyReviews']);
    Route::get('/room/{roomId}', [ReviewController::class, 'getRoomReviews']);
    Route::get('/{id}', [ReviewController::class, 'show'])->where('id', '[0-9]+');

    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::get('/statistics/overview', [ReviewController::class, 'statistics']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [ReviewController::class, 'store']);
        Route::put('/{id}', [ReviewController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [ReviewController::class, 'destroy'])->where('id', '[0-9]+');
        Route::post('/{id}/mark-helpful', [ReviewController::class, 'markHelpful'])->where('id', '[0-9]+');
        Route::post('/{id}/mark-not-helpful', [ReviewController::class, 'markNotHelpful'])->where('id', '[0-9]+');
    });

    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::post('/{id}/approve', [ReviewController::class, 'approve'])->where('id', '[0-9]+');
        Route::post('/{id}/reject', [ReviewController::class, 'reject'])->where('id', '[0-9]+');
    });
});

// ==================================================================
// 10. SUPPLIES (Vật tư)
// ==================================================================
Route::prefix('supplies')->group(function () {
    Route::get('/', [SupplyController::class, 'index']);
    Route::get('/{id}', [SupplyController::class, 'show'])->where('id', '[0-9]+');

    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::get('/low-stock/items', [SupplyController::class, 'getLowStockItems']);
        Route::get('/out-of-stock/items', [SupplyController::class, 'getOutOfStockItems']);
        Route::get('/statistics/overview', [SupplyController::class, 'getStatistics']);
        Route::post('/', [SupplyController::class, 'store']);
        Route::put('/{id}', [SupplyController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [SupplyController::class, 'destroy'])->where('id', '[0-9]+');
        Route::post('/{id}/adjust-stock', [SupplyController::class, 'adjustStock'])->where('id', '[0-9]+');
    });
});

// ==================================================================
// 11. SUPPLY LOGS (Lịch sử vật tư)
// ==================================================================
Route::prefix('supply-logs')->middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
    Route::get('/', [SupplyLogController::class, 'index']);
    Route::get('/activities/recent', [SupplyLogController::class, 'getRecentActivities']);
    Route::get('/summary/movement', [SupplyLogController::class, 'getMovementSummary']);
    Route::get('/supply/{supplyId}', [SupplyLogController::class, 'getSupplyLogs']);
    Route::get('/{id}', [SupplyLogController::class, 'show'])->where('id', '[0-9]+');
});

// ==================================================================
// 12. INVOICES (Hóa đơn)
// ==================================================================
Route::prefix('invoices')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::get('/config/calculation', [InvoiceController::class, 'getCalculationConfig']);
        Route::get('/config/refund-policies', [InvoiceController::class, 'getRefundPolicyConfig']);
        Route::get('/statistics/overview', [InvoiceController::class, 'statistics']);
        Route::get('/{id}', [InvoiceController::class, 'show'])->where('id', '[0-9]+');
    });

    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::post('/', [InvoiceController::class, 'store']);
        Route::post('/create-from-booking', [InvoiceController::class, 'createFromBooking']);
        Route::put('/{id}', [InvoiceController::class, 'update'])->where('id', '[0-9]+');
        Route::match(['post', 'patch'], '/{id}/mark-paid', [InvoiceController::class, 'markAsPaid'])->where('id', '[0-9]+');
        Route::patch('/{id}/status', [InvoiceController::class, 'updateStatus'])->where('id', '[0-9]+');
    });

    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::post('/config/calculation', [InvoiceController::class, 'setCalculationConfig']);
        Route::post('/config/refund-policies', [InvoiceController::class, 'createRefundPolicy']);
        Route::put('/config/refund-policies/{policyId}', [InvoiceController::class, 'updateRefundPolicy'])->where('policyId', '[0-9]+');
        Route::delete('/{id}', [InvoiceController::class, 'destroy'])->where('id', '[0-9]+');
        Route::post('/merge', [InvoiceController::class, 'mergeInvoices']);
        Route::post('/{id}/split', [InvoiceController::class, 'splitInvoice'])->where('id', '[0-9]+');
        Route::post('/{id}/discounts', [InvoiceController::class, 'applyDiscount'])->where('id', '[0-9]+');
        Route::delete('/{id}/discounts/{discountId}', [InvoiceController::class, 'removeDiscount'])->whereNumber('id')->whereNumber('discountId');
        Route::post('/{id}/apply-discount', [InvoiceController::class, 'applyDiscount'])->where('id', '[0-9]+');
        Route::post('/{id}/apply-refund-policy', [InvoiceController::class, 'applyRefundPolicy'])->where('id', '[0-9]+');
    });
});

// ==================================================================
// 13. INVOICE ITEMS (Mục hóa đơn)
// ==================================================================
Route::prefix('invoices/{invoiceId}/items')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [InvoiceItemController::class, 'index']);
        Route::get('/penalties', [InvoiceItemController::class, 'getPenaltyItems']);
        Route::get('/regular', [InvoiceItemController::class, 'getRegularItems']);
    });

    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::post('/penalty', [InvoiceItemController::class, 'addPenaltyItem']);
        Route::post('/regular', [InvoiceItemController::class, 'addRegularItem']);
    });
});

Route::prefix('invoice-items')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [InvoiceItemController::class, 'index']);
        Route::get('/{id}', [InvoiceItemController::class, 'show'])->where('id', '[0-9]+');
    });

    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::post('/', [InvoiceItemController::class, 'store']);
        Route::put('/{id}', [InvoiceItemController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [InvoiceItemController::class, 'destroy'])->where('id', '[0-9]+');
    });

    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::post('/bulk/create', [InvoiceItemController::class, 'bulkCreate']);
        Route::delete('/bulk/delete', [InvoiceItemController::class, 'bulkDelete']);
    });
});
