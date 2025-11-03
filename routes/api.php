<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceItemController;
use App\Http\Controllers\SupplyController;
use App\Http\Controllers\SupplyLogController;

// Invoice Management Routes
Route::prefix('invoices')->group(function () {
    Route::get('/', [InvoiceController::class, 'index']);
    Route::post('/', [InvoiceController::class, 'store']);
    Route::post('/create-from-booking', [InvoiceController::class, 'createFromBooking']);
    Route::get('/config/calculation', [InvoiceController::class, 'getCalculationConfig']);
    Route::post('/config/calculation', [InvoiceController::class, 'setCalculationConfig']);
    Route::get('/config/refund-policies', [InvoiceController::class, 'getRefundPolicyConfig']);
    Route::post('/config/refund-policies', [InvoiceController::class, 'createRefundPolicy']);
    Route::put('/config/refund-policies/{policyId}', [InvoiceController::class, 'updateRefundPolicy'])->where('policyId', '[0-9]+');
    Route::get('/statistics/overview', [InvoiceController::class, 'statistics']);
    Route::post('/merge', [InvoiceController::class, 'mergeInvoices']);
    Route::post('/{id}/split', [InvoiceController::class, 'splitInvoice'])->where('id', '[0-9]+');
    Route::get('/{id}', [InvoiceController::class, 'show'])->where('id', '[0-9]+');
    Route::put('/{id}', [InvoiceController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/{id}', [InvoiceController::class, 'destroy'])->where('id', '[0-9]+');
    Route::match(['post', 'patch'], '/{id}/mark-paid', [InvoiceController::class, 'markAsPaid'])->where('id', '[0-9]+');
    Route::patch('/{id}/status', [InvoiceController::class, 'updateStatus'])->where('id', '[0-9]+');
    Route::post('/{id}/discounts', [InvoiceController::class, 'applyDiscount'])->where('id', '[0-9]+');
    Route::delete('/{id}/discounts/{discountId}', [InvoiceController::class, 'removeDiscount'])->where('id', '[0-9]+')->where('discountId', '[0-9]+');
    Route::post('/{id}/apply-discount', [InvoiceController::class, 'applyDiscount'])->where('id', '[0-9]+');
    Route::post('/{id}/apply-refund-policy', [InvoiceController::class, 'applyRefundPolicy'])->where('id', '[0-9]+');
});

// Invoice Items Routes
Route::prefix('invoices/{invoiceId}/items')->group(function () {
    Route::get('/', [InvoiceItemController::class, 'index']);
    Route::post('/penalty', [InvoiceItemController::class, 'addPenaltyItem']);
    Route::post('/regular', [InvoiceItemController::class, 'addRegularItem']);
    Route::get('/penalties', [InvoiceItemController::class, 'getPenaltyItems']);
    Route::get('/regular', [InvoiceItemController::class, 'getRegularItems']);
});

Route::prefix('invoice-items')->group(function () {
    Route::get('/{id}', [InvoiceItemController::class, 'show'])->where('id', '[0-9]+');
    Route::put('/{id}', [InvoiceItemController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/{id}', [InvoiceItemController::class, 'destroy'])->where('id', '[0-9]+');
});

// Supply Management Routes
Route::prefix('supplies')->group(function () {
    Route::get('/', [SupplyController::class, 'index']);
    Route::post('/', [SupplyController::class, 'store']);
    Route::get('/low-stock/items', [SupplyController::class, 'getLowStockItems']);
    Route::get('/out-of-stock/items', [SupplyController::class, 'getOutOfStockItems']);
    Route::get('/statistics/overview', [SupplyController::class, 'getStatistics']);
    Route::get('/{id}', [SupplyController::class, 'show'])->where('id', '[0-9]+');
    Route::put('/{id}', [SupplyController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/{id}', [SupplyController::class, 'destroy'])->where('id', '[0-9]+');
    Route::post('/{id}/adjust-stock', [SupplyController::class, 'adjustStock'])->where('id', '[0-9]+');
});

// Supply Logs Routes
Route::prefix('supply-logs')->group(function () {
    Route::get('/', [SupplyLogController::class, 'index']);
    Route::get('/activities/recent', [SupplyLogController::class, 'getRecentActivities']);
    Route::get('/summary/movement', [SupplyLogController::class, 'getMovementSummary']);
    Route::get('/supply/{supplyId}', [SupplyLogController::class, 'getSupplyLogs']);
    Route::get('/{id}', [SupplyLogController::class, 'show'])->where('id', '[0-9]+');
});
