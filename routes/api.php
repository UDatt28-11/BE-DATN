<?php
// routes/api.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// === AUTH CONTROLLERS ===
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\StaffAuthController;
use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Controllers\Auth\LogoutController;

use App\Http\Controllers\User\VerifyEmailController;
use App\Http\Controllers\User\ResetPasswordController;
use App\Http\Controllers\User\AuthController;

// === GOOGLE LOGIN ===
use App\Http\Controllers\Auth\GoogleController;

// === ADMIN RESOURCE CONTROLLERS ===
use App\Http\Controllers\Api\Admin\PropertyController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\AmenityController;
use App\Http\Controllers\Api\Admin\RoomController;
use App\Http\Controllers\Api\Admin\RoomTypeController;
use App\Http\Controllers\Api\Admin\RoomImageController;
use App\Http\Controllers\Api\Admin\BookingOrderController;
use App\Http\Controllers\Api\Admin\PromotionController;
use App\Http\Controllers\Api\Admin\ReviewController;
use App\Http\Controllers\Api\Admin\SupplyController;
use App\Http\Controllers\Api\Admin\SupplyLogController;
use App\Http\Controllers\Api\Admin\InvoiceController;
use App\Http\Controllers\Api\Admin\InvoiceItemController;
use App\Http\Controllers\Api\Admin\PaymentController;
use App\Http\Controllers\Api\Admin\VoucherController;
use App\Http\Controllers\Api\Admin\ServiceController;
use App\Http\Controllers\Api\Admin\SubscriptionController;
use App\Http\Controllers\Api\Admin\PriceRuleController;
use App\Http\Controllers\Api\Admin\ConversationController;
use App\Http\Controllers\Api\Admin\MessageController;
use App\Http\Controllers\Api\Admin\PayoutController;
use App\Http\Controllers\Api\Admin\EmailTemplateController;
use App\Http\Controllers\Api\Admin\EmailLogController;
use App\Http\Controllers\Api\Admin\EmailConfigController;
use App\Http\Controllers\Api\Admin\AnalyticsController;
use App\Http\Controllers\Auth\AdminPasswordResetController;

// ==================================================================
// 1. GOOGLE LOGIN (PUBLIC)
// ==================================================================
Route::prefix('google')->group(function () {
    Route::get('redirect/{role}', [GoogleController::class, 'redirectToGoogle'])
        ->where('role', 'admin|staff|user');
    Route::get('callback/{role}', [GoogleController::class, 'handleGoogleCallback'])
        ->where('role', 'admin|staff|user');
});

