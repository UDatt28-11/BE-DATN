<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceItemController;
use App\Http\Controllers\SupplyController;
use App\Http\Controllers\SupplyLogController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\RoomController;

/**
 * ========================================
 * 🔐 AUTHENTICATION (Xác thực & Đăng nhập)
 * ========================================
 */

// Đăng ký tài khoản mới
Route::post('/register', [AuthController::class, 'register']);

// Đăng nhập & nhận Bearer token
Route::post('/login', [AuthController::class, 'login']);

// Lấy thông tin user hiện tại (cần đăng nhập)
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

// Đăng xuất & xóa token (cần đăng nhập)
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

/**
 * ========================================
 * 🏠 ROOMS MANAGEMENT (Quản lý Phòng)
 * ========================================
 */

// Public routes - Xem danh sách phòng (cho khách hàng)
Route::prefix('rooms')->group(function () {
    // GET /rooms - Danh sách phòng công khai (chỉ phòng available)
    Route::get('/', [RoomController::class, 'index']);
    
    // GET /rooms/{id} - Chi tiết phòng công khai
    Route::get('/{id}', [RoomController::class, 'show'])->where('id', '[0-9]+');
});

// Admin routes - Quản lý phòng
Route::prefix('admin/rooms')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // GET /admin/rooms - Danh sách phòng
    Route::get('/', [RoomController::class, 'index']);
    
    // GET /admin/rooms/{id} - Chi tiết phòng
    Route::get('/{id}', [RoomController::class, 'show'])->where('id', '[0-9]+');
    
    // POST /admin/rooms - Tạo phòng mới
    Route::post('/', [RoomController::class, 'store']);
    
    // PUT /admin/rooms/{id} - Cập nhật phòng
    Route::put('/{id}', [RoomController::class, 'update'])->where('id', '[0-9]+');
    
    // DELETE /admin/rooms/{id} - Xóa phòng
    Route::delete('/{id}', [RoomController::class, 'destroy'])->where('id', '[0-9]+');
});

// Staff routes - Xem phòng
Route::prefix('staff/rooms')->middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
    // GET /staff/rooms - Danh sách phòng (staff có thể xem tất cả)
    Route::get('/', [RoomController::class, 'index']);
    
    // GET /staff/rooms/{id} - Chi tiết phòng
    Route::get('/{id}', [RoomController::class, 'show'])->where('id', '[0-9]+');
});

/**
 * ========================================
 * 📅 BOOKING ORDERS MANAGEMENT (Quản lý Đặt phòng)
 * ========================================
 */

// Customer routes - Đặt phòng và xem đặt phòng của mình
Route::prefix('customer/bookings')->middleware('auth:sanctum')->group(function () {
    // GET /customer/bookings - Danh sách đặt phòng của khách hàng
    Route::get('/', [BookingController::class, 'customerIndex']);
    
    // GET /customer/bookings/{id} - Chi tiết đặt phòng của khách hàng
    Route::get('/{id}', [BookingController::class, 'customerShow'])->where('id', '[0-9]+');
    
    // POST /customer/bookings - Tạo đặt phòng mới (khách hàng tự đặt)
    Route::post('/', [BookingController::class, 'customerStore']);
    
    // PATCH /customer/bookings/{id}/cancel - Hủy đặt phòng
    Route::patch('/{id}/cancel', [BookingController::class, 'customerCancel'])->where('id', '[0-9]+');
});

