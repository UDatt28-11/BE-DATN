
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\User\AuthController as UserAuthController;
use App\Http\Controllers\Staff\AuthController as StaffAuthController;
use App\Http\Controllers\User\ResetPasswordController;
use App\Http\Controllers\User\VerifyEmailController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceItemController;
use App\Http\Controllers\SupplyController;
use App\Http\Controllers\SupplyLogController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\UserManagementController;

Route::prefix('admin')->group(function () {
    Route::post('login', [AdminAuthController::class, 'login']);
    // Google 
    Route::get('google/redirect', [GoogleController::class, 'redirectToGoogle'])->defaults('role', 'admin');
    Route::get('google/callback', [GoogleController::class, 'handleGoogleCallback'])->defaults('role', 'admin');

    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::post('logout', [AdminAuthController::class, 'logout']);
        // thêm các route quản lý admin
    });
});


Route::prefix('staff')->group(function () {
    Route::post('register', [StaffAuthController::class, 'register']);
    Route::post('login', [StaffAuthController::class, 'login']);
    // Google
    Route::get('google/redirect', [GoogleController::class, 'redirectToGoogle'])->defaults('role', 'staff');
    Route::get('google/callback', [GoogleController::class, 'handleGoogleCallback'])->defaults('role', 'staff');

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
    //  Đăng nhập bằng Google (user)
    Route::get('google/redirect', [GoogleController::class, 'redirectToGoogle'])->defaults('role', 'user');
    Route::get('google/callback', [GoogleController::class, 'handleGoogleCallback'])->defaults('role', 'user');

    Route::middleware(['auth:sanctum', 'role:user'])->group(function () {
        Route::post('logout', [UserAuthController::class, 'logout']);
        // thêm route người dùng (giỏ hàng, đơn hàng, ...)
    });
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Health check
Route::get('/ping', function () {
    return response()->json(['ok' => true, 'time' => now()->toISOString()]);
});

/*
|--------------------------------------------------------------------------
| Users Management
|--------------------------------------------------------------------------
*/
Route::prefix('users')->group(function () {
    // Admin only (read and write)
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::get('/', [UserManagementController::class, 'index']);
        Route::get('/{id}', [UserManagementController::class, 'show'])->where('id', '[0-9]+');
        Route::post('/', [UserManagementController::class, 'store']);
        Route::put('/{id}', [UserManagementController::class, 'update'])->where('id', '[0-9]+');
        Route::patch('/{id}/status', [UserManagementController::class, 'updateStatus'])->where('id', '[0-9]+');
        Route::patch('/{id}/role', [UserManagementController::class, 'updateRole'])->where('id', '[0-9]+');
        Route::delete('/{id}', [UserManagementController::class, 'destroy'])->where('id', '[0-9]+');
    });
});

/*
|--------------------------------------------------------------------------
| Invoice Routes
|--------------------------------------------------------------------------
*/
Route::prefix('invoices')->group(function () {
    // Authenticated read
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::get('/config/calculation', [InvoiceController::class, 'getCalculationConfig']);
        Route::get('/config/refund-policies', [InvoiceController::class, 'getRefundPolicyConfig']);
        Route::get('/statistics/overview', [InvoiceController::class, 'statistics']);
        Route::get('/{id}', [InvoiceController::class, 'show'])->where('id', '[0-9]+');
    });

    // Staff/Admin write
    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::post('/', [InvoiceController::class, 'store']);
        Route::post('/create-from-booking', [InvoiceController::class, 'createFromBooking']);
        Route::put('/{id}', [InvoiceController::class, 'update'])->where('id', '[0-9]+');
        Route::match(['post', 'patch'], '/{id}/mark-paid', [InvoiceController::class, 'markAsPaid'])->where('id', '[0-9]+');
        Route::patch('/{id}/status', [InvoiceController::class, 'updateStatus'])->where('id', '[0-9]+');
    });

    // Admin only
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

// Invoice Items nested
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

// InvoiceItem general
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

/*
|--------------------------------------------------------------------------
| Supplies & Supply Logs
|--------------------------------------------------------------------------
*/
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

Route::prefix('supply-logs')->middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
    Route::get('/', [SupplyLogController::class, 'index']);
    Route::get('/activities/recent', [SupplyLogController::class, 'getRecentActivities']);
    Route::get('/summary/movement', [SupplyLogController::class, 'getMovementSummary']);
    Route::get('/supply/{supplyId}', [SupplyLogController::class, 'getSupplyLogs']);
    Route::get('/{id}', [SupplyLogController::class, 'show'])->where('id', '[0-9]+');
});

/*
|--------------------------------------------------------------------------
| Promotions
|--------------------------------------------------------------------------
*/
Route::prefix('promotions')->group(function () {
    Route::get('/', [PromotionController::class, 'index']);
    Route::post('/validate', [PromotionController::class, 'validate']);
    Route::get('/active', [PromotionController::class, 'activePromotions']);
    Route::get('/{id}', [PromotionController::class, 'show'])->where('id', '[0-9]+');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/statistics/overview', [PromotionController::class, 'statistics']);
    });

    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::post('/', [PromotionController::class, 'store']);
        Route::put('/{id}', [PromotionController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [PromotionController::class, 'destroy'])->where('id', '[0-9]+');
    });
});

/*
|--------------------------------------------------------------------------
| Reviews
|--------------------------------------------------------------------------
*/
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
