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

    // Item Group Routes
    Route::get('items-group', [App\Http\Controllers\ItemsController::class, 'group'])->name('items.group');
    Route::get('items-group/{group}', [App\Http\Controllers\ItemsController::class, 'groupDetail'])->name('items.group-detail');
    Route::get('items-group/{group}/stats', [App\Http\Controllers\ItemsController::class, 'groupStats'])->name('items.group-stats');

    // Item Stats & Transactions
    Route::get('items/{item}/transactions', [App\Http\Controllers\ItemsController::class, 'itemTransactions'])->name('items.transactions');
    Route::get('items/{item}/stats', [App\Http\Controllers\ItemsController::class, 'itemStats'])->name('items.stats');

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
    Route::resource('tags', \App\Http\Controllers\Stuff\TagController::class);
    // Journal Module
    Route::resource('journals/operations', \App\Http\Controllers\Journal\OperationController::class);
    Route::resource('journals/account-list', \App\Http\Controllers\Journal\AccountListController::class)->parameters([
        'account-list' => 'account_list',
    ]);
    Route::get('journals/account-list/{account_list}/ledger', [\App\Http\Controllers\Journal\AccountListController::class, 'ledger'])->name('account-list.ledger');

    // Production Module
    Route::prefix('produksi')->name('produksi.')->group(function () {
        Route::get('/', [App\Http\Controllers\ProduksiController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\ProduksiController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\ProduksiController::class, 'store'])->name('store');
        Route::get('/workers/lookup', [App\Http\Controllers\ProduksiController::class, 'workerLookup'])->name('workers.lookup');

        // Assign Jahit to Production Entry
        Route::patch('/{produksi}/jahit', [App\Http\Controllers\ProduksiController::class, 'postSaveRow'])->name('assign-jahit');

        // Assign QC to Production Entry
        Route::patch('/{produksi}/qc', [App\Http\Controllers\ProduksiController::class, 'postSaveQc'])->name('assign-qc');

        Route::patch('/{produksi}/setor', [App\Http\Controllers\ProduksiController::class, 'postSetor'])->name('setor');
        Route::get('/setoran', [App\Http\Controllers\ProduksiController::class, 'setoranIndex'])->name('setoran.index');
        Route::get('/setoran/{produksi}/edit', [App\Http\Controllers\ProduksiController::class, 'setoranEdit'])->name('setoran.edit');
        Route::patch('/setoran/{produksi}/edit-item', [App\Http\Controllers\ProduksiController::class, 'setoranEditItem'])->name('setoran.edit-item');
        Route::patch('/setoran/{produksi}/gudang', [App\Http\Controllers\ProduksiController::class, 'setoranGudang'])->name('setoran.gudang');
        Route::patch('/setoran/{produksi}/status-produksi', [App\Http\Controllers\ProduksiController::class, 'setoranStatusToProduksi'])->name('setoran.status-produksi');

        Route::get('/{produksi}/edit', [App\Http\Controllers\ProduksiController::class, 'edit'])->name('edit');
        Route::patch('/{produksi}', [App\Http\Controllers\ProduksiController::class, 'update'])->name('update');
        Route::post('/{produksi}/split', [App\Http\Controllers\ProduksiController::class, 'split'])->name('split');
        Route::patch('/{produksi}/worker', [App\Http\Controllers\ProduksiController::class, 'gantiJahit'])->name('ganti-jahit');

        foreach (['potong', 'jahit', 'qc'] as $type) {
            Route::prefix($type)->name($type.'.')->group(function () use ($type) {
                Route::get('/list', [App\Http\Controllers\ProduksiController::class, 'workerIndex'])->defaults('type', $type)->name('index');
                Route::post('/store', [App\Http\Controllers\ProduksiController::class, 'workerStore'])->defaults('type', $type)->name('store');
                Route::put('/{worker}', [App\Http\Controllers\ProduksiController::class, 'workerUpdate'])->name('update');
                Route::delete('/{worker}/delete', [App\Http\Controllers\ProduksiController::class, 'workerDestroy'])->name('destroy');
            });
        }
    });

    // Borongan Module
    Route::prefix('borongan')->name('borongan.')->group(function () {
        Route::get('/', [App\Http\Controllers\BoronganController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\BoronganController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\BoronganController::class, 'store'])->name('store');
        Route::get('/ajax', [App\Http\Controllers\BoronganController::class, 'getAjaxBorongan'])->name('ajax');
        Route::get('/{borongan}', [App\Http\Controllers\BoronganController::class, 'show'])->name('show');
    });

    // Modul Karyawan, Cuti, dan Gaji
    Route::resource('karyawan', \App\Http\Controllers\KaryawanController::class);
    Route::get('karyawan/{karyawan}/cuti/create', [\App\Http\Controllers\CutiController::class, 'create'])->name('karyawan.cuti.create');
    Route::post('karyawan/{karyawan}/cuti', [\App\Http\Controllers\CutiController::class, 'store'])->name('karyawan.cuti.store');

    Route::get('gaji', [\App\Http\Controllers\GajiController::class, 'index'])->name('gaji.index');
    Route::delete('gaji/{gaji}', [\App\Http\Controllers\GajiController::class, 'destroy'])->name('gaji.destroy');
    Route::get('karyawan/{karyawan}/gaji/create', [\App\Http\Controllers\GajiController::class, 'create'])->name('karyawan.gaji.create');
    Route::post('karyawan/{karyawan}/gaji', [\App\Http\Controllers\GajiController::class, 'store'])->name('karyawan.gaji.store');

    // Reports Module
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/nett-cash-sby', [\App\Http\Controllers\ReportController::class, 'nettCashSby'])->name('nett-cash-sby');
        Route::get('/cash-flow', [\App\Http\Controllers\ReportController::class, 'cashFlow'])->name('cash-flow');
        Route::get('/compare', [\App\Http\Controllers\ReportController::class, 'compare'])->name('compare');
        Route::post('/compare', [\App\Http\Controllers\ReportController::class, 'storeCompare'])->name('compare.store');
        Route::delete('/compare/{compare}', [\App\Http\Controllers\ReportController::class, 'destroyCompare'])->name('compare.destroy');
        Route::get('/inventory-health', [\App\Http\Controllers\ReportController::class, 'inventoryHealth'])->name('inventory-health');
        Route::get('/item-sales', [\App\Http\Controllers\ReportController::class, 'itemSales'])->name('item-sales');
        Route::get('/stock-intelligence', [\App\Http\Controllers\ReportController::class, 'stockIntelligence'])->name('stock-intelligence');
        Route::get('/rebalance-detail', [\App\Http\Controllers\ReportController::class, 'rebalanceDetail'])->name('rebalance-detail');
    });
});

require __DIR__.'/settings.php';