// Staff routes - Quản lý đặt phòng
Route::prefix('staff/bookings')->middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
    // GET /staff/bookings - Danh sách đặt phòng
    Route::get('/', [BookingController::class, 'index']);
    
    // GET /staff/bookings/{id} - Chi tiết đặt phòng
    Route::get('/{id}', [BookingController::class, 'show'])->where('id', '[0-9]+');
    
    // POST /staff/bookings - Tạo đặt phòng mới (nhân viên tạo cho khách)
    Route::post('/', [BookingController::class, 'store']);
    
    // PUT /staff/bookings/{id} - Cập nhật đặt phòng
    Route::put('/{id}', [BookingController::class, 'update'])->where('id', '[0-9]+');
    
    // PATCH /staff/bookings/{id}/status - Cập nhật trạng thái
    Route::patch('/{id}/status', [BookingController::class, 'updateStatus'])->where('id', '[0-9]+');
    
    // POST /staff/bookings/{id}/check-in - Check-in
    Route::post('/{id}/check-in', [BookingController::class, 'checkIn'])->where('id', '[0-9]+');
    
    // POST /staff/bookings/{id}/check-out - Check-out
    Route::post('/{id}/check-out', [BookingController::class, 'checkOut'])->where('id', '[0-9]+');
});

// Admin routes - Quản lý đặt phòng (toàn quyền)
Route::prefix('admin/booking-orders')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // GET /admin/booking-orders - Danh sách đặt phòng
    Route::get('/', [BookingController::class, 'index']);
    
    // GET /admin/booking-orders/{id} - Chi tiết đặt phòng
    Route::get('/{id}', [BookingController::class, 'show'])->where('id', '[0-9]+');
    
    // POST /admin/booking-orders - Tạo đặt phòng mới
    Route::post('/', [BookingController::class, 'store']);
    
    // PUT /admin/booking-orders/{id} - Cập nhật đặt phòng
    Route::put('/{id}', [BookingController::class, 'update'])->where('id', '[0-9]+');
    
    // PATCH /admin/booking-orders/{id}/status - Cập nhật trạng thái
    Route::patch('/{id}/status', [BookingController::class, 'updateStatus'])->where('id', '[0-9]+');
    
    // DELETE /admin/booking-orders/{id} - Xóa đặt phòng
    Route::delete('/{id}', [BookingController::class, 'destroy'])->where('id', '[0-9]+');
});

/**
 * ========================================
 * 💰 INVOICES MANAGEMENT (Quản lý Hóa đơn)
 * ========================================
 */

// Customer routes - Xem hóa đơn của mình
Route::prefix('customer/invoices')->middleware('auth:sanctum')->group(function () {
    // GET /customer/invoices - Danh sách hóa đơn của khách hàng
    Route::get('/', [InvoiceController::class, 'customerIndex']);
    
    // GET /customer/invoices/{id} - Chi tiết hóa đơn của khách hàng
    Route::get('/{id}', [InvoiceController::class, 'customerShow'])->where('id', '[0-9]+');
});

// Staff routes - Quản lý hóa đơn
Route::prefix('staff/invoices')->middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
    // GET /staff/invoices - Danh sách hóa đơn
    Route::get('/', [InvoiceController::class, 'index']);
    
    // GET /staff/invoices/{id} - Chi tiết hóa đơn
    Route::get('/{id}', [InvoiceController::class, 'show'])->where('id', '[0-9]+');
    
    // POST /staff/invoices - Tạo hóa đơn mới
    Route::post('/', [InvoiceController::class, 'store']);
    
    // POST /staff/invoices/create-from-booking - Tạo hóa đơn từ booking
    Route::post('/create-from-booking', [InvoiceController::class, 'createFromBooking']);
    
    // PUT /staff/invoices/{id} - Cập nhật hóa đơn
    Route::put('/{id}', [InvoiceController::class, 'update'])->where('id', '[0-9]+');
    
    // POST|PATCH /staff/invoices/{id}/mark-paid - Đánh dấu đã thanh toán
    Route::match(['post', 'patch'], '/{id}/mark-paid', [InvoiceController::class, 'markAsPaid'])->where('id', '[0-9]+');
    
    // PATCH /staff/invoices/{id}/status - Cập nhật trạng thái
    Route::patch('/{id}/status', [InvoiceController::class, 'updateStatus'])->where('id', '[0-9]+');
});