// ==================================================================
// 2. ADMIN AUTH
// ==================================================================
Route::prefix('admin')->group(function () {
    Route::post('login', [AdminAuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::middleware('auth:sanctum')->post('logout', LogoutController::class);
    
    // Password reset with OTP
    Route::post('forgot-password', [AdminPasswordResetController::class, 'sendOtp']);
    Route::post('reset-password', [AdminPasswordResetController::class, 'resetPassword']);
});

// ==================================================================
// 3. STAFF AUTH
// ==================================================================
Route::prefix('staff')->group(function () {
    Route::post('login', [StaffAuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::middleware('auth:sanctum')->post('logout', LogoutController::class);
});

// ==================================================================
// 4. USER AUTH
// ==================================================================
Route::prefix('user')->group(function () {
    Route::post('login', [UserAuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::middleware('auth:sanctum')->post('logout', LogoutController::class);

    // Verify email, forgot password, reset password
    Route::get('email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->middleware(['signed'])
        ->name('verification.verify');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::get('reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])
        ->name('password.reset');
});

// ==================================================================
// 5. ADMIN ROUTES (role:admin)
// ==================================================================
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {

    // Properties
    Route::post('properties/{property}/verify', [PropertyController::class, 'verify']);
    Route::post('properties/{property}/reject', [PropertyController::class, 'reject']);
    Route::apiResource('properties', PropertyController::class);

    // Users
    Route::get('users/lookup', [UserController::class, 'lookup']);
    Route::get('users/locked', [UserController::class, 'locked']);
    Route::post('users/bulk-lock', [UserController::class, 'bulkLock']);
    Route::post('users/bulk-unlock', [UserController::class, 'bulkUnlock']);
    Route::patch('users/{user}/status', [UserController::class, 'updateStatus']);
    Route::post('users/{user}/verify-identity', [UserController::class, 'verifyIdentity']);
    Route::post('users/{user}/reject-identity', [UserController::class, 'rejectIdentity']);
    Route::apiResource('users', UserController::class);

    // Amenities
    Route::apiResource('amenities', AmenityController::class);

    // Room Types
    Route::patch('room-types/{roomType}/status', [RoomTypeController::class, 'updateStatus']);
    Route::get('room-types/{roomType}/amenities', [RoomTypeController::class, 'showWithAmenities']);
    Route::apiResource('room-types', RoomTypeController::class);

    // Rooms
    Route::patch('rooms/{room}/status', [RoomController::class, 'updateStatus']);
    Route::post('rooms/{room}/verify', [RoomController::class, 'verify']);
    Route::post('rooms/{room}/reject', [RoomController::class, 'reject']);
    Route::apiResource('rooms', RoomController::class);
    Route::post('rooms/{room}/upload-images', [RoomImageController::class, 'store']);
    Route::delete('room-images/{roomImage}', [RoomImageController::class, 'destroy']);

    // Booking Orders
    Route::get('booking-orders/statistics', [BookingOrderController::class, 'statistics']);
    Route::patch('booking-orders/{id}/status', [BookingOrderController::class, 'updateStatus']);
    Route::apiResource('booking-orders', BookingOrderController::class);
    
    // Email Templates
    Route::apiResource('email-templates', EmailTemplateController::class);
    
    // Email Logs
    Route::get('email-logs/statistics', [EmailLogController::class, 'statistics']);
    Route::apiResource('email-logs', EmailLogController::class)->only(['index', 'show']);
    
    // Email Config
    Route::get('email-configs', [EmailConfigController::class, 'index']);
    Route::put('email-configs', [EmailConfigController::class, 'update']);
    Route::get('email-configs/smtp', [EmailConfigController::class, 'getSmtpConfig']);
    Route::put('email-configs/smtp', [EmailConfigController::class, 'updateSmtpConfig']);
    
    // Analytics
    Route::get('analytics/dashboard', [AnalyticsController::class, 'dashboard']);
    Route::get('analytics/revenue', [AnalyticsController::class, 'revenue']);
    Route::get('analytics/customers', [AnalyticsController::class, 'customers']);
    Route::get('analytics/bookings', [AnalyticsController::class, 'bookings']);
    Route::get('analytics/properties', [AnalyticsController::class, 'properties']);

    // Promotions
    Route::post('promotions/bulk-delete', [PromotionController::class, 'bulkDelete']);
    Route::post('promotions/bulk-update-status', [PromotionController::class, 'bulkUpdateStatus']);
    Route::get('promotions/{id}/usage', [PromotionController::class, 'usage']);
    Route::apiResource('promotions', PromotionController::class);
    Route::get('promotions/statistics/overview', [PromotionController::class, 'statistics']);
    Route::post('promotions/validate', [PromotionController::class, 'validate']);

    // Reviews
    Route::apiResource('reviews', ReviewController::class);
    Route::get('reviews/statistics/overview', [ReviewController::class, 'statistics']);
    Route::post('reviews/{id}/approve', [ReviewController::class, 'approve'])->where('id', '[0-9]+');
    Route::post('reviews/{id}/reject', [ReviewController::class, 'reject'])->where('id', '[0-9]+');

    // Supplies
    Route::apiResource('supplies', SupplyController::class);
    Route::get('supplies/low-stock/items', [SupplyController::class, 'getLowStockItems']);
    Route::get('supplies/out-of-stock/items', [SupplyController::class, 'getOutOfStockItems']);
    Route::get('supplies/statistics/overview', [SupplyController::class, 'getStatistics']);
    Route::post('supplies/{id}/adjust-stock', [SupplyController::class, 'adjustStock']);

    // Supply Logs
    Route::prefix('supply-logs')->group(function () {
        Route::get('/', [SupplyLogController::class, 'index']);
        Route::get('/activities/recent', [SupplyLogController::class, 'getRecentActivities']);
        Route::get('/summary/movement', [SupplyLogController::class, 'getMovementSummary']);
        Route::get('/supply/{supplyId}', [SupplyLogController::class, 'getSupplyLogs']);
        Route::get('/{id}', [SupplyLogController::class, 'show'])->where('id', '[0-9]+');
    });

    // Invoices
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::get('/config/calculation', [InvoiceController::class, 'getCalculationConfig']);
        Route::get('/config/refund-policies', [InvoiceController::class, 'getRefundPolicyConfig']);
        Route::get('/statistics/overview', [InvoiceController::class, 'statistics']);
        Route::get('/{id}', [InvoiceController::class, 'show'])->where('id', '[0-9]+');

        Route::post('/', [InvoiceController::class, 'store']);
        Route::post('/create-from-booking', [InvoiceController::class, 'createFromBooking']);
        Route::put('/{id}', [InvoiceController::class, 'update'])->where('id', '[0-9]+');
        Route::match(['post', 'patch'], '/{id}/mark-paid', [InvoiceController::class, 'markAsPaid']);
        Route::patch('/{id}/status', [InvoiceController::class, 'updateStatus']);

        Route::post('/config/calculation', [InvoiceController::class, 'setCalculationConfig']);
        Route::post('/config/refund-policies', [InvoiceController::class, 'createRefundPolicy']);
        Route::put('/config/refund-policies/{policyId}', [InvoiceController::class, 'updateRefundPolicy']);
        Route::delete('/{id}', [InvoiceController::class, 'destroy']);
        Route::post('/merge', [InvoiceController::class, 'mergeInvoices']);
        Route::post('/{id}/split', [InvoiceController::class, 'splitInvoice']);
        Route::post('/{id}/apply-discount', [InvoiceController::class, 'applyDiscount']);
        Route::delete('/{id}/discounts/{discountId}', [InvoiceController::class, 'removeDiscount'])
            ->whereNumber('id')->whereNumber('discountId');
        Route::post('/{id}/apply-refund-policy', [InvoiceController::class, 'applyRefundPolicy']);
    });

    // Invoice Items
    Route::prefix('invoices/{invoiceId}/items')->group(function () {
        Route::get('/', [InvoiceItemController::class, 'index']);
        Route::get('/penalties', [InvoiceItemController::class, 'getPenaltyItems']);
        Route::get('/regular', [InvoiceItemController::class, 'getRegularItems']);
        Route::post('/penalty', [InvoiceItemController::class, 'addPenaltyItem']);
        Route::post('/regular', [InvoiceItemController::class, 'addRegularItem']);
    });

    Route::prefix('invoice-items')->group(function () {
        Route::get('/', [InvoiceItemController::class, 'index']);
        Route::get('/{id}', [InvoiceItemController::class, 'show']);
        Route::post('/', [InvoiceItemController::class, 'store']);
        Route::put('/{id}', [InvoiceItemController::class, 'update']);
        Route::delete('/{id}', [InvoiceItemController::class, 'destroy']);
        Route::post('/bulk/create', [InvoiceItemController::class, 'bulkCreate']);
        Route::delete('/bulk/delete', [InvoiceItemController::class, 'bulkDelete']);
    });

    // Payments
    Route::apiResource('payments', PaymentController::class);

    // Vouchers
    Route::apiResource('vouchers', VoucherController::class);
    Route::post('vouchers/validate', [VoucherController::class, 'validateVoucher']);

    // Services
    Route::apiResource('services', ServiceController::class);

    // Subscriptions
    Route::apiResource('subscriptions', SubscriptionController::class);

    // Price Rules
    Route::apiResource('price-rules', PriceRuleController::class);

    // Conversations
    Route::apiResource('conversations', ConversationController::class);

    // Payouts
    Route::apiResource('payouts', PayoutController::class);
});

// ==================================================================
// 6. STAFF ROUTES (CẦN BỔ SUNG SAU)
// ==================================================================
Route::middleware(['auth:sanctum', 'role:staff'])->prefix('staff')->group(function () {
    // TODO: Thêm route cho staff
});

// ==================================================================
// 7. USER ROUTES (CẦN BỔ SUNG SAU)
// ==================================================================
Route::middleware(['auth:sanctum', 'role:user'])->prefix('user')->group(function () {
    // TODO: Thêm route cho user
});

// ==================================================================
// 8. PROMOTIONS (PUBLIC + PROTECTED)
// ==================================================================
Route::prefix('promotions')->group(function () {
    Route::get('/', [PromotionController::class, 'index']);
    Route::get('/active', [PromotionController::class, 'activePromotions']);
    Route::post('/validate', [PromotionController::class, 'validate']);
    Route::get('/{id}', [PromotionController::class, 'show'])->where('id', '[0-9]+');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/statistics/overview', [PromotionController::class, 'statistics']);
    });

    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::post('/', [PromotionController::class, 'store']);
        Route::put('/{id}', [PromotionController::class, 'update']);
        Route::delete('/{id}', [PromotionController::class, 'destroy']);
    });
});

// ==================================================================
// 9. REVIEWS (PUBLIC + PROTECTED)
// ==================================================================
Route::prefix('reviews')->group(function () {
    Route::get('/', [ReviewController::class, 'index']);
    Route::get('/property/{propertyId}', [ReviewController::class, 'getPropertyReviews']);
    Route::get('/room/{roomId}', [ReviewController::class, 'getRoomReviews']);
    Route::get('/{id}', [ReviewController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [ReviewController::class, 'store']);
        Route::put('/{id}', [ReviewController::class, 'update']);
        Route::delete('/{id}', [ReviewController::class, 'destroy']);
        Route::post('/{id}/mark-helpful', [ReviewController::class, 'markHelpful']);
        Route::post('/{id}/mark-not-helpful', [ReviewController::class, 'markNotHelpful']);
    });

    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::get('/statistics/overview', [ReviewController::class, 'statistics']);
    });

    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::post('/{id}/approve', [ReviewController::class, 'approve']);
        Route::post('/{id}/reject', [ReviewController::class, 'reject']);
    });
});

