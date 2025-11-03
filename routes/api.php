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

// Authentication Routes (public)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Invoice Management Routes
Route::prefix('invoices')->group(function () {
    // READ operations - Auth required
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::get('/config/calculation', [InvoiceController::class, 'getCalculationConfig']);
        Route::get('/config/refund-policies', [InvoiceController::class, 'getRefundPolicyConfig']);
        Route::get('/statistics/overview', [InvoiceController::class, 'statistics']);
        Route::get('/{id}', [InvoiceController::class, 'show'])->where('id', '[0-9]+');
    });
    
    // WRITE operations - Staff & Admin only
    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::post('/', [InvoiceController::class, 'store']);
        Route::post('/create-from-booking', [InvoiceController::class, 'createFromBooking']);
        Route::put('/{id}', [InvoiceController::class, 'update'])->where('id', '[0-9]+');
        Route::match(['post', 'patch'], '/{id}/mark-paid', [InvoiceController::class, 'markAsPaid'])->where('id', '[0-9]+');
        Route::patch('/{id}/status', [InvoiceController::class, 'updateStatus'])->where('id', '[0-9]+');
    });
    
    // ADMIN only operations
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::post('/config/calculation', [InvoiceController::class, 'setCalculationConfig']);
        Route::post('/config/refund-policies', [InvoiceController::class, 'createRefundPolicy']);
        Route::put('/config/refund-policies/{policyId}', [InvoiceController::class, 'updateRefundPolicy'])->where('policyId', '[0-9]+');
        Route::delete('/{id}', [InvoiceController::class, 'destroy'])->where('id', '[0-9]+');
        Route::post('/merge', [InvoiceController::class, 'mergeInvoices']);
        Route::post('/{id}/split', [InvoiceController::class, 'splitInvoice'])->where('id', '[0-9]+');
        Route::post('/{id}/discounts', [InvoiceController::class, 'applyDiscount'])->where('id', '[0-9]+');
        Route::delete('/{id}/discounts/{discountId}', [InvoiceController::class, 'removeDiscount'])->where('id', '[0-9]+')->where('discountId', '[0-9]+');
        Route::post('/{id}/apply-discount', [InvoiceController::class, 'applyDiscount'])->where('id', '[0-9]+');
        Route::post('/{id}/apply-refund-policy', [InvoiceController::class, 'applyRefundPolicy'])->where('id', '[0-9]+');
    });
});

// Invoice Items Routes (Nested under invoices)
Route::prefix('invoices/{invoiceId}/items')->group(function () {
    // READ operations - Auth required
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [InvoiceItemController::class, 'index']);
        Route::get('/penalties', [InvoiceItemController::class, 'getPenaltyItems']);
        Route::get('/regular', [InvoiceItemController::class, 'getRegularItems']);
    });
    
    // WRITE operations - Staff & Admin required
    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::post('/penalty', [InvoiceItemController::class, 'addPenaltyItem']);
        Route::post('/regular', [InvoiceItemController::class, 'addRegularItem']);
    });
});

Route::prefix('invoice-items')->group(function () {
    // READ operations - Auth required
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [InvoiceItemController::class, 'index']);
        Route::get('/{id}', [InvoiceItemController::class, 'show'])->where('id', '[0-9]+');
    });
    
    // WRITE operations - Staff/Admin required
    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::post('/', [InvoiceItemController::class, 'store']);
        Route::put('/{id}', [InvoiceItemController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [InvoiceItemController::class, 'destroy'])->where('id', '[0-9]+');
    });
    
    // ADMIN only operations
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::post('/bulk/create', [InvoiceItemController::class, 'bulkCreate']);
        Route::delete('/bulk/delete', [InvoiceItemController::class, 'bulkDelete']);
    });
});

// Supply Management Routes
Route::prefix('supplies')->group(function () {
    // READ operations - Public (list only)
    Route::get('/', [SupplyController::class, 'index']);
    Route::get('/{id}', [SupplyController::class, 'show'])->where('id', '[0-9]+');
    
    // READ operations - Staff & Admin required
    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::get('/low-stock/items', [SupplyController::class, 'getLowStockItems']);
        Route::get('/out-of-stock/items', [SupplyController::class, 'getOutOfStockItems']);
        Route::get('/statistics/overview', [SupplyController::class, 'getStatistics']);
    });
    
    // WRITE operations - Staff & Admin only
    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::post('/', [SupplyController::class, 'store']);
        Route::put('/{id}', [SupplyController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [SupplyController::class, 'destroy'])->where('id', '[0-9]+');
        Route::post('/{id}/adjust-stock', [SupplyController::class, 'adjustStock'])->where('id', '[0-9]+');
    });
});

// Supply Logs Routes - Auth & Staff required (READ only)
Route::prefix('supply-logs')->middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
    Route::get('/', [SupplyLogController::class, 'index']);
    Route::get('/activities/recent', [SupplyLogController::class, 'getRecentActivities']);
    Route::get('/summary/movement', [SupplyLogController::class, 'getMovementSummary']);
    Route::get('/supply/{supplyId}', [SupplyLogController::class, 'getSupplyLogs']);
    Route::get('/{id}', [SupplyLogController::class, 'show'])->where('id', '[0-9]+');
});

// Promotion Management Routes
Route::prefix('promotions')->group(function () {
    // READ operations - Public & Auth
    Route::get('/', [PromotionController::class, 'index']);
    Route::post('/validate', [PromotionController::class, 'validate']);
    Route::get('/active', [PromotionController::class, 'activePromotions']);
    Route::get('/{id}', [PromotionController::class, 'show'])->where('id', '[0-9]+');
    
    // Auth required read
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/statistics/overview', [PromotionController::class, 'statistics']);
    });
    
    // WRITE operations - Staff & Admin only
    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::post('/', [PromotionController::class, 'store']);
        Route::put('/{id}', [PromotionController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [PromotionController::class, 'destroy'])->where('id', '[0-9]+');
    });
});

// Review Management Routes
Route::prefix('reviews')->group(function () {
    // READ operations - Public
    Route::get('/', [ReviewController::class, 'index']);
    Route::get('/property/{propertyId}', [ReviewController::class, 'getPropertyReviews']);
    Route::get('/room/{roomId}', [ReviewController::class, 'getRoomReviews']);
    Route::get('/{id}', [ReviewController::class, 'show'])->where('id', '[0-9]+');
    
    // READ statistics - Staff & Admin required
    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::get('/statistics/overview', [ReviewController::class, 'statistics']);
    });
    
    // Auth required operations (create/update/delete by user)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [ReviewController::class, 'store']);
        Route::put('/{id}', [ReviewController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [ReviewController::class, 'destroy'])->where('id', '[0-9]+');
        Route::post('/{id}/mark-helpful', [ReviewController::class, 'markHelpful'])->where('id', '[0-9]+');
        Route::post('/{id}/mark-not-helpful', [ReviewController::class, 'markNotHelpful'])->where('id', '[0-9]+');
    });
    
    // ADMIN only operations
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::post('/{id}/approve', [ReviewController::class, 'approve'])->where('id', '[0-9]+');
        Route::post('/{id}/reject', [ReviewController::class, 'reject'])->where('id', '[0-9]+');
    });
});
