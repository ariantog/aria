<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('dashboard');
})->middleware(['auth', 'verified', 'active'])->name('dashboard');

Route::middleware(['auth', 'active'])->get('/banned', function () {
    if (request()->user()->is_active) {
        return redirect('dashboard');
    }

    return Inertia::render('auth/Banned');
})->withoutMiddleware(['active'])->name('banned');

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::get('/permissions', [App\Http\Controllers\PermissionController::class, 'index'])->name('permissions.index');
    Route::post('/permissions/generate', [App\Http\Controllers\PermissionController::class, 'generate'])->name('permissions.generate');
    Route::resource('roles', App\Http\Controllers\RoleController::class);
    Route::resource('users', \App\Http\Controllers\UserController::class);
    Route::resource('locations', \App\Http\Controllers\LocationController::class);
    Route::resource('posts', App\Http\Controllers\PostController::class);
    Route::resource('items', App\Http\Controllers\ItemsController::class);
    Route::resource('addrbook', App\Http\Controllers\AddrbookController::class);
    Route::resource('system-settings', App\Http\Controllers\SettingController::class)->except(['show']);

    // Dynamic Addrbook Type Routes (e.g., /customer, /supplier)
    $addrbookTypes = implode('|', array_column(\App\Models\Addrbook::getTypes(), 'slug'));

    Route::get('/{type}', [App\Http\Controllers\AddrbookController::class, 'index'])
        ->where('type', $addrbookTypes)
        ->name('addrbook.type.index');

    Route::get('/{type}/create', [App\Http\Controllers\AddrbookController::class, 'create'])
        ->where('type', $addrbookTypes)
        ->name('addrbook.type.create');

    Route::get('/{type}/{addrbook}', [App\Http\Controllers\AddrbookController::class, 'showType'])
        ->where('type', $addrbookTypes)
        ->name('addrbook.type.show');

    Route::get('/{type}/{addrbook}/edit', [App\Http\Controllers\AddrbookController::class, 'editType'])
        ->where('type', $addrbookTypes)
        ->name('addrbook.type.edit');

    // Asset Lancar Routes
    Route::get('assetlancar', [App\Http\Controllers\ItemsController::class, 'indexAsset'])->name('assetlancar.index');
    Route::get('assetlancar/create', [App\Http\Controllers\ItemsController::class, 'createAsset'])->name('assetlancar.create');
    Route::post('assetlancar', [App\Http\Controllers\ItemsController::class, 'store'])->name('assetlancar.store');
    Route::get('assetlancar/{item}', [App\Http\Controllers\ItemsController::class, 'show'])->name('assetlancar.show');
    Route::get('assetlancar/{item}/edit', [App\Http\Controllers\ItemsController::class, 'edit'])->name('assetlancar.edit');
    Route::put('assetlancar/{item}', [App\Http\Controllers\ItemsController::class, 'update'])->name('assetlancar.update');
    Route::delete('assetlancar/{item}', [App\Http\Controllers\ItemsController::class, 'destroy'])->name('assetlancar.destroy');

    // Transactions Routes
    Route::get('transactions', [App\Http\Controllers\TransactionsController::class, 'index'])->name('transactions.index');
    Route::post('transactions', [App\Http\Controllers\TransactionsController::class, 'store'])->name('transactions.store');

    Route::get('transactions/cash-in', [App\Http\Controllers\TransactionsController::class, 'cashIn'])->name('transactions.cash-in');
    Route::post('transactions/cash-in', [App\Http\Controllers\TransactionsController::class, 'storeCashIn'])->name('transactions.cash-in.store');

    Route::get('transactions/cash-out', [App\Http\Controllers\TransactionsController::class, 'cashOut'])->name('transactions.cash-out');
    Route::post('transactions/cash-out', [App\Http\Controllers\TransactionsController::class, 'storeCashOut'])->name('transactions.cash-out.store');

    Route::get('transactions/transfer', [App\Http\Controllers\TransactionsController::class, 'transfer'])->name('transactions.transfer');
    Route::post('transactions/transfer', [App\Http\Controllers\TransactionsController::class, 'storeTransfer'])->name('transactions.transfer.store');

    Route::get('transactions/adjust', [App\Http\Controllers\TransactionsController::class, 'adjust'])->name('transactions.adjust');
    Route::post('transactions/adjust', [App\Http\Controllers\TransactionsController::class, 'storeAdjust'])->name('transactions.adjust.store');

    Route::get('transactions/{transaction}', [App\Http\Controllers\TransactionsController::class, 'show'])->name('transactions.show');
    Route::delete('transactions/{transaction}', [App\Http\Controllers\TransactionsController::class, 'destroy'])->name('transactions.destroy');

    Route::get('transactions/{type}/create', [App\Http\Controllers\TransactionsController::class, 'create'])->name('transactions.create');
    Route::get('transactions/{type}/lookup/{role}', [App\Http\Controllers\TransactionLookupController::class, 'search'])->name('transactions.lookup');
    Route::get('tags/lookup', [App\Http\Controllers\TagLookupController::class, 'search'])->name('tags.lookup');
});

require __DIR__.'/settings.php';
