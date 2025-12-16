<?php
// routes/api.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// === AUTH CONTROLLERS ===
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\StaffAuthController;
use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\AuthController as MainAuthController;

use App\Http\Controllers\User\VerifyEmailController;
use App\Http\Controllers\User\ResetPasswordController;
use App\Http\Controllers\User\AuthController;

use App\Http\Controllers\Api\FileController;

// === GOOGLE LOGIN ===
use App\Http\Controllers\Auth\GoogleController;

// === ADMIN RESOURCE CONTROLLERS ===
use App\Http\Controllers\Api\Admin\PropertyController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\AmenityController;
use App\Http\Controllers\Api\Admin\RoomController;
use App\Http\Controllers\Api\Admin\RoomTypeController;
use App\Http\Controllers\Api\Admin\RoomTypeImageController;
use App\Http\Controllers\Api\Admin\PropertyImageController;
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
use App\Http\Controllers\Api\Public\HomeController;
use App\Http\Controllers\Auth\AdminPasswordResetController;
use App\Http\Controllers\Api\User\VoucherController as UserVoucherController;
use App\Http\Controllers\Api\Staff\BookingController as StaffBookingController;
use App\Http\Controllers\Api\PayOSController;

// === MODELS & FACADES FOR PAYMENT REDIRECT ===
use App\Models\BookingOrder;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// ==================================================================
// 1. GOOGLE LOGIN (PUBLIC)
// ==================================================================
Route::prefix('google')->group(function () {
    // Lấy URL redirect đến Google OAuth
    Route::get('redirect/{role}', [GoogleController::class, 'redirectToGoogle'])
        ->where('role', 'admin|staff|user');
    
    // Callback từ Google - có thể có hoặc không có role trong URL
    // Role được lấy từ state parameter nếu không có trong URL
    Route::get('callback/{role?}', [GoogleController::class, 'handleGoogleCallback'])
        ->where('role', 'admin|staff|user');
});

// ==================================================================
// 2. GLOBAL AUTH (Admin + Staff + User) - BE tự xác định role
// ==================================================================
Route::post('login', [MainAuthController::class, 'login'])
    ->middleware('throttle:10,1');
Route::middleware('auth:sanctum')->post('logout', [MainAuthController::class, 'logout']);

