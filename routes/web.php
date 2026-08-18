<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified', 'active'])->name('dashboard');

Route::middleware(['auth', 'active'])->get('/banned', function () {
    if (request()->user()->active) {
        return redirect('dashboard');
    }

    return view('auth.banned');
})->withoutMiddleware(['active'])->name('banned');

// Jubelio webhook — must stay public (no auth); CSRF exempt in bootstrap/app.php
Route::post('jubelio/webhook/order', [App\Http\Controllers\JubelioController::class, 'webhookOrder'])
    ->name('jubelio.webhook.order');
Route::post('jubelio/webhook/return', [App\Http\Controllers\JubelioController::class, 'webhookReturn'])
    ->name('jubelio.webhook.return');

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::get('/permissions', [App\Http\Controllers\PermissionController::class, 'index'])->name('permissions.index');
    Route::post('/permissions/generate', [App\Http\Controllers\PermissionController::class, 'generate'])->name('permissions.generate');
    Route::resource('roles', App\Http\Controllers\RoleController::class);
    Route::post('users/{user}/ban', [\App\Http\Controllers\UserController::class, 'ban'])->name('users.ban');
    Route::post('users/{user}/unban', [\App\Http\Controllers\UserController::class, 'unban'])->name('users.unban');
    Route::resource('users', \App\Http\Controllers\UserController::class)->except(['destroy']);
    Route::resource('locations', \App\Http\Controllers\LocationController::class);
    Route::get('locations/{location}/customers', [\App\Http\Controllers\LocationController::class, 'customers'])->name('locations.customers');
    Route::post('locations/{location}/customers', [\App\Http\Controllers\LocationController::class, 'attachAddrbook'])->name('locations.customers.attach');
    Route::delete('locations/{location}/customers/{addrbook}', [\App\Http\Controllers\LocationController::class, 'detachAddrbook'])->name('locations.customers.detach');
    Route::get('items/legacy-converter', [App\Http\Controllers\LegacyItemConverterController::class, 'index'])->name('items.legacy-converter');
    Route::post('items/legacy-converter/preview', [App\Http\Controllers\LegacyItemConverterController::class, 'preview'])->name('items.legacy-converter.preview');
    Route::post('items/legacy-converter/purge-useless', [App\Http\Controllers\LegacyItemConverterController::class, 'purgeUseless'])->name('items.legacy-converter.purge-useless');
    Route::post('items/legacy-converter/run', [App\Http\Controllers\LegacyItemConverterController::class, 'run'])->name('items.legacy-converter.run');
    Route::get('items/special-converter', [App\Http\Controllers\SpecialSkuConverterController::class, 'index'])->name('items.special-converter');
    Route::post('items/special-converter/preview', [App\Http\Controllers\SpecialSkuConverterController::class, 'preview'])->name('items.special-converter.preview');
    Route::post('items/special-converter/run', [App\Http\Controllers\SpecialSkuConverterController::class, 'run'])->name('items.special-converter.run');
    Route::get('items/{item}/transactions', [App\Http\Controllers\ItemsController::class, 'itemTransactions'])->name('items.transactions');
    Route::get('items/{item}/stats', [App\Http\Controllers\ItemsController::class, 'itemStats'])->name('items.stats');
    Route::get('items/{item}/jubelio', [App\Http\Controllers\ItemsController::class, 'jubelio'])->name('items.jubelio');
    Route::get('items/{item}/jubelio-search', [App\Http\Controllers\ItemsController::class, 'getJubelioItems'])->name('items.jubelio-search');
    Route::post('items/{item}/jubelio-link', [App\Http\Controllers\ItemsController::class, 'updateJubelioId'])->name('items.jubelio-link');
    Route::resource('items', App\Http\Controllers\ItemsController::class);
    Route::get('jubelio/order/cek', [App\Http\Controllers\JubelioController::class, 'cekOrder'])->name('jubelio.order.cek');
    Route::post('jubelio/order/cek/queue', [App\Http\Controllers\JubelioController::class, 'queueCekOrder'])->name('jubelio.order.cek.queue');
    Route::get('jubelio/token', [App\Http\Controllers\JubelioTokenController::class, 'index'])->name('jubelio.token.index');
    Route::post('jubelio/token/refresh', [App\Http\Controllers\JubelioTokenController::class, 'refresh'])->name('jubelio.token.refresh');
    Route::post('jubelio/token/check', [App\Http\Controllers\JubelioTokenController::class, 'check'])->name('jubelio.token.check');
    Route::get('jubelio/{jubelio}/payload', [App\Http\Controllers\JubelioController::class, 'payload'])->name('jubelio.payload');
    Route::post('jubelio/{jubelio}/process', [App\Http\Controllers\JubelioController::class, 'processOrder'])->name('jubelio.process');
    Route::post('jubelio/{jubelio}/solve', [App\Http\Controllers\JubelioController::class, 'markSolved'])->name('jubelio.solve');
    Route::resource('jubelio', App\Http\Controllers\JubelioController::class);
    Route::get('jubelio-returns', [App\Http\Controllers\JubelioReturnController::class, 'index'])->name('jubelio.returns.index');
    Route::get('jubelio-returns/{jubelioReturn}', [App\Http\Controllers\JubelioReturnController::class, 'show'])->name('jubelio.returns.show');
    Route::post('jubelio-returns/{jubelioReturn}/process', [App\Http\Controllers\JubelioReturnController::class, 'process'])->name('jubelio.returns.process');
    Route::post('jubelio-returns/{jubelioReturn}/solve', [App\Http\Controllers\JubelioReturnController::class, 'markSolved'])->name('jubelio.returns.solve');
    Route::get('jubelio-get-orders', [App\Http\Controllers\JubelioGetOrderController::class, 'index'])->name('jubelio.get-orders.index');
    Route::post('jubelio-get-orders', [App\Http\Controllers\JubelioGetOrderController::class, 'store'])->name('jubelio.get-orders.store');
    Route::post('jubelio-get-orders/reset', [App\Http\Controllers\JubelioGetOrderController::class, 'reset'])->name('jubelio.get-orders.reset');
    Route::get('jubelio-transaction/sync', [App\Http\Controllers\JubelioController::class, 'transactionSync'])->name('jubelio.transaction.sync');
    Route::get('jubelio-transaction/{transaction}/detail-sync', [App\Http\Controllers\JubelioController::class, 'detailJubelioSync'])->name('jubelio.transaction.detail-sync');
    Route::patch('jubelio-transaction/{transaction}/sync-display', [App\Http\Controllers\JubelioController::class, 'transactionSyncDisplay'])->name('jubelio.transaction.sync-display');
    Route::post('jubelio-transaction/{transaction}/sync-confirm', [App\Http\Controllers\JubelioController::class, 'confirmSyncWarning'])->name('jubelio.transaction.sync-confirm');
    Route::post('jubelio-transaction/{transaction}/sync-clear', [App\Http\Controllers\JubelioController::class, 'clearSyncWarning'])->name('jubelio.transaction.sync-clear');
    Route::post('jubelio-transaction/{id}/adjust-stok', [App\Http\Controllers\JubelioController::class, 'adjustStok'])->name('jubelio.adjustStok');

    // Jubelio Stock Check Routes
    Route::resource('jubelio-stock-checks', App\Http\Controllers\JubelioStockCheckController::class)->only(['index', 'create', 'store', 'show', 'destroy']);

    // Jubelio Sync Mappings
    Route::get('jubelio-sync', [App\Http\Controllers\JubelioSyncController::class, 'index'])->name('jubelio.sync.index');
    Route::get('jubelio-sync/create', [App\Http\Controllers\JubelioSyncController::class, 'create'])->name('jubelio.sync.create');
    Route::post('jubelio-sync', [App\Http\Controllers\JubelioSyncController::class, 'store'])->name('jubelio.sync.store');
    Route::post('jubelio-sync/refresh-bins', [App\Http\Controllers\JubelioSyncController::class, 'refreshAllBins'])->name('jubelio.sync.refreshBins');
    Route::get('jubelio-sync/{sync}/edit', [App\Http\Controllers\JubelioSyncController::class, 'edit'])->name('jubelio.sync.edit');
    Route::patch('jubelio-sync/{sync}', [App\Http\Controllers\JubelioSyncController::class, 'update'])->name('jubelio.sync.update');
    Route::delete('jubelio-sync/{sync}', [App\Http\Controllers\JubelioSyncController::class, 'destroy'])->name('jubelio.sync.delete');
    Route::get('jubelio-sync/{sync}/bin', [App\Http\Controllers\JubelioSyncController::class, 'getBin'])->name('jubelio.sync.getBin');

    Route::get('addrbook', fn () => abort(404));
    Route::resource('addrbook', App\Http\Controllers\AddrbookController::class)->except(['index']);
    Route::get('addrbook/{addrbook}/transactions', [App\Http\Controllers\AddrbookController::class, 'transactions'])->name('addrbook.transactions');
    Route::get('addrbook/{addrbook}/items', [App\Http\Controllers\AddrbookController::class, 'items'])->name('addrbook.items');
    Route::get('addrbook/{addrbook}/item-sales', [App\Http\Controllers\AddrbookController::class, 'itemSales'])->name('addrbook.item-sales');
    Route::get('addrbook/{addrbook}/stats', [App\Http\Controllers\AddrbookController::class, 'stat'])->name('addrbook.stats');
    Route::get('system-settings/invoice/branding', [App\Http\Controllers\InvoiceSettingsController::class, 'edit'])->name('invoice-settings.edit');
    Route::put('system-settings/invoice/branding', [App\Http\Controllers\InvoiceSettingsController::class, 'update'])->name('invoice-settings.update');
    Route::get('system-settings/lookup/{type}', [App\Http\Controllers\SettingController::class, 'lookup'])->name('system-settings.lookup');
    Route::resource('system-settings', App\Http\Controllers\SettingController::class)->except(['show']);

    // Cron Manager
    Route::get('cron-manager', [App\Http\Controllers\ScheduledTaskController::class, 'index'])->name('scheduled-tasks.index');
    Route::patch('cron-manager/{scheduledTask}', [App\Http\Controllers\ScheduledTaskController::class, 'update'])->name('scheduled-tasks.update');
    Route::post('cron-manager/{scheduledTask}/toggle', [App\Http\Controllers\ScheduledTaskController::class, 'toggle'])->name('scheduled-tasks.toggle');

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

    Route::get('/{type}/{addrbook}/transactions', [App\Http\Controllers\AddrbookController::class, 'transactionsType'])
        ->where('type', $addrbookTypes)
        ->name('addrbook.type.transactions');

    Route::get('/{type}/{addrbook}/items', [App\Http\Controllers\AddrbookController::class, 'itemsType'])
        ->where('type', $addrbookTypes)
        ->name('addrbook.type.items');

    Route::get('/{type}/{addrbook}/stats', [App\Http\Controllers\AddrbookController::class, 'statType'])
        ->where('type', $addrbookTypes)
        ->name('addrbook.type.stats');

    Route::get('/{type}/{addrbook}/item-sales', [App\Http\Controllers\AddrbookController::class, 'itemSalesType'])
        ->where('type', $addrbookTypes)
        ->name('addrbook.type.item-sales');

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
    Route::get('assetlancar/{item}/transactions', [App\Http\Controllers\ItemsController::class, 'itemTransactions'])->name('assetlancar.transactions');
    Route::get('assetlancar/{item}/stats', [App\Http\Controllers\ItemsController::class, 'itemStats'])->name('assetlancar.stats');

    // Item Group Routes
    Route::get('items-group', [App\Http\Controllers\ItemsController::class, 'group'])->name('items.group');
    Route::get('items-group/parent/{parentSlug}', [App\Http\Controllers\ItemsController::class, 'groupParentDetail'])->name('items.group-parent-detail');
    Route::get('items-group/parent/{parentSlug}/export', [App\Http\Controllers\ItemsController::class, 'exportGroupParent'])->name('items.group-parent-export');
    Route::put('items-group/parent/{parentSlug}', [App\Http\Controllers\ItemsController::class, 'updateGroupParent'])->name('items.group-parent-update');
    Route::get('items-group/{group}', [App\Http\Controllers\ItemsController::class, 'groupDetail'])->name('items.group-detail');
    Route::put('items-group/{group}', [App\Http\Controllers\ItemsController::class, 'updateGroup'])->name('items.group-update');
    Route::get('items-group/{group}/stats', [App\Http\Controllers\ItemsController::class, 'groupStats'])->name('items.group-stats');

    // Item Stats & Transactions
    Route::get('items/{item}/transactions', [App\Http\Controllers\ItemsController::class, 'itemTransactions'])->name('items.transactions');
    Route::get('items/{item}/stats', [App\Http\Controllers\ItemsController::class, 'itemStats'])->name('items.stats');

    // Transactions Routes
    Route::get('transactions/deleted', [App\Http\Controllers\DeletedTransactionsController::class, 'index'])->name('transactions.deleted.index');
    Route::get('transactions/deleted/{id}', [App\Http\Controllers\DeletedTransactionsController::class, 'show'])->name('transactions.deleted.show');
    Route::get('transactions', [App\Http\Controllers\TransactionsController::class, 'index'])->name('transactions.index');
    Route::post('transactions', [App\Http\Controllers\TransactionsController::class, 'store'])->name('transactions.store');
    Route::post('transactions/batch-parse', [App\Http\Controllers\TransactionsController::class, 'batchParse'])->name('transactions.batch-parse');

    Route::get('transactions/cash-in', [App\Http\Controllers\TransactionsController::class, 'cashIn'])->name('transactions.cash-in');
    Route::post('transactions/cash-in', [App\Http\Controllers\TransactionsController::class, 'storeCashIn'])->name('transactions.cash-in.store');

    Route::get('transactions/cash-out', [App\Http\Controllers\TransactionsController::class, 'cashOut'])->name('transactions.cash-out');
    Route::post('transactions/cash-out', [App\Http\Controllers\TransactionsController::class, 'storeCashOut'])->name('transactions.cash-out.store');

    Route::get('transactions/transfer', [App\Http\Controllers\TransactionsController::class, 'transfer'])->name('transactions.transfer');
    Route::post('transactions/transfer', [App\Http\Controllers\TransactionsController::class, 'storeTransfer'])->name('transactions.transfer.store');

    Route::get('transactions/adjust', [App\Http\Controllers\TransactionsController::class, 'adjust'])->name('transactions.adjust');
    Route::post('transactions/adjust', [App\Http\Controllers\TransactionsController::class, 'storeAdjust'])->name('transactions.adjust.store');

    Route::get('transactions/export', [App\Http\Controllers\TransactionsController::class, 'export'])->name('transactions.export');
    Route::get('transactions/{transaction}/receipt', [App\Http\Controllers\TransactionsController::class, 'receipt'])->name('transactions.receipt');
    Route::get('transactions/{transaction}/print', [App\Http\Controllers\TransactionsController::class, 'printInvoice'])->name('transactions.print');
    Route::get('transactions/{transaction}/pdf', [App\Http\Controllers\TransactionsController::class, 'showPdf'])->name('transactions.pdf.show');
    Route::post('transactions/{transaction}/pdf', [App\Http\Controllers\TransactionsController::class, 'storePdf'])->name('transactions.pdf.store');
    Route::match(['get', 'post'], 'transactions/{transaction}/draft-return', [App\Http\Controllers\TransactionsController::class, 'draftReturn'])->name('transactions.draft-return');
    Route::post('transactions/{transaction}/whatsapp', [App\Http\Controllers\TransactionsController::class, 'sendWhatsapp'])->name('transactions.whatsapp');

    Route::get('transactions/{transaction}', [App\Http\Controllers\TransactionsController::class, 'show'])->name('transactions.show');
    Route::delete('transactions/{transaction}', [App\Http\Controllers\TransactionsController::class, 'destroy'])->name('transactions.destroy');

    Route::get('transactions/{type}/create', [App\Http\Controllers\TransactionsController::class, 'create'])->name('transactions.create');
    Route::get('transactions/{type}/item-by-id', [App\Http\Controllers\TransactionsController::class, 'itemById'])->name('transactions.item-by-id');
    Route::get('transactions/{type}/lookup/{role}', [App\Http\Controllers\TransactionLookupController::class, 'search'])->name('transactions.lookup');
    Route::get('tags/lookup', [App\Http\Controllers\TagLookupController::class, 'search'])->name('tags.lookup');
    Route::resource('tags', \App\Http\Controllers\Stuff\TagController::class);
    Route::redirect('/contributors', '/reports/product-performance');
    Route::redirect('/contributors/filter', '/reports/product-performance');
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
        Route::patch('/{produksi}/pritil', [App\Http\Controllers\ProduksiController::class, 'postSavePritil'])->name('assign-pritil');

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

        foreach (['potong', 'jahit', 'qc', 'pritil'] as $type) {
            Route::prefix($type)->name($type.'.')->group(function () use ($type) {
                Route::get('/list', [App\Http\Controllers\ProduksiController::class, 'workerIndex'])->defaults('type', $type)->name('index');
                Route::get('/{worker}', [App\Http\Controllers\ProduksiController::class, 'workerShow'])->defaults('type', $type)->name('show');
                Route::post('/store', [App\Http\Controllers\ProduksiController::class, 'workerStore'])->defaults('type', $type)->name('store');
                Route::put('/{worker}', [App\Http\Controllers\ProduksiController::class, 'workerUpdate'])->defaults('type', $type)->name('update');
                Route::delete('/{worker}/delete', [App\Http\Controllers\ProduksiController::class, 'workerDestroy'])->defaults('type', $type)->name('destroy');
            });
        }
    });

    // Borongan Module
    Route::prefix('borongan')->name('borongan.')->group(function () {
        Route::get('/', [App\Http\Controllers\BoronganController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\BoronganController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\BoronganController::class, 'store'])->name('store');
        Route::get('/ajax', [App\Http\Controllers\BoronganController::class, 'getAjaxBorongan'])->name('ajax');
        Route::get('/{borongan}/edit', [App\Http\Controllers\BoronganController::class, 'edit'])->name('edit');
        Route::patch('/{borongan}', [App\Http\Controllers\BoronganController::class, 'update'])->name('update');
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
        Route::get('/nett-cash-sby', \App\Http\Controllers\Reports\NettCashController::class)->name('nett-cash-sby');
        Route::get('/purchase', \App\Http\Controllers\Reports\PurchaseReportController::class)->name('purchase');
        Route::get('/expense', \App\Http\Controllers\Reports\ExpenseReportController::class)->name('expense');
        Route::get('/warehouse-item', [\App\Http\Controllers\Reports\WarehouseItemReportController::class, 'index'])->name('warehouse-item');
        Route::get('/cash-flow', \App\Http\Controllers\CashFlowController::class)->name('cash-flow');
        Route::get('/compare', [\App\Http\Controllers\Reports\CompareReportController::class, 'index'])->name('compare');
        Route::post('/compare', [\App\Http\Controllers\Reports\CompareReportController::class, 'store'])->name('compare.store');
        Route::delete('/compare/{compare}', [\App\Http\Controllers\Reports\CompareReportController::class, 'destroy'])->name('compare.destroy');
        Route::get('/inventory-health', [\App\Http\Controllers\ReportController::class, 'inventoryHealth'])->name('inventory-health');
        Route::get('/item-sales', \App\Http\Controllers\Reports\ItemSaleReportController::class)->name('item-sales');
        Route::get('/warehouse-arrangement', [\App\Http\Controllers\Reports\WarehouseArrangementController::class, 'index'])->name('warehouse-arrangement');
        Route::get('/warehouse-arrangement/export', [\App\Http\Controllers\Reports\WarehouseArrangementController::class, 'export'])->name('warehouse-arrangement.export');
        Route::post('/warehouse-arrangement/draft-move', [\App\Http\Controllers\Reports\WarehouseArrangementController::class, 'draftMove'])->name('warehouse-arrangement.draft-move');
        Route::post('/warehouse-arrangement/refresh', [\App\Http\Controllers\Reports\WarehouseArrangementController::class, 'refresh'])->name('warehouse-arrangement.refresh');
        Route::post('/warehouse-arrangement/cancel-refresh', [\App\Http\Controllers\Reports\WarehouseArrangementController::class, 'cancelRefresh'])->name('warehouse-arrangement.cancel-refresh');
        Route::post('/warehouse-arrangement/tick-refresh', [\App\Http\Controllers\Reports\WarehouseArrangementController::class, 'tickRefresh'])->name('warehouse-arrangement.tick-refresh');
        Route::get('/product-performance', [\App\Http\Controllers\Reports\ProductPerformanceController::class, 'index'])->name('product-performance');
        Route::get('/produksi-potong', \App\Http\Controllers\Reports\ProduksiPotongReportController::class)->name('produksi-potong');
        Route::get('/produksi-qc', \App\Http\Controllers\Reports\ProduksiQcReportController::class)->name('produksi-qc');
    });

    // Restock Module
    Route::prefix('restock')->name('restock.')->group(function () {
        Route::get('/', [App\Http\Controllers\Restock\RestockTypeController::class, 'index'])->name('index');
        Route::get('/missing', [App\Http\Controllers\Restock\RestockMissingController::class, 'index'])->name('missing.index');
        Route::get('/type/{typeTag:code}/missing', [App\Http\Controllers\Restock\RestockMissingController::class, 'forType'])->name('type.missing');
        Route::post('/missing/{cell}/found', [App\Http\Controllers\Restock\RestockMissingController::class, 'markFound'])->name('missing.found');
        Route::get('/settings', [App\Http\Controllers\Restock\RestockSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [App\Http\Controllers\Restock\RestockSettingsController::class, 'update'])->name('settings.update');
        Route::get('/settings/lookup/{type}', [App\Http\Controllers\Restock\RestockSettingsController::class, 'lookup'])->name('settings.lookup');
        Route::get('/type/{typeTag:code}', [App\Http\Controllers\Restock\RestockTypeController::class, 'show'])->name('type.show');
        Route::post('/type/{typeTag:code}/sheets', [App\Http\Controllers\Restock\RestockSheetController::class, 'store'])->name('sheets.store');
        Route::get('/sheets/{sheet}', [App\Http\Controllers\Restock\RestockSheetController::class, 'show'])->name('sheets.show');
        Route::get('/sheets/{sheet}/export', [App\Http\Controllers\Restock\RestockSheetController::class, 'export'])->name('sheets.export');
        Route::put('/sheets/{sheet}', [App\Http\Controllers\Restock\RestockSheetController::class, 'update'])->name('sheets.update');
        Route::post('/sheets/{sheet}/move', [App\Http\Controllers\Restock\RestockSheetController::class, 'move'])->name('sheets.move');
        Route::post('/sheets/{sheet}/receive', [App\Http\Controllers\Restock\RestockSheetController::class, 'receive'])->name('sheets.receive');
        Route::post('/sheets/{sheet}/sync', [App\Http\Controllers\Restock\RestockSheetController::class, 'sync'])->name('sheets.sync');
    });
});

require __DIR__.'/settings.php';