// Admin routes - Quản lý hóa đơn (toàn quyền)
Route::prefix('invoices')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // GET /invoices - Danh sách tất cả hóa đơn
    Route::get('/', [InvoiceController::class, 'index']);
    
    // GET /invoices/config/calculation - Lấy cấu hình tính toán
    Route::get('/config/calculation', [InvoiceController::class, 'getCalculationConfig']);
    
    // GET /invoices/config/refund-policies - Lấy chính sách hoàn tiền
    Route::get('/config/refund-policies', [InvoiceController::class, 'getRefundPolicyConfig']);
    
    // GET /invoices/statistics/overview - Thống kê hóa đơn
    Route::get('/statistics/overview', [InvoiceController::class, 'statistics']);
    
    // GET /invoices/{id} - Chi tiết hóa đơn
    Route::get('/{id}', [InvoiceController::class, 'show'])->where('id', '[0-9]+');
    
    // POST /invoices - Tạo hóa đơn mới
    Route::post('/', [InvoiceController::class, 'store']);
    
    // POST /invoices/create-from-booking - Tạo hóa đơn từ booking
    Route::post('/create-from-booking', [InvoiceController::class, 'createFromBooking']);
    
    // PUT /invoices/{id} - Cập nhật hóa đơn
    Route::put('/{id}', [InvoiceController::class, 'update'])->where('id', '[0-9]+');
    
    // POST|PATCH /invoices/{id}/mark-paid - Đánh dấu đã thanh toán
    Route::match(['post', 'patch'], '/{id}/mark-paid', [InvoiceController::class, 'markAsPaid'])->where('id', '[0-9]+');
    
    // PATCH /invoices/{id}/status - Cập nhật trạng thái
    Route::patch('/{id}/status', [InvoiceController::class, 'updateStatus'])->where('id', '[0-9]+');
    
    // POST /invoices/config/calculation - Cấu hình tính toán
    Route::post('/config/calculation', [InvoiceController::class, 'setCalculationConfig']);
    
    // POST /invoices/config/refund-policies - Tạo chính sách hoàn tiền
    Route::post('/config/refund-policies', [InvoiceController::class, 'createRefundPolicy']);
    
    // PUT /invoices/config/refund-policies/{policyId} - Cập nhật chính sách
    Route::put('/config/refund-policies/{policyId}', [InvoiceController::class, 'updateRefundPolicy'])->where('policyId', '[0-9]+');
    
    // DELETE /invoices/{id} - Xóa hóa đơn
    Route::delete('/{id}', [InvoiceController::class, 'destroy'])->where('id', '[0-9]+');
    
    // POST /invoices/merge - Gộp hóa đơn
    Route::post('/merge', [InvoiceController::class, 'mergeInvoices']);
    
    // POST /invoices/{id}/split - Tách hóa đơn
    Route::post('/{id}/split', [InvoiceController::class, 'splitInvoice'])->where('id', '[0-9]+');
    
    // POST /invoices/{id}/discounts - Áp dụng giảm giá
    Route::post('/{id}/discounts', [InvoiceController::class, 'applyDiscount'])->where('id', '[0-9]+');
    
    // DELETE /invoices/{id}/discounts/{discountId} - Xóa giảm giá
    Route::delete('/{id}/discounts/{discountId}', [InvoiceController::class, 'removeDiscount'])->where('id', '[0-9]+')->where('discountId', '[0-9]+');
    
    // POST /invoices/{id}/apply-discount - Áp dụng giảm giá
    Route::post('/{id}/apply-discount', [InvoiceController::class, 'applyDiscount'])->where('id', '[0-9]+');
    
    // POST /invoices/{id}/apply-refund-policy - Áp dụng chính sách hoàn tiền
    Route::post('/{id}/apply-refund-policy', [InvoiceController::class, 'applyRefundPolicy'])->where('id', '[0-9]+');
});

/**
 * ========================================
 * 📝 INVOICE ITEMS (Mục Hóa đơn)
 * ========================================
 */