// ==================================================================
// 10. SUPPLIES (PUBLIC + PROTECTED)
// ==================================================================
Route::prefix('supplies')->group(function () {
    Route::get('/', [SupplyController::class, 'index']);
    Route::get('/{id}', [SupplyController::class, 'show']);

    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::get('/low-stock/items', [SupplyController::class, 'getLowStockItems']);
        Route::get('/out-of-stock/items', [SupplyController::class, 'getOutOfStockItems']);
        Route::get('/statistics/overview', [SupplyController::class, 'getStatistics']);
        Route::post('/', [SupplyController::class, 'store']);
        Route::put('/{id}', [SupplyController::class, 'update']);
        Route::delete('/{id}', [SupplyController::class, 'destroy']);
        Route::post('/{id}/adjust-stock', [SupplyController::class, 'adjustStock']);
    });
});

// ==================================================================
// 11. SUPPLY LOGS (STAFF + ADMIN)
// ==================================================================
Route::prefix('supply-logs')->middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
    Route::get('/', [SupplyLogController::class, 'index']);
    Route::get('/activities/recent', [SupplyLogController::class, 'getRecentActivities']);
    Route::get('/summary/movement', [SupplyLogController::class, 'getMovementSummary']);
    Route::get('/supply/{supplyId}', [SupplyLogController::class, 'getSupplyLogs']);
    Route::get('/{id}', [SupplyLogController::class, 'show']);
});

