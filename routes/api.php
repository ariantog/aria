<?php

use App\Http\Controllers\Readonly\ReadonlyItemExportController;
use App\Http\Controllers\Readonly\ReadonlyStockExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active', 'can:export-readonly', 'enforce.readonly', 'throttle:readonly-export'])->group(function (): void {
    Route::get('readonly/items', [ReadonlyItemExportController::class, 'indexJson'])
        ->name('api.readonly.items');
    Route::get('readonly/items/{item}/cost-history', [ReadonlyItemExportController::class, 'costHistory'])
        ->whereNumber('item')
        ->name('api.readonly.items.cost-history');
    Route::get('readonly/stock', [ReadonlyStockExportController::class, 'index'])
        ->name('api.readonly.stock');
});