Route::prefix('invoices/{invoiceId}/items')->group(function () {
    // GET /invoices/{invoiceId}/items - Danh sách mục hóa đơn
    Route::get('/', [InvoiceItemController::class, 'index']);
    
    // GET /invoices/{invoiceId}/items/penalties - Lấy các mục phạt
    Route::get('/penalties', [InvoiceItemController::class, 'getPenaltyItems']);
    
    // GET /invoices/{invoiceId}/items/regular - Lấy các mục thường
    Route::get('/regular', [InvoiceItemController::class, 'getRegularItems']);
    
    // POST /invoices/{invoiceId}/items/penalty - Thêm mục phạt
    Route::post('/penalty', [InvoiceItemController::class, 'addPenaltyItem']);
    
    // POST /invoices/{invoiceId}/items/regular - Thêm mục thường
    Route::post('/regular', [InvoiceItemController::class, 'addRegularItem']);
});

Route::prefix('invoice-items')->group(function () {
    // GET /invoice-items - Danh sách mục hóa đơn
    Route::get('/', [InvoiceItemController::class, 'index']);
    
    // GET /invoice-items/{id} - Chi tiết mục hóa đơn
    Route::get('/{id}', [InvoiceItemController::class, 'show'])->where('id', '[0-9]+');
    
    // POST /invoice-items - Tạo mục hóa đơn
    Route::post('/', [InvoiceItemController::class, 'store']);
    
    // PUT /invoice-items/{id} - Cập nhật mục hóa đơn
    Route::put('/{id}', [InvoiceItemController::class, 'update'])->where('id', '[0-9]+');
    
    // DELETE /invoice-items/{id} - Xóa mục hóa đơn
    Route::delete('/{id}', [InvoiceItemController::class, 'destroy'])->where('id', '[0-9]+');
    
    // POST /invoice-items/bulk/create - Tạo nhiều mục
    Route::post('/bulk/create', [InvoiceItemController::class, 'bulkCreate']);
    
    // DELETE /invoice-items/bulk/delete - Xóa nhiều mục
    Route::delete('/bulk/delete', [InvoiceItemController::class, 'bulkDelete']);
});

/**
 * ========================================
 * 🛒 SUPPLIES MANAGEMENT (Quản lý Vật tư)
 * ========================================
 */

// Staff routes - Quản lý vật tư
Route::prefix('staff/supplies')->middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
    // GET /staff/supplies - Danh sách vật tư
    Route::get('/', [SupplyController::class, 'index']);
    
    // GET /staff/supplies/{id} - Chi tiết vật tư
    Route::get('/{id}', [SupplyController::class, 'show'])->where('id', '[0-9]+');
    
    // GET /staff/supplies/low-stock/items - Vật tư sắp hết
    Route::get('/low-stock/items', [SupplyController::class, 'getLowStockItems']);
    
    // GET /staff/supplies/out-of-stock/items - Vật tư hết hàng
    Route::get('/out-of-stock/items', [SupplyController::class, 'getOutOfStockItems']);
    
    // GET /staff/supplies/statistics/overview - Thống kê vật tư
    Route::get('/statistics/overview', [SupplyController::class, 'getStatistics']);
    
    // POST /staff/supplies/{id}/adjust-stock - Điều chỉnh tồn kho
    Route::post('/{id}/adjust-stock', [SupplyController::class, 'adjustStock'])->where('id', '[0-9]+');
});

// Admin routes - Quản lý vật tư (toàn quyền)
Route::prefix('admin/supplies')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // GET /admin/supplies - Danh sách vật tư
    Route::get('/', [SupplyController::class, 'index']);
    
    // GET /admin/supplies/{id} - Chi tiết vật tư
    Route::get('/{id}', [SupplyController::class, 'show'])->where('id', '[0-9]+');
    
    // GET /admin/supplies/low-stock/items - Vật tư sắp hết
    Route::get('/low-stock/items', [SupplyController::class, 'getLowStockItems']);
    
    // GET /admin/supplies/out-of-stock/items - Vật tư hết hàng
    Route::get('/out-of-stock/items', [SupplyController::class, 'getOutOfStockItems']);
    
    // GET /admin/supplies/statistics/overview - Thống kê vật tư
    Route::get('/statistics/overview', [SupplyController::class, 'getStatistics']);
    
    // POST /admin/supplies - Tạo vật tư mới
    Route::post('/', [SupplyController::class, 'store']);
    
    // PUT /admin/supplies/{id} - Cập nhật vật tư
    Route::put('/{id}', [SupplyController::class, 'update'])->where('id', '[0-9]+');
    
    // DELETE /admin/supplies/{id} - Xóa vật tư
    Route::delete('/{id}', [SupplyController::class, 'destroy'])->where('id', '[0-9]+');
    
    // POST /admin/supplies/{id}/adjust-stock - Điều chỉnh tồn kho
    Route::post('/{id}/adjust-stock', [SupplyController::class, 'adjustStock'])->where('id', '[0-9]+');
});