// ==================================================================
// 12. INVOICES (PUBLIC + PROTECTED)
// ==================================================================
Route::prefix('invoices')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [InvoiceController::class, 'index']);
    Route::get('/config/calculation', [InvoiceController::class, 'getCalculationConfig']);
    Route::get('/config/refund-policies', [InvoiceController::class, 'getRefundPolicyConfig']);
    Route::get('/statistics/overview', [InvoiceController::class, 'statistics']);
    Route::get('/{id}', [InvoiceController::class, 'show']);

    Route::middleware('role:staff,admin')->group(function () {
        Route::post('/', [InvoiceController::class, 'store']);
        Route::post('/create-from-booking', [InvoiceController::class, 'createFromBooking']);
        Route::put('/{id}', [InvoiceController::class, 'update']);
        Route::match(['post', 'patch'], '/{id}/mark-paid', [InvoiceController::class, 'markAsPaid']);
        Route::patch('/{id}/status', [InvoiceController::class, 'updateStatus']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::post('/config/calculation', [InvoiceController::class, 'setCalculationConfig']);
        Route::post('/config/refund-policies', [InvoiceController::class, 'createRefundPolicy']);
        Route::put('/config/refund-policies/{policyId}', [InvoiceController::class, 'updateRefundPolicy']);
        Route::delete('/{id}', [InvoiceController::class, 'destroy']);
        Route::post('/merge', [InvoiceController::class, 'mergeInvoices']);
        Route::post('/{id}/split', [InvoiceController::class, 'splitInvoice']);
        Route::post('/{id}/apply-discount', [InvoiceController::class, 'applyDiscount']);
        Route::delete('/{id}/discounts/{discountId}', [InvoiceController::class, 'removeDiscount']);
        Route::post('/{id}/apply-refund-policy', [InvoiceController::class, 'applyRefundPolicy']);
    });
});

