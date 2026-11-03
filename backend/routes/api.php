<?php

use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\ProductionSheetController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProofController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\StudioController;
use App\Http\Controllers\StudioEventController;
use App\Support\CatalogRegistry;
use Illuminate\Support\Facades\Route;

// Public account entry points. Registration always assigns the customer role.
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Catalog browsing, draft checks, quotes, and inspiration uploads support guests where appropriate.
Route::prefix('studio')->name('studio.')->middleware('throttle:30,1')->group(function () {
    Route::get('/catalog', [StudioController::class, 'catalog'])->name('catalog');
    Route::post('/validate', [StudioController::class, 'validateConfiguration'])->name('validate');
    Route::post('/quote', [StudioController::class, 'quote'])
        ->middleware('optional-auth')
        ->name('quote');
    Route::post('/assets', [AssetController::class, 'store'])
        ->middleware(['optional-auth', 'throttle:10,1'])
        ->name('assets.store');
});
Route::post('/studio/events', [StudioEventController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('studio.events');

Route::get('/assets/{asset}/file', [AssetController::class, 'file'])
    ->middleware('signed')
    ->name('assets.file');

Route::post('/payments/webhook/{provider}', [PaymentController::class, 'webhook'])
    ->middleware('throttle:60,1')
    ->name('payments.webhook');

// Customer and staff operations require a token; nested role and policy checks limit access further.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('role:customer')->group(function () {
        Route::post('/checkout', [OrderController::class, 'store'])->name('checkout');
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/payments', [PaymentController::class, 'store'])->name('orders.payments');
        Route::post('/orders/{order}/cancel', [PaymentController::class, 'cancel'])->name('orders.cancel');
        Route::get('/orders/{order}/timeline', [OrderController::class, 'timeline'])->name('orders.timeline');
        Route::post('/orders/{order}/reorder', [OrderController::class, 'reorder'])->name('orders.reorder');
        Route::get('/orders/{order}/proof', [ProofController::class, 'show'])->name('orders.proof');
        Route::post('/orders/{order}/proof/approve', [ProofController::class, 'approve'])->name('orders.proof.approve');
        Route::post('/orders/{order}/proof/request-changes', [ProofController::class, 'requestChanges'])->name('orders.proof.changes');
        Route::post('/orders/{order}/refund-requests', [RefundController::class, 'store'])->name('orders.refunds.store');
        Route::get('/orders/{order}/refund-requests', [RefundController::class, 'index'])->name('orders.refunds.index');
        Route::post('/refund-evidence', [RefundController::class, 'uploadEvidence'])->name('refund-evidence.store');
    });

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/reports/overview', [ReportController::class, 'overview']);
        Route::get('/reports/estimates', [ReportController::class, 'estimates']);
    });

    Route::prefix('admin')->middleware('role:admin,producer')->group(function () {
        Route::get('/production-sheets', [ProductionSheetController::class, 'index']);
        Route::get('/production-sheets/batches', [ProductionSheetController::class, 'batches']);
        Route::get('/production-sheets/{item}', [ProductionSheetController::class, 'show'])->whereNumber('item');
        Route::get('/production-sheets/{item}/print', [ProductionSheetController::class, 'print'])->whereNumber('item');
        Route::patch('/production-sheets/{item}/checklist', [ProductionSheetController::class, 'checklist'])->whereNumber('item');
        Route::post('/order-items/{item}/transition', [ProductionSheetController::class, 'transition'])->whereNumber('item');
        Route::post('/order-items/{item}/attention', [ProductionSheetController::class, 'setAttention'])->whereNumber('item');
        Route::delete('/order-items/{item}/attention', [ProductionSheetController::class, 'clearAttention'])->whereNumber('item');
        Route::post('/order-items/{item}/qc-photos', [ProductionSheetController::class, 'qcPhotos'])->whereNumber('item');
        Route::post('/order-items/{item}/proof/revise', [ProofController::class, 'revise'])->whereNumber('item');
        Route::get('/calendar', [CalendarController::class, 'index']);
        Route::get('/blackout-dates', [CalendarController::class, 'blackouts']);
        Route::post('/blackout-dates', [CalendarController::class, 'storeBlackout']);
        Route::put('/blackout-dates/{blackoutDate}', [CalendarController::class, 'updateBlackout'])->whereNumber('blackoutDate');
        Route::patch('/blackout-dates/{blackoutDate}', [CalendarController::class, 'updateBlackout'])->whereNumber('blackoutDate');
        Route::delete('/blackout-dates/{blackoutDate}', [CalendarController::class, 'deleteBlackout'])->whereNumber('blackoutDate');
        Route::get('/refund-requests', [RefundController::class, 'adminIndex']);
        Route::post('/refund-requests/{refund}/approve', [RefundController::class, 'approve'])->whereNumber('refund');
        Route::post('/refund-requests/{refund}/reject', [RefundController::class, 'reject'])->whereNumber('refund');
    });

    Route::prefix('admin/catalog')->name('admin.catalog.')->group(function () {
        Route::middleware('role:admin')->group(function () {
            foreach (array_keys(CatalogRegistry::definitions()) as $catalogType) {
                Route::apiResource($catalogType, CatalogController::class)
                    ->parameters([$catalogType => 'id'])
                    ->names($catalogType)
                    ->except([]);
            }
        });

        Route::middleware('role:admin,producer')->group(function () {
            foreach (CatalogRegistry::definitions() as $catalogType => $definition) {
                if ($definition['availability'] !== null) {
                    Route::patch($catalogType.'/{id}/availability', [CatalogController::class, 'availability'])
                        ->whereNumber('id')
                        ->name($catalogType.'.availability');
                }
            }
        });
    });
});
