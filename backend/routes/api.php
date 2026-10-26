<?php

use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\ProductionSheetController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\StudioController;
use App\Support\CatalogRegistry;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

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

Route::get('/assets/{asset}/file', [AssetController::class, 'file'])
    ->middleware('signed')
    ->name('assets.file');

Route::post('/payments/webhook/{provider}', [PaymentController::class, 'webhook'])
    ->middleware('throttle:60,1')
    ->name('payments.webhook');

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
