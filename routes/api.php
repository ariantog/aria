<?php

use App\Http\Controllers\Mobile\MobileAddrbookController;
use App\Http\Controllers\Mobile\MobileAuthController;
use App\Http\Controllers\Mobile\MobileItemController;
use App\Http\Controllers\Mobile\MobileTransactionController;
use App\Http\Controllers\Mobile\MobileUserController;
use App\Http\Controllers\Readonly\ReadonlyItemExportController;
use App\Http\Controllers\Readonly\ReadonlyStockExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.readonly-export', 'active', 'can:export-readonly', 'enforce.readonly', 'throttle:readonly-export'])->group(function (): void {
    Route::get('readonly/items', [ReadonlyItemExportController::class, 'indexJson'])
        ->name('api.readonly.items');
    Route::get('readonly/items/{item}/cost-history', [ReadonlyItemExportController::class, 'costHistory'])
        ->whereNumber('item')
        ->name('api.readonly.items.cost-history');
    Route::get('readonly/stock', [ReadonlyStockExportController::class, 'index'])
        ->name('api.readonly.stock');
});

Route::prefix('mobile/v1')->group(function (): void {
    Route::post('auth/login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'active.api'])->group(function (): void {
        Route::post('auth/logout', [MobileAuthController::class, 'logout']);
        Route::get('me', [MobileUserController::class, 'show']);

        Route::get('items/by-code', [MobileItemController::class, 'byCode']);
        Route::get('items/by-id', [MobileItemController::class, 'byId']);
        Route::get('items/barcode-lookup', [MobileItemController::class, 'barcodeLookup']);

        Route::get('addrbook/{type}', [MobileAddrbookController::class, 'search'])
            ->whereIn('type', ['sell', 'move', 'buy', 'return', 'return-supplier', 'cash-in', 'cash-out', 'transfer']);

        Route::middleware(['prevent.duplicate'])->group(function (): void {
            Route::post('transactions/item', [MobileTransactionController::class, 'storeItem']);
            Route::post('transactions/cash-in', [MobileTransactionController::class, 'storeCashIn']);
            Route::post('transactions/cash-out', [MobileTransactionController::class, 'storeCashOut']);
            Route::post('transactions/transfer', [MobileTransactionController::class, 'storeTransfer']);
        });
    });
});