// ==================================================================
// 13. INVOICE ITEMS (PROTECTED)
// ==================================================================
Route::prefix('invoices/{invoiceId}/items')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [InvoiceItemController::class, 'index']);
    Route::get('/penalties', [InvoiceItemController::class, 'getPenaltyItems']);
    Route::get('/regular', [InvoiceItemController::class, 'getRegularItems']);

    Route::middleware('role:staff,admin')->group(function () {
        Route::post('/penalty', [InvoiceItemController::class, 'addPenaltyItem']);
        Route::post('/regular', [InvoiceItemController::class, 'addRegularItem']);
    });
});

Route::prefix('invoice-items')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [InvoiceItemController::class, 'index']);
    Route::get('/{id}', [InvoiceItemController::class, 'show']);

    Route::middleware('role:staff,admin')->group(function () {
        Route::post('/', [InvoiceItemController::class, 'store']);
        Route::put('/{id}', [InvoiceItemController::class, 'update']);
        Route::delete('/{id}', [InvoiceItemController::class, 'destroy']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::post('/bulk/create', [InvoiceItemController::class, 'bulkCreate']);
        Route::delete('/bulk/delete', [InvoiceItemController::class, 'bulkDelete']);
    });
});

// ==================================================================
// 14. VOUCHERS (PUBLIC + PROTECTED)
// ==================================================================
Route::prefix('vouchers')->group(function () {
    Route::get('/', [VoucherController::class, 'index']);
    Route::get('/{id}', [VoucherController::class, 'show'])->where('id', '[0-9]+');
    Route::post('/validate', [VoucherController::class, 'validateVoucher']);

    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::post('/', [VoucherController::class, 'store']);
        Route::put('/{id}', [VoucherController::class, 'update']);
        Route::delete('/{id}', [VoucherController::class, 'destroy']);
    });
});

// ==================================================================
// 15. SERVICES (PUBLIC + PROTECTED)
// ==================================================================
Route::prefix('services')->group(function () {
    Route::get('/', [ServiceController::class, 'index']);
    Route::get('/{id}', [ServiceController::class, 'show'])->where('id', '[0-9]+');

    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::post('/', [ServiceController::class, 'store']);
        Route::put('/{id}', [ServiceController::class, 'update']);
        Route::delete('/{id}', [ServiceController::class, 'destroy']);
    });
});