/**
 * ========================================
 * 📋 SUPPLY LOGS (Lịch sử Vật tư)
 * ========================================
 */

// Staff routes - Xem lịch sử vật tư
Route::prefix('staff/supply-logs')->middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
    // GET /staff/supply-logs - Danh sách nhật ký
    Route::get('/', [SupplyLogController::class, 'index']);
    
    // GET /staff/supply-logs/activities/recent - Hoạt động gần đây
    Route::get('/activities/recent', [SupplyLogController::class, 'getRecentActivities']);
    
    // GET /staff/supply-logs/summary/movement - Tóm tắt di chuyển
    Route::get('/summary/movement', [SupplyLogController::class, 'getMovementSummary']);
    
    // GET /staff/supply-logs/supply/{supplyId} - Lịch sử vật tư
    Route::get('/supply/{supplyId}', [SupplyLogController::class, 'getSupplyLogs'])->where('supplyId', '[0-9]+');
    
    // GET /staff/supply-logs/{id} - Chi tiết nhật ký
    Route::get('/{id}', [SupplyLogController::class, 'show'])->where('id', '[0-9]+');
});

// Admin routes - Xem lịch sử vật tư
Route::prefix('admin/supply-logs')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // GET /admin/supply-logs - Danh sách nhật ký
    Route::get('/', [SupplyLogController::class, 'index']);
    
    // GET /admin/supply-logs/activities/recent - Hoạt động gần đây
    Route::get('/activities/recent', [SupplyLogController::class, 'getRecentActivities']);
    
    // GET /admin/supply-logs/summary/movement - Tóm tắt di chuyển
    Route::get('/summary/movement', [SupplyLogController::class, 'getMovementSummary']);
    
    // GET /admin/supply-logs/supply/{supplyId} - Lịch sử vật tư
    Route::get('/supply/{supplyId}', [SupplyLogController::class, 'getSupplyLogs'])->where('supplyId', '[0-9]+');
    
    // GET /admin/supply-logs/{id} - Chi tiết nhật ký
    Route::get('/{id}', [SupplyLogController::class, 'show'])->where('id', '[0-9]+');
});

/**
 * ========================================
 * 🎉 PROMOTIONS MANAGEMENT (Quản lý Khuyến mãi)
 * ========================================
 */

// Public routes - Xem khuyến mãi (cho khách hàng)
Route::prefix('promotions')->group(function () {
    // GET /promotions - Danh sách khuyến mãi công khai
    Route::get('/', [PromotionController::class, 'index']);
    
    // POST /promotions/validate - Kiểm tra mã khuyến mãi (public)
    Route::post('/validate', [PromotionController::class, 'validate']);
    
    // GET /promotions/active - Khuyến mãi đang hoạt động (public)
    Route::get('/active', [PromotionController::class, 'activePromotions']);
    
    // GET /promotions/{id} - Chi tiết khuyến mãi (public)
    Route::get('/{id}', [PromotionController::class, 'show'])->where('id', '[0-9]+');
});

// Staff routes - Xem thống kê khuyến mãi
Route::prefix('staff/promotions')->middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
    // GET /staff/promotions/statistics/overview - Thống kê khuyến mãi
    Route::get('/statistics/overview', [PromotionController::class, 'statistics']);
});

