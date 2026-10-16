<?php

use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
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
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('role:customer')->group(function () {
        Route::post('/checkout', [OrderController::class, 'store'])->name('checkout');
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
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