// ==================================================================
// 3. ADMIN AUTH (giữ lại cho các client khác nếu cần)
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
// 4. STAFF AUTH
// ==================================================================
Route::prefix('staff')->group(function () {
    Route::post('login', [StaffAuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::middleware('auth:sanctum')->post('logout', LogoutController::class);
});

// ==================================================================
// 5. USER AUTH (PUBLIC ROUTES - Đặt trước protected routes)
// ==================================================================
// ========================================
// TEST ROUTES (KHÔNG CẦN AUTH - Đặt TRƯỚC để test)
// ========================================
// PAYOS WEBHOOK (PUBLIC ROUTE - Không cần auth)
// ========================================
Route::post('payos/webhook', [PayOSController::class, 'webhook'])->name('payos.webhook');

// PAYOS REDIRECT (PUBLIC ROUTE - Redirect về localhost)
// ========================================
Route::get('payment/redirect', function (Request $request) {
    // Lấy tất cả query params từ PayOS
    $queryParams = $request->query();
    
    // Xác định loại redirect (success hoặc cancel)
    // Nếu có param 'type' từ returnUrl của chúng ta, dùng nó
    // Nếu không, kiểm tra cancel hoặc status
    $type = $request->get('type');
    if (!$type) {
        $type = ($request->get('cancel') === 'true' || $request->get('status') === 'CANCELLED') ? 'cancel' : 'success';
    }
    
    // Nếu là cancel và có booking_id, tự động hủy booking nếu chưa thanh toán
    if ($type === 'cancel' && $request->has('booking_id')) {
        try {
            $bookingId = $request->get('booking_id');
            $booking = \App\Models\BookingOrder::find($bookingId);
            
            if ($booking && $booking->payment_status === 'unpaid' && $booking->status === 'pending') {
                // Tự động hủy booking khi hủy thanh toán và chưa thanh toán gì
                $booking->update(['status' => 'cancelled']);
                
                \Illuminate\Support\Facades\Log::info('Payment cancel: Auto-cancelled booking', [
                    'booking_id' => $bookingId,
                    'reason' => 'Payment cancelled and no payment made',
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Payment cancel: Error auto-cancelling booking', [
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    // Nếu là success và có invoice_id (thanh toán invoice sau checkout)
    if ($type === 'success' && $request->has('invoice_id')) {
        try {
            $invoiceId = $request->get('invoice_id');
            $invoice = \App\Models\Invoice::with('bookingOrder')->find($invoiceId);
            
            if ($invoice) {
                // Tìm payment gần nhất của invoice này
                $latestPayment = $invoice->payments()
                    ->latest()
                    ->first();
                
                // Nếu có payment và user quay lại từ PayOS success, cập nhật payment status
                if ($latestPayment && $latestPayment->status === 'pending') {
                    $latestPayment->update([
                        'status' => 'success',
                        'paid_at' => now(),
                    ]);
                    Log::info('Payment redirect: Invoice payment status updated to success', [
                        'payment_id' => $latestPayment->id,
                        'invoice_id' => $invoice->id,
                    ]);
                }
                
                // Tính lại paid_amount từ tổng các payments thành công
                $totalPaidAmount = $invoice->payments()
                    ->whereIn('status', ['success', 'paid'])
                    ->sum('amount');
                
                // Cập nhật invoice status nếu đã thanh toán đầy đủ
                if ($totalPaidAmount >= $invoice->total_amount) {
                    $invoice->update(['status' => 'paid']);
                    
                    // Cập nhật booking status thành completed nếu đã checkout
                    if ($invoice->bookingOrder && in_array($invoice->bookingOrder->status, ['checked_out', 'partially_checked_out'])) {
                        $invoice->bookingOrder->update(['status' => 'completed']);
                    }
                    
                    Log::info('Payment redirect: Invoice paid after checkout', [
                        'invoice_id' => $invoice->id,
                        'booking_id' => $invoice->bookingOrder ? $invoice->bookingOrder->id : null,
                        'total_paid' => $totalPaidAmount,
                        'invoice_total' => $invoice->total_amount,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Payment redirect: Error processing invoice payment', [
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    // Nếu là success và có booking_id, kiểm tra và cập nhật booking status
    if ($type === 'success' && $request->has('booking_id')) {
        try {
            $bookingId = $request->get('booking_id');
            $booking = BookingOrder::find($bookingId);
            
            if ($booking) {
                // Tìm payment gần nhất của booking này
                $invoice = $booking->invoices()->first();
                if ($invoice) {
                    $payment = $invoice->payments()
                        ->whereIn('status', ['success', 'paid'])
                        ->latest()
                        ->first();
                    
                    // Tìm payment gần nhất (có thể là pending nếu webhook chưa được gọi)
                    $latestPayment = $invoice->payments()
                        ->latest()
                        ->first();
                    
                    // Nếu có payment và user quay lại từ PayOS success, cập nhật payment status
                    if ($latestPayment && $latestPayment->status === 'pending') {
                        $latestPayment->update([
                            'status' => 'success',
                            'paid_at' => now(),
                        ]);
                        Log::info('Payment redirect: Payment status updated to success', [
                            'payment_id' => $latestPayment->id,
                            'booking_id' => $booking->id,
                        ]);
                    }
                    
                    // Tính lại paid_amount từ tổng các payments thành công
                    $totalPaidAmount = $invoice->payments()
                        ->whereIn('status', ['success', 'paid'])
                        ->sum('amount');
                    
                    // Nếu có payment thành công, luôn cập nhật booking và đảm bảo deposit item
                    if ($totalPaidAmount > 0) {
                        DB::beginTransaction();
                        try {
                            // Cập nhật booking paid_amount và payment_status
                            $newPaidAmount = min($totalPaidAmount, $booking->total_amount);
                            $bookingPaymentStatus = 'partial';
                            
                            if ($newPaidAmount >= $booking->total_amount) {
                                $bookingPaymentStatus = 'paid';
                            }
                            
                            // Chỉ cập nhật status nếu chưa confirmed
                            $updateData = [
                                'paid_amount' => $newPaidAmount,
                                'payment_status' => $bookingPaymentStatus,
                            ];
                            
                            if ($booking->status !== 'confirmed') {
                                $updateData['status'] = 'confirmed';
                            }
                            
                            $booking->update($updateData);
                            
                            // Đảm bảo invoice có deposit item (luôn kiểm tra và tạo nếu chưa có)
                            $hasDepositItem = InvoiceItem::where('invoice_id', $invoice->id)
                                ->where('item_type', 'deposit')
                                ->exists();
                            
                            if (!$hasDepositItem && $newPaidAmount > 0) {
                                InvoiceItem::create([
                                    'invoice_id' => $invoice->id,
                                    'description' => 'Tiền cọc đã thanh toán (PayOS)',
                                    'quantity' => 1,
                                    'unit_price' => -$newPaidAmount,
                                    'total_line' => -$newPaidAmount,
                                    'item_type' => 'deposit',
                                ]);
                                
                                // Tính lại invoice total
                                $allItems = InvoiceItem::where('invoice_id', $invoice->id)->get();
                                $newTotalAmount = max(0, $allItems->sum('total_line'));
                                $invoice->update(['total_amount' => $newTotalAmount]);
                                
                                Log::info('Payment redirect: Deposit item created', [
                                    'booking_id' => $booking->id,
                                    'invoice_id' => $invoice->id,
                                    'deposit_amount' => -$newPaidAmount,
                                ]);
                            }
                            
                            DB::commit();
                            
                            Log::info('Payment redirect: Booking updated', [
                                'booking_id' => $booking->id,
                                'paid_amount' => $newPaidAmount,
                                'has_deposit_item' => $hasDepositItem,
                            ]);
                        } catch (\Exception $e) {
                            DB::rollBack();
                            Log::error('Payment redirect: Failed to update booking', [
                                'booking_id' => $bookingId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Payment redirect: Error checking booking status', [
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    // Xóa param 'type' khỏi query params (không cần gửi về frontend)
    unset($queryParams['type']);
    
    // Redirect về frontend với tất cả query params từ PayOS
    $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
    
    // Xây dựng URL với tất cả query params từ PayOS
    $redirectUrl = $frontendUrl . '/payment/' . $type;
    
    // Thêm các query params từ PayOS
    foreach ($queryParams as $key => $value) {
        if ($key !== 'type') { // Bỏ qua param 'type' vì đã dùng trong path
            $redirectUrl .= (strpos($redirectUrl, '?') === false ? '?' : '&') . $key . '=' . urlencode($value);
        }
    }
    
    // Nếu có booking_id, thêm vào URL
    if ($request->has('booking_id')) {
        $redirectUrl .= (strpos($redirectUrl, '?') === false ? '?' : '&') . 'booking_id=' . $request->get('booking_id');
    }
    
    // Nếu có invoice_id, thêm vào URL
    if ($request->has('invoice_id')) {
        $redirectUrl .= (strpos($redirectUrl, '?') === false ? '?' : '&') . 'invoice_id=' . $request->get('invoice_id');
    }
    
    return redirect($redirectUrl);
})->name('payment.redirect');

// ========================================
// Route test đơn giản nhất - không có prefix
Route::get('/test-route-simple', function () {
    return response()->json([
        'success' => true,
        'message' => 'Simple test route works!',
    ]);
});

// Route test với prefix user nhưng không có middleware
Route::get('/user/bookings/test-public', function (Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'Public test route works! Route exists and is accessible.',
        'url' => $request->fullUrl(),
        'method' => $request->method(),
        'headers' => $request->headers->all(),
        'query' => $request->query(),
        'path' => $request->path(),
        'route' => $request->route()?->getName(),
    ]);
});

Route::prefix('user')->group(function () {
    // Register (public, không cần auth) - dùng User\AuthController
    Route::post('register', [\App\Http\Controllers\User\AuthController::class, 'register'])
        ->middleware('throttle:10,1');
    
    // Login - dùng Auth\UserAuthController
    Route::post('login', [UserAuthController::class, 'login'])
        ->middleware('throttle:10,1');

    // Verify email, forgot password, reset password - dùng User\AuthController
    Route::get('email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->middleware(['signed'])
        ->name('verification.verify');
    Route::post('forgot-password', [\App\Http\Controllers\User\AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [\App\Http\Controllers\User\AuthController::class, 'resetPassword']);
    Route::get('reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])
        ->name('password.reset');
});

// ==================================================================
// 6. ADMIN ROUTES (role:admin)
// ==================================================================
// Tất cả routes trong group này yêu cầu: Bearer Token + Role: Admin
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {

    // ========================================
    // 🏠 PROPERTIES MANAGEMENT (Quản lý Homestay)
    // ========================================
    Route::post('properties/{property}/verify', [PropertyController::class, 'verify']);
    Route::post('properties/{property}/reject', [PropertyController::class, 'reject']);
    Route::apiResource('properties', PropertyController::class);
    Route::post('properties/{property}/upload-images', [PropertyImageController::class, 'store']);
    // Bulk delete cần khai báo TRƯỚC route có {propertyImage} để tránh Laravel bind 'bulk' thành id
    Route::delete('property-images/bulk', [PropertyImageController::class, 'bulkDestroy']);
    Route::delete('property-images/{propertyImage}', [PropertyImageController::class, 'destroy'])
        ->whereNumber('propertyImage');
    Route::post('property-images/{propertyImage}/set-primary', [PropertyImageController::class, 'setPrimary'])
        ->whereNumber('propertyImage');

    // ========================================
    // 👥 USERS MANAGEMENT (Quản lý Người dùng)
    // ========================================
    Route::get('users/lookup', [UserController::class, 'lookup']);
    Route::get('users/locked', [UserController::class, 'locked']);
    Route::post('users/bulk-lock', [UserController::class, 'bulkLock']);
    Route::post('users/bulk-unlock', [UserController::class, 'bulkUnlock']);
    Route::patch('users/{user}/status', [UserController::class, 'updateStatus']);
    Route::post('users/{user}/verify-identity', [UserController::class, 'verifyIdentity']);
    Route::post('users/{user}/reject-identity', [UserController::class, 'rejectIdentity']);
    Route::apiResource('users', UserController::class);

    // ========================================
    // 🛎️ AMENITIES MANAGEMENT (Quản lý Tiện ích)
    // ========================================
    Route::get('amenities/history', [AmenityController::class, 'history']);
    Route::post('amenities/{id}/restore', [AmenityController::class, 'restore'])->whereNumber('id');
    Route::delete('amenities/{id}/force', [AmenityController::class, 'forceDelete'])->whereNumber('id');
    Route::apiResource('amenities', AmenityController::class);

    // ========================================
    // 🏨 ROOM TYPES MANAGEMENT (Quản lý Loại phòng)
    // ========================================
    Route::get('room-types/history', [RoomTypeController::class, 'history']);
    Route::post('room-types/{id}/restore', [RoomTypeController::class, 'restore'])->whereNumber('id');
    Route::delete('room-types/{id}/force', [RoomTypeController::class, 'forceDelete'])->whereNumber('id');
    Route::patch('room-types/{roomType}/status', [RoomTypeController::class, 'updateStatus']);
    Route::get('room-types/{roomType}/amenities', [RoomTypeController::class, 'showWithAmenities']);
    Route::post('room-types/{roomType}/upload-images', [RoomTypeImageController::class, 'store']);
    Route::post('room-type-images/bulk-delete', [RoomTypeImageController::class, 'bulkDestroy']);
    Route::delete('room-type-images/{roomTypeImage}', [RoomTypeImageController::class, 'destroy'])
        ->whereNumber('roomTypeImage');
    Route::apiResource('room-types', RoomTypeController::class);

    // ========================================
    // 🛏️ ROOMS MANAGEMENT (Quản lý Phòng)
    // ========================================
    // Admin routes: lấy tất cả rooms kể cả chưa verified
    Route::get('rooms', [RoomController::class, 'index']);
    Route::get('rooms/{room}', [RoomController::class, 'show']);
    Route::patch('rooms/{room}/status', [RoomController::class, 'updateStatus']);
    Route::post('rooms/{room}/verify', [RoomController::class, 'verify']);
    Route::post('rooms/{room}/reject', [RoomController::class, 'reject']);
    Route::post('rooms', [RoomController::class, 'store']);
    Route::put('rooms/{room}', [RoomController::class, 'update']);
    Route::delete('rooms/{room}', [RoomController::class, 'destroy']);
    // Room images đã chuyển sang room type images

    // ========================================
    // 📅 BOOKING ORDERS MANAGEMENT (Quản lý Đặt phòng)
    // ========================================
    Route::get('booking-orders/statistics', [BookingOrderController::class, 'statistics']);
    Route::patch('booking-orders/{id}/status', [BookingOrderController::class, 'updateStatus']);
    Route::post('booking-orders/{id}/confirm-deposit', [BookingOrderController::class, 'confirmDeposit'])->where('id', '[0-9]+')->name('admin.bookings.confirmDeposit');
    Route::get('booking-orders/export', [BookingOrderController::class, 'export']);
    Route::apiResource('booking-orders', BookingOrderController::class);
    
    // ========================================
    // 🚪 CHECK-IN MANAGEMENT (Quản lý Check-in)
    // ========================================
    // Xem danh sách check-in requests
    Route::get('check-in-requests', [BookingOrderController::class, 'getCheckInRequests']);
    Route::get('check-in-requests/{id}', [BookingOrderController::class, 'getCheckInRequest']);
    // Approve/Reject check-in requests
    Route::post('check-in-requests/{id}/approve', [BookingOrderController::class, 'approveCheckInRequest']);
    Route::post('check-in-requests/{id}/reject', [BookingOrderController::class, 'rejectCheckInRequest']);
    // Checkout requests
    Route::get('checkout-requests', [BookingOrderController::class, 'getCheckoutRequests']);
    Route::post('checkout-requests/{id}/approve', [BookingOrderController::class, 'approveCheckoutRequest']);
    Route::post('checkout-requests/{id}/reject', [BookingOrderController::class, 'rejectCheckoutRequest']);
    // Admin check-in trực tiếp
    Route::post('booking-orders/{id}/check-in-direct', [BookingOrderController::class, 'checkInDirect']);
    // Quản lý lưu trú - Danh sách khách đã check-in
    Route::get('checked-in-guests', [BookingOrderController::class, 'getCheckedInGuests']);
    
    // Quản lý yêu cầu dịch vụ
    Route::get('service-requests', [BookingOrderController::class, 'getServiceRequests']);
    Route::post('service-requests/{id}/approve', [BookingOrderController::class, 'approveServiceRequest']);
    Route::post('service-requests/{id}/reject', [BookingOrderController::class, 'rejectServiceRequest']);

    // Quản lý yêu cầu tiện ích
    Route::get('amenity-requests', [BookingOrderController::class, 'getAmenityRequests']);
    Route::post('amenity-requests/{id}/approve', [BookingOrderController::class, 'approveAmenityRequest']);
    Route::post('amenity-requests/{id}/reject', [BookingOrderController::class, 'rejectAmenityRequest']);
    Route::post('amenity-requests/{id}/complete', [BookingOrderController::class, 'completeAmenityRequest']);

    // ========================================
    // 📧 EMAIL MANAGEMENT (Quản lý Email)
    // ========================================
    Route::apiResource('email-templates', EmailTemplateController::class);
    Route::get('email-logs/statistics', [EmailLogController::class, 'statistics']);
    Route::apiResource('email-logs', EmailLogController::class)->only(['index', 'show']);
    Route::get('email-configs', [EmailConfigController::class, 'index']);
    Route::put('email-configs', [EmailConfigController::class, 'update']);
    Route::get('email-configs/smtp', [EmailConfigController::class, 'getSmtpConfig']);
    Route::put('email-configs/smtp', [EmailConfigController::class, 'updateSmtpConfig']);

    // ========================================
    // 📊 ANALYTICS (Thống kê & Phân tích)
    // ========================================
    Route::get('analytics/dashboard', [AnalyticsController::class, 'dashboard']);
    Route::get('analytics/revenue', [AnalyticsController::class, 'revenue']);
    Route::get('analytics/customers', [AnalyticsController::class, 'customers']);
    Route::get('analytics/bookings', [AnalyticsController::class, 'bookings']);
    Route::get('analytics/properties', [AnalyticsController::class, 'properties']);

    // ========================================
    // 🎉 PROMOTIONS MANAGEMENT (Quản lý Khuyến mãi)
    // ========================================
    Route::post('promotions/bulk-delete', [PromotionController::class, 'bulkDelete']);
    Route::post('promotions/bulk-update-status', [PromotionController::class, 'bulkUpdateStatus']);
    Route::get('promotions/{id}/usage', [PromotionController::class, 'usage']);
    Route::apiResource('promotions', PromotionController::class);
    Route::get('promotions/statistics/overview', [PromotionController::class, 'statistics']);
    Route::post('promotions/validate', [PromotionController::class, 'validate']);

    // ========================================
    // ⭐ REVIEWS MANAGEMENT (Quản lý Đánh giá)
    // ========================================
    Route::apiResource('reviews', ReviewController::class);
    Route::get('reviews/statistics/overview', [ReviewController::class, 'statistics']);
    Route::post('reviews/{id}/approve', [ReviewController::class, 'approve'])->where('id', '[0-9]+');
    Route::post('reviews/{id}/reject', [ReviewController::class, 'reject'])->where('id', '[0-9]+');

    // ========================================
    // 📦 SUPPLIES MANAGEMENT (Quản lý Vật tư)
    // ========================================
    Route::apiResource('supplies', SupplyController::class);
    Route::get('supplies/low-stock/items', [SupplyController::class, 'getLowStockItems']);
    Route::get('supplies/out-of-stock/items', [SupplyController::class, 'getOutOfStockItems']);
    Route::get('supplies/statistics/overview', [SupplyController::class, 'getStatistics']);
    Route::post('supplies/{id}/adjust-stock', [SupplyController::class, 'adjustStock']);

    // ========================================
    // 📋 SUPPLY LOGS (Lịch sử Vật tư)
    // ========================================
    Route::prefix('supply-logs')->group(function () {
        Route::get('/', [SupplyLogController::class, 'index']);
        Route::get('/activities/recent', [SupplyLogController::class, 'getRecentActivities']);
        Route::get('/summary/movement', [SupplyLogController::class, 'getMovementSummary']);
        Route::get('/supply/{supplyId}', [SupplyLogController::class, 'getSupplyLogs']);
        Route::get('/{id}', [SupplyLogController::class, 'show'])->where('id', '[0-9]+');
    });

    // ========================================
    // 💰 INVOICES MANAGEMENT (Quản lý Hóa đơn)
    // ========================================
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::get('/export', [InvoiceController::class, 'export']);
        Route::get('/config/calculation', [InvoiceController::class, 'getCalculationConfig']);
        Route::get('/config/refund-policies', [InvoiceController::class, 'getRefundPolicyConfig']);
        Route::get('/statistics/overview', [InvoiceController::class, 'statistics']);
        Route::get('/{id}', [InvoiceController::class, 'show'])->where('id', '[0-9]+');

        Route::post('/', [InvoiceController::class, 'store']);
        Route::post('/create-from-booking', [InvoiceController::class, 'createFromBooking']);
        Route::put('/{id}', [InvoiceController::class, 'update'])->where('id', '[0-9]+');
        Route::match(['post', 'patch'], '/{id}/mark-paid', [InvoiceController::class, 'markAsPaid']);
        Route::patch('/{id}/status', [InvoiceController::class, 'updateStatus']);
        Route::post('/{id}/add-service', [InvoiceController::class, 'addService'])->where('id', '[0-9]+');
        Route::post('/{id}/add-damage', [InvoiceController::class, 'addDamage'])->where('id', '[0-9]+');
        Route::post('/{id}/approve-for-payment', [InvoiceController::class, 'approveForPayment'])->where('id', '[0-9]+');
        Route::delete('/{id}/items/{itemId}', [InvoiceController::class, 'removeItem'])->where('id', '[0-9]+')->where('itemId', '[0-9]+');

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

    // ========================================
    // 📝 INVOICE ITEMS (Mục Hóa đơn)
    // ========================================
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

    // ========================================
    // 💳 PAYMENTS MANAGEMENT (Quản lý Thanh toán)
    // ========================================
    Route::apiResource('payments', PaymentController::class);

    // ========================================
    // 🎟️ VOUCHERS MANAGEMENT (Quản lý Voucher)
    // ========================================
    Route::apiResource('vouchers', VoucherController::class);
    Route::post('vouchers/validate', [VoucherController::class, 'validateVoucher']);

    // ========================================
    // 🛎️ SERVICES MANAGEMENT (Quản lý Dịch vụ)
    // ========================================
    Route::apiResource('services', ServiceController::class);
    Route::patch('services/{service}/status', [ServiceController::class, 'updateStatus']);

    // ========================================
    // 📅 SUBSCRIPTIONS MANAGEMENT (Quản lý Đăng ký)
    // ========================================
    Route::apiResource('subscriptions', SubscriptionController::class);

    // ========================================
    // 💵 PRICE RULES MANAGEMENT (Quản lý Quy tắc giá)
    // ========================================
    Route::apiResource('price-rules', PriceRuleController::class);

    // ========================================
    // 💬 CONVERSATIONS MANAGEMENT (Quản lý Hội thoại)
    // ========================================
    Route::apiResource('conversations', ConversationController::class);

    // ========================================
    // 💸 PAYOUTS MANAGEMENT (Quản lý Thanh toán chủ nhà)
    // ========================================
    Route::apiResource('payouts', PayoutController::class);
});

// ==================================================================
// 7. STAFF ROUTES (role:staff,admin)
// ==================================================================
// Tất cả routes trong group này yêu cầu: Bearer Token + Role: Staff hoặc Admin
Route::middleware(['auth:sanctum', 'role:staff,admin'])->prefix('staff')->group(function () {
    // ========================================
    // 🚪 CHECK-IN/CHECK-OUT (Nhận/Trả phòng)
    // ========================================
    Route::get('/check-in/list', [\App\Http\Controllers\Api\Staff\CheckInOutController::class, 'getCheckInList']);
    Route::get('/check-in/{id}', [\App\Http\Controllers\Api\Staff\CheckInOutController::class, 'getCheckInDetails']);
    Route::post('/check-in/{id}', [\App\Http\Controllers\Api\Staff\CheckInOutController::class, 'checkIn']);
    Route::get('/check-out/list', [\App\Http\Controllers\Api\Staff\CheckInOutController::class, 'getCheckOutList']);
    Route::get('/check-out/{id}', [\App\Http\Controllers\Api\Staff\CheckInOutController::class, 'getCheckOutDetails']);
    Route::get('/check-out/{id}/supplies', [\App\Http\Controllers\Api\Staff\CheckInOutController::class, 'getSuppliesForCheckout']);
    Route::post('/check-out/{id}/preview', [\App\Http\Controllers\Api\Staff\CheckInOutController::class, 'previewCheckout']);
    Route::post('/check-out/{id}', [\App\Http\Controllers\Api\Staff\CheckInOutController::class, 'checkOut']);

    // ========================================
    // 📅 BOOKING MANAGEMENT (Quản lý Đặt phòng - Staff)
    // ========================================
    Route::get('/booking-orders', [StaffBookingController::class, 'index']);
    Route::get('/booking-orders/{id}', [StaffBookingController::class, 'show']);
    Route::patch('/booking-orders/{id}/status', [StaffBookingController::class, 'updateStatus']);
    Route::post('/booking-orders/{id}/change-detail', [StaffBookingController::class, 'changeDetail']);
});

// ==================================================================
// 7. USER ROUTES (PROTECTED - Đặt sau public routes để tránh conflict)
// ==================================================================
// ========================================
// TEST ROUTES (KHÔNG CẦN AUTH - Để debug)
// ========================================
Route::get('user/bookings/test-auth', function (Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'Auth test route works!',
        'user' => $request->user() ? [
            'id' => $request->user()->id,
            'email' => $request->user()->email,
            'role' => $request->user()->role,
        ] : 'not authenticated',
        'headers' => $request->headers->all(),
    ]);
})->middleware('auth:sanctum');

// Route test với controller (không cần auth)
Route::get('user/bookings/test-controller', [BookingOrderController::class, 'indexUser']);

// ========================================
// USER/STAFF/ADMIN BOOKINGS ROUTES - Tất cả role đều có thể đặt phòng
// Đặt trước để ưu tiên match, dùng role:user,staff,admin để cho phép tất cả
// ========================================
Route::middleware(['auth:sanctum', 'role:user,staff,admin'])->prefix('user')->group(function () {
    // Logout (chỉ user)
    Route::post('logout', LogoutController::class)->middleware('role:user');
    
    // Kho mã giảm giá của user (vouchers)
    Route::prefix('vouchers')->group(function () {
        Route::get('/', [UserVoucherController::class, 'index'])->name('user.vouchers.index');
        Route::get('/available', [UserVoucherController::class, 'available'])->name('user.vouchers.available');
        Route::get('/counts', [UserVoucherController::class, 'counts'])->name('user.vouchers.counts');
        Route::post('/claim', [UserVoucherController::class, 'claim'])->name('user.vouchers.claim');
        Route::post('/apply', [UserVoucherController::class, 'apply'])->name('user.vouchers.apply');
        Route::get('/{id}', [UserVoucherController::class, 'show'])->where('id', '[0-9]+')->name('user.vouchers.show');
    });
    
    // Bookings - Tất cả role đều có thể xem và tạo bookings của chính mình
    Route::get('bookings', [BookingOrderController::class, 'indexUser'])->name('user.bookings.index');
    Route::get('bookings/counts', [BookingOrderController::class, 'getBookingCounts'])->name('user.bookings.counts');
    Route::post('bookings', [BookingOrderController::class, 'storeUser'])->name('user.bookings.store');
    Route::get('bookings/{id}', [BookingOrderController::class, 'showUser'])->where('id', '[0-9]+')->name('user.bookings.show');
    Route::patch('bookings/{id}/payment', [BookingOrderController::class, 'updatePayment'])->where('id', '[0-9]+')->name('user.bookings.updatePayment');
    Route::post('bookings/{id}/deposit', [BookingOrderController::class, 'payDeposit'])->where('id', '[0-9]+')->name('user.bookings.payDeposit');
    Route::get('bookings/{id}/cancellation-policy', [BookingOrderController::class, 'getCancellationPolicy'])->where('id', '[0-9]+')->name('user.bookings.cancellationPolicy');
    Route::post('bookings/{id}/cancel', [BookingOrderController::class, 'cancelUserBooking'])->where('id', '[0-9]+')->name('user.bookings.cancel');
    Route::post('bookings/{id}/change-dates', [BookingOrderController::class, 'changeDates'])->where('id', '[0-9]+')->name('user.bookings.changeDates');
    Route::post('bookings/{id}/check-in', [BookingOrderController::class, 'checkInUser'])->where('id', '[0-9]+')->name('user.bookings.checkIn');
    Route::post('bookings/{id}/request-checkout', [BookingOrderController::class, 'requestCheckOut'])->where('id', '[0-9]+')->name('user.bookings.requestCheckOut');
    Route::post('bookings/{id}/check-out', [BookingOrderController::class, 'checkOutUser'])->where('id', '[0-9]+')->name('user.bookings.checkOut');
    Route::post('bookings/{id}/request-service', [BookingOrderController::class, 'requestService'])->where('id', '[0-9]+')->name('user.bookings.requestService');
    Route::post('bookings/{id}/request-amenity', [BookingOrderController::class, 'requestAmenity'])->where('id', '[0-9]+')->name('user.bookings.requestAmenity');
    
    // PayOS payment routes (user và admin đều có thể sử dụng)
    Route::post('payos/create-payment-link', [PayOSController::class, 'createPaymentLink'])->middleware('role:user,admin')->name('payos.createPaymentLink');
    Route::post('payos/create-invoice-payment-link', [PayOSController::class, 'createInvoicePaymentLink'])->middleware('role:user,admin')->name('payos.createInvoicePaymentLink');
    Route::get('payos/check-status/{orderCode}', [PayOSController::class, 'checkPaymentStatus'])->middleware('role:user,admin')->where('orderCode', '[0-9]+')->name('payos.checkStatus');
    
    // Invoices - User có thể xem và thanh toán invoice của chính mình
    Route::get('invoices', [\App\Http\Controllers\Api\Admin\InvoiceController::class, 'getUserInvoices'])->name('user.invoices.index');
    Route::get('invoices/{id}', [\App\Http\Controllers\Api\Admin\InvoiceController::class, 'getUserInvoice'])->where('id', '[0-9]+')->name('user.invoices.show');
    Route::post('invoices/{id}/pay', [\App\Http\Controllers\Api\Admin\InvoiceController::class, 'payInvoice'])->where('id', '[0-9]+')->name('user.invoices.pay');
});

// ========================================
// STAFF ROUTES (role:staff) - Đã được gộp vào route user ở trên
// ========================================

// ========================================
// ADMIN ROUTES (role:admin) - Có thể xem và tạo bookings của chính mình
// Đặt cuối cùng - XÓA route này vì admin có thể dùng route user với role:user,staff,admin
// HOẶC tạo route riêng với prefix khác để tránh conflict
// ========================================
// NOTE: Admin có thể dùng route user nếu có role phù hợp, hoặc dùng route admin/booking-orders
// Route này đã bị xóa để tránh conflict với route user

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
// 22. ROOMS (PUBLIC + PROTECTED)
// ==================================================================
Route::prefix('rooms')->group(function () {
    // Public routes - không cần đăng nhập
    Route::get('/', [RoomController::class, 'indexPublic']);
    Route::get('/{id}', [RoomController::class, 'showPublic'])->where('id', '[0-9]+');

    // Protected routes - cần đăng nhập và role admin
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::patch('/{room}/status', [RoomController::class, 'updateStatus']);
        Route::post('/{room}/verify', [RoomController::class, 'verify']);
        Route::post('/{room}/reject', [RoomController::class, 'reject']);
        Route::post('/', [RoomController::class, 'store']);
        Route::put('/{id}', [RoomController::class, 'update']);
        Route::delete('/{id}', [RoomController::class, 'destroy']);
        // Room images đã chuyển sang room type images
    });
});

// ==================================================================
// 23. PUBLIC HOMEPAGE (không cần đăng nhập)
// ==================================================================
// Public routes (caching can be enabled later with cache.api middleware)
Route::prefix('public')->group(function () {
    Route::get('/statistics', [HomeController::class, 'statistics']);
    Route::get('/room-types', [HomeController::class, 'roomTypes']);
    Route::get('/room-types/{id}', [HomeController::class, 'roomTypeDetail'])->whereNumber('id');
    Route::get('/room-types/{id}/reviews', [HomeController::class, 'roomTypeReviews'])->whereNumber('id');
    Route::get('/amenities', [HomeController::class, 'amenities']);
    Route::get('/featured-rooms', [HomeController::class, 'featuredRooms']);
    Route::get('/popular-rooms', [HomeController::class, 'popularRooms']);
    Route::get('/properties', [HomeController::class, 'properties']); // Featured properties for homepage
});

// API tổng hợp homepage data
Route::get('/homepage/data', [HomeController::class, 'homepageData']);

// API tìm kiếm properties (public)
Route::get('/properties/search', [HomeController::class, 'searchProperties']);

// API chi tiết properties (public)
Route::get('/properties/{id}', [HomeController::class, 'propertyDetail'])->where('id', '[0-9]+');
Route::get('/properties/{id}/reviews', [HomeController::class, 'propertyReviews'])->where('id', '[0-9]+');
Route::get('/properties/{id}/comments', [HomeController::class, 'propertyComments'])->where('id', '[0-9]+');

// API reviews và comments cho rooms (public)
Route::get('/rooms/{id}/reviews', [RoomController::class, 'roomReviews'])->where('id', '[0-9]+');
Route::get('/rooms/{id}/comments', [RoomController::class, 'roomComments'])->where('id', '[0-9]+');

// API danh sách rooms public (không cần auth)
Route::get('/rooms', [RoomController::class, 'indexPublic']);

// ==================================================================
// 24. TEST: LẤY USER HIỆN TẠI (XÓA TRƯỚC DEPLOY)
// ==================================================================
// Route này được đặt sau user/bookings để tránh conflict
Route::middleware('auth:sanctum')->get('/user/current', function (Request $request) {
    return $request->user();
});

// ==================================================================
// 24. FALLBACK 404
// ==================================================================
Route::fallback(function () {
    return response()->json(['message' => 'Route not found.'], 404);
});

Route::post('/upload-file', [FileController::class, 'store']);