// Admin routes - Quản lý khuyến mãi
Route::prefix('admin/promotions')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // GET /admin/promotions - Danh sách khuyến mãi
    Route::get('/', [PromotionController::class, 'index']);
    
    // GET /admin/promotions/{id} - Chi tiết khuyến mãi
    Route::get('/{id}', [PromotionController::class, 'show'])->where('id', '[0-9]+');
    
    // GET /admin/promotions/statistics/overview - Thống kê khuyến mãi
    Route::get('/statistics/overview', [PromotionController::class, 'statistics']);
    
    // POST /admin/promotions - Tạo khuyến mãi mới
    Route::post('/', [PromotionController::class, 'store']);
    
    // PUT /admin/promotions/{id} - Cập nhật khuyến mãi
    Route::put('/{id}', [PromotionController::class, 'update'])->where('id', '[0-9]+');
    
    // DELETE /admin/promotions/{id} - Xóa khuyến mãi
    Route::delete('/{id}', [PromotionController::class, 'destroy'])->where('id', '[0-9]+');
});

/**
 * ========================================
 * ⭐ REVIEWS MANAGEMENT (Quản lý Đánh giá)
 * ========================================
 */

// Review Management Routes
Route::prefix('reviews')->group(function () {
    /**
     * ✅ PUBLIC READ Operations (Ai cũng xem được)
     * Không cần Token
     */
    // GET /reviews - Danh sách đánh giá công khai (có phân trang)
    Route::get('/', [ReviewController::class, 'index']);
    
    // GET /reviews/property/{propertyId} - Danh sách đánh giá theo căn hộ
    Route::get('/property/{propertyId}', [ReviewController::class, 'getPropertyReviews']);
    
    // GET /reviews/room/{roomId} - Danh sách đánh giá theo phòng
    Route::get('/room/{roomId}', [ReviewController::class, 'getRoomReviews']);
    
    // GET /reviews/{id} - Chi tiết đánh giá
    Route::get('/{id}', [ReviewController::class, 'show'])->where('id', '[0-9]+');
    
    /**
     * ✅ PROTECTED READ Operations
     * Cần: Bearer Token + Role: Staff hoặc Admin
     */
    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        // GET /reviews/statistics/overview - Thống kê đánh giá
        Route::get('/statistics/overview', [ReviewController::class, 'statistics']);
    });
    
    /**
     * ✍️ WRITE Operations - Authenticated Users (User/Staff/Admin)
     * Cần: Bearer Token
     */
    Route::middleware('auth:sanctum')->group(function () {
        // POST /reviews - Tạo đánh giá mới
        Route::post('/', [ReviewController::class, 'store']);
        
        // PUT /reviews/{id} - Cập nhật đánh giá (chỉ người tạo)
        Route::put('/{id}', [ReviewController::class, 'update'])->where('id', '[0-9]+');
        
        // DELETE /reviews/{id} - Xóa đánh giá (chỉ người tạo hoặc admin)
        Route::delete('/{id}', [ReviewController::class, 'destroy'])->where('id', '[0-9]+');
        
        // POST /reviews/{id}/mark-helpful - Đánh dấu đánh giá là hữu ích
        Route::post('/{id}/mark-helpful', [ReviewController::class, 'markHelpful'])->where('id', '[0-9]+');
        
        // POST /reviews/{id}/mark-not-helpful - Đánh dấu đánh giá không hữu ích
        Route::post('/{id}/mark-not-helpful', [ReviewController::class, 'markNotHelpful'])->where('id', '[0-9]+');
    });
    
    /**
     * 🔒 ADMIN ONLY Operations
     * Cần: Bearer Token + Role: Admin
     */
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        // POST /reviews/{id}/approve - Phê duyệt đánh giá
        Route::post('/{id}/approve', [ReviewController::class, 'approve'])->where('id', '[0-9]+');
        
        // POST /reviews/{id}/reject - Từ chối đánh giá
        Route::post('/{id}/reject', [ReviewController::class, 'reject'])->where('id', '[0-9]+');
    });
});