// ==================================================================
// 16. SUBSCRIPTIONS (PROTECTED)
// ==================================================================
Route::prefix('subscriptions')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [SubscriptionController::class, 'index']);
    Route::get('/{id}', [SubscriptionController::class, 'show'])->where('id', '[0-9]+');

    Route::middleware('role:staff,admin')->group(function () {
        Route::post('/', [SubscriptionController::class, 'store']);
        Route::put('/{id}', [SubscriptionController::class, 'update']);
        Route::delete('/{id}', [SubscriptionController::class, 'destroy']);
    });
});

// ==================================================================
// 17. PRICE RULES (PROTECTED)
// ==================================================================
Route::prefix('price-rules')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [PriceRuleController::class, 'index']);
    Route::get('/{id}', [PriceRuleController::class, 'show'])->where('id', '[0-9]+');

    Route::middleware('role:staff,admin')->group(function () {
        Route::post('/', [PriceRuleController::class, 'store']);
        Route::put('/{id}', [PriceRuleController::class, 'update']);
        Route::delete('/{id}', [PriceRuleController::class, 'destroy']);
    });
});

// ==================================================================
// 18. CONVERSATIONS (PROTECTED)
// ==================================================================
Route::prefix('conversations')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ConversationController::class, 'index']);
    Route::post('/', [ConversationController::class, 'store']);
    Route::get('/{id}', [ConversationController::class, 'show'])->where('id', '[0-9]+');
    Route::delete('/{id}', [ConversationController::class, 'destroy'])->where('id', '[0-9]+');
});

// ==================================================================
// 19. MESSAGES (PROTECTED)
// ==================================================================
Route::prefix('conversations/{conversation}/messages')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [MessageController::class, 'index']);
    Route::post('/', [MessageController::class, 'store']);
});

Route::prefix('messages')->middleware('auth:sanctum')->group(function () {
    Route::get('/{id}', [MessageController::class, 'show'])->where('id', '[0-9]+');
    Route::put('/{id}', [MessageController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/{id}', [MessageController::class, 'destroy'])->where('id', '[0-9]+');
    Route::post('/{id}/mark-read', [MessageController::class, 'markAsRead'])->where('id', '[0-9]+');
    
    // Admin only
    Route::middleware('role:admin')->group(function () {
        Route::post('/{id}/hide', [MessageController::class, 'hide'])->where('id', '[0-9]+');
        Route::post('/{id}/unhide', [MessageController::class, 'unhide'])->where('id', '[0-9]+');
    });
});

// ==================================================================
// 20. PAYMENTS (PROTECTED)
// ==================================================================
Route::prefix('payments')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [PaymentController::class, 'index']);
    Route::get('/{id}', [PaymentController::class, 'show'])->where('id', '[0-9]+');

    Route::middleware('role:staff,admin')->group(function () {
        Route::post('/', [PaymentController::class, 'store']);
        Route::put('/{id}', [PaymentController::class, 'update']);
        Route::delete('/{id}', [PaymentController::class, 'destroy']);
    });
});

// ==================================================================
// 21. PAYOUTS (PROTECTED)
// ==================================================================
Route::prefix('payouts')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [PayoutController::class, 'index']);
    Route::get('/{id}', [PayoutController::class, 'show'])->where('id', '[0-9]+');

    Route::middleware('role:staff,admin')->group(function () {
        Route::post('/', [PayoutController::class, 'store']);
        Route::put('/{id}', [PayoutController::class, 'update']);
        Route::delete('/{id}', [PayoutController::class, 'destroy']);
    });
});

// ==================================================================
// 22. TEST: LẤY USER HIỆN TẠI (XÓA TRƯỚC DEPLOY)
// ==================================================================
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// ==================================================================
// 15. FALLBACK 404
// ==================================================================
Route::fallback(function () {
    return response()->json(['message' => 'Route not found.'], 404);
});
