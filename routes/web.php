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

Route::get('dashboard', App\Http\Controllers\DashboardController::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('dashboard');

Route::get('my-checklist', [App\Http\Controllers\MyChecklistController::class, 'index'])
    ->middleware(['auth', 'verified', 'active'])
    ->name('my-checklist.index');

Route::post('checklist/{checklist}/toggle', App\Http\Controllers\ChecklistCompletionController::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('checklist.toggle');

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

// Shopee Ads OAuth callback — public (Shopee redirects here after seller approval)
Route::get('shopee-ads/oauth/callback', [App\Http\Controllers\ShopeeAdsController::class, 'oauthCallback'])
    ->name('shopee-ads.oauth.callback');

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::get('/permissions', [App\Http\Controllers\PermissionController::class, 'index'])->name('permissions.index');
    Route::post('/permissions/generate', [App\Http\Controllers\PermissionController::class, 'generate'])->name('permissions.generate');
    Route::resource('roles', App\Http\Controllers\RoleController::class);
    Route::post('users/{user}/ban', [\App\Http\Controllers\UserController::class, 'ban'])->name('users.ban');
    Route::post('users/{user}/unban', [\App\Http\Controllers\UserController::class, 'unban'])->name('users.unban');
    Route::get('staff-checklists', [App\Http\Controllers\StaffChecklistOverviewController::class, 'index'])->name('staff-checklists.index');
    Route::get('staff-checklists/templates', [App\Http\Controllers\ChecklistTemplateController::class, 'index'])->name('staff-checklists.templates.index');
    Route::get('staff-checklists/templates/create', [App\Http\Controllers\ChecklistTemplateController::class, 'create'])->name('staff-checklists.templates.create');
    Route::post('staff-checklists/templates', [App\Http\Controllers\ChecklistTemplateController::class, 'store'])->name('staff-checklists.templates.store');
    Route::get('staff-checklists/templates/{template}/edit', [App\Http\Controllers\ChecklistTemplateController::class, 'edit'])->name('staff-checklists.templates.edit');
    Route::put('staff-checklists/templates/{template}', [App\Http\Controllers\ChecklistTemplateController::class, 'update'])->name('staff-checklists.templates.update');
    Route::delete('staff-checklists/templates/{template}', [App\Http\Controllers\ChecklistTemplateController::class, 'destroy'])->name('staff-checklists.templates.destroy');
    Route::resource('users', \App\Http\Controllers\UserController::class)->except(['destroy']);
    Route::resource('locations', \App\Http\Controllers\LocationController::class);
    Route::get('locations/{location}/customers', [\App\Http\Controllers\LocationController::class, 'customers'])->name('locations.customers');
    Route::post('locations/{location}/customers', [\App\Http\Controllers\LocationController::class, 'attachAddrbook'])->name('locations.customers.attach');
    Route::delete('locations/{location}/customers/{addrbook}', [\App\Http\Controllers\LocationController::class, 'detachAddrbook'])->name('locations.customers.detach');
    Route::get('items/legacy-converter', [App\Http\Controllers\LegacyItemConverterController::class, 'index'])->name('items.legacy-converter');
    Route::post('items/legacy-converter/preview', [App\Http\Controllers\LegacyItemConverterController::class, 'preview'])->name('items.legacy-converter.preview');
    Route::post('items/legacy-converter/purge-useless', [App\Http\Controllers\LegacyItemConverterController::class, 'purgeUseless'])->name('items.legacy-converter.purge-useless');
    Route::post('items/legacy-converter/run', [App\Http\Controllers\LegacyItemConverterController::class, 'run'])->name('items.legacy-converter.run');
    Route::post('items/legacy-converter/{item}/run', [App\Http\Controllers\LegacyItemConverterController::class, 'runItem'])->name('items.legacy-converter.run-item');
    Route::get('items/party-lookup', [App\Http\Controllers\ItemsController::class, 'partyLookup'])->name('items.party-lookup');
    Route::get('items/pcode-name', [App\Http\Controllers\ItemsController::class, 'pcodeName'])->name('items.pcode-name');
    Route::get('items/{item}/transactions', [App\Http\Controllers\ItemsController::class, 'itemTransactions'])->name('items.transactions');
    Route::get('items/{item}/stats', [App\Http\Controllers\ItemsController::class, 'itemStats'])->name('items.stats');
    Route::get('items/{item}/jubelio', [App\Http\Controllers\ItemsController::class, 'jubelio'])->name('items.jubelio');
    Route::get('items/{item}/jubelio-search', [App\Http\Controllers\ItemsController::class, 'getJubelioItems'])->name('items.jubelio-search');
    Route::post('items/{item}/jubelio-link', [App\Http\Controllers\ItemsController::class, 'updateJubelioId'])->name('items.jubelio-link');
    Route::post('items/{item}/convert-identity', [App\Http\Controllers\ItemIdentityConvertController::class, 'store'])->name('items.convert-identity');
    Route::post('items/{item}/recalculate-qty', [App\Http\Controllers\ItemsController::class, 'recalculateQuantity'])->name('items.recalculate-qty');
    Route::resource('items', App\Http\Controllers\ItemsController::class);
    Route::get('jubelio/order/cek', [App\Http\Controllers\JubelioController::class, 'cekOrder'])->name('jubelio.order.cek');
    Route::post('jubelio/order/cek/queue', [App\Http\Controllers\JubelioController::class, 'queueCekOrder'])->name('jubelio.order.cek.queue');
    Route::get('jubelio/token', [App\Http\Controllers\JubelioTokenController::class, 'index'])->name('jubelio.token.index');
    Route::post('jubelio/token/refresh', [App\Http\Controllers\JubelioTokenController::class, 'refresh'])->name('jubelio.token.refresh');
    Route::post('jubelio/token/check', [App\Http\Controllers\JubelioTokenController::class, 'check'])->name('jubelio.token.check');
    Route::get('jubelio/{jubelio}/payload', [App\Http\Controllers\JubelioController::class, 'payload'])->name('jubelio.payload');
    Route::post('jubelio/{jubelio}/refresh-payload', [App\Http\Controllers\JubelioController::class, 'refreshPayload'])->name('jubelio.refresh-payload');
    Route::post('jubelio/{jubelio}/process', [App\Http\Controllers\JubelioController::class, 'processOrder'])->name('jubelio.process');
    Route::post('jubelio/{jubelio}/solve', [App\Http\Controllers\JubelioController::class, 'markSolved'])->name('jubelio.solve');
    Route::resource('jubelio', App\Http\Controllers\JubelioController::class);
    Route::get('jubelio-returns', [App\Http\Controllers\JubelioReturnController::class, 'index'])->name('jubelio.returns.index');
    Route::get('jubelio-returns/{jubelioReturn}', [App\Http\Controllers\JubelioReturnController::class, 'show'])->name('jubelio.returns.show');
    Route::post('jubelio-returns/{jubelioReturn}/process', [App\Http\Controllers\JubelioReturnController::class, 'process'])->name('jubelio.returns.process');
    Route::post('jubelio-returns/{jubelioReturn}/solve', [App\Http\Controllers\JubelioReturnController::class, 'markSolved'])->name('jubelio.returns.solve');
    Route::get('jubelio-get-orders', [App\Http\Controllers\JubelioGetOrderController::class, 'index'])->name('jubelio.get-orders.index');
    Route::post('jubelio-get-orders', [App\Http\Controllers\JubelioGetOrderController::class, 'store'])->name('jubelio.get-orders.store');
    Route::post('jubelio-get-orders/resume', [App\Http\Controllers\JubelioGetOrderController::class, 'resume'])->name('jubelio.get-orders.resume');
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
    Route::get('addrbook/{addrbook}/items/export', [App\Http\Controllers\AddrbookController::class, 'itemsExport'])->name('addrbook.items.export');
    Route::get('addrbook/{addrbook}/item-sales', [App\Http\Controllers\AddrbookController::class, 'itemSales'])->name('addrbook.item-sales');
    Route::get('addrbook/{addrbook}/item-sales/export', [App\Http\Controllers\AddrbookController::class, 'itemSalesExport'])->name('addrbook.item-sales.export');
    Route::get('addrbook/{addrbook}/stats', [App\Http\Controllers\AddrbookController::class, 'stat'])->name('addrbook.stats');
    Route::get('system-settings/invoice/branding', [App\Http\Controllers\InvoiceSettingsController::class, 'edit'])->name('invoice-settings.edit');
    Route::put('system-settings/invoice/branding', [App\Http\Controllers\InvoiceSettingsController::class, 'update'])->name('invoice-settings.update');
    Route::get('system-settings/lookup/{type}', [App\Http\Controllers\SettingController::class, 'lookup'])->name('system-settings.lookup');
    Route::resource('system-settings', App\Http\Controllers\SettingController::class)->except(['show']);

    // Warehouse stats backfill
    Route::get('warehouse-stat-backfill', [App\Http\Controllers\WarehouseStatBackfillController::class, 'index'])->name('warehouse-stat-backfill.index');
    Route::post('warehouse-stat-backfill/start', [App\Http\Controllers\WarehouseStatBackfillController::class, 'start'])->name('warehouse-stat-backfill.start');
    Route::post('warehouse-stat-backfill/pause', [App\Http\Controllers\WarehouseStatBackfillController::class, 'pause'])->name('warehouse-stat-backfill.pause');
    Route::post('warehouse-stat-backfill/resume', [App\Http\Controllers\WarehouseStatBackfillController::class, 'resume'])->name('warehouse-stat-backfill.resume');
    Route::post('warehouse-stat-backfill/run-batch', [App\Http\Controllers\WarehouseStatBackfillController::class, 'runBatch'])->name('warehouse-stat-backfill.run-batch');

    // Data retention (archive copy + live cleanup)
    Route::get('data-retention', [App\Http\Controllers\DataRetentionController::class, 'index'])->name('data-retention.index');
    Route::post('data-retention/preview-archive', [App\Http\Controllers\DataRetentionController::class, 'previewArchive'])->name('data-retention.preview-archive');
    Route::post('data-retention/archive-year', [App\Http\Controllers\DataRetentionController::class, 'archiveYear'])->name('data-retention.archive-year');
    Route::post('data-retention/preview-cleanup', [App\Http\Controllers\DataRetentionController::class, 'previewCleanup'])->name('data-retention.preview-cleanup');
    Route::post('data-retention/cleanup-year', [App\Http\Controllers\DataRetentionController::class, 'cleanupYear'])->name('data-retention.cleanup-year');
    Route::post('data-retention/purge-orphan-items', [App\Http\Controllers\DataRetentionController::class, 'purgeOrphanItems'])->name('data-retention.purge-orphan-items');
    Route::post('data-retention/purge-orphan-item-groups', [App\Http\Controllers\DataRetentionController::class, 'purgeOrphanItemGroups'])->name('data-retention.purge-orphan-item-groups');
    Route::post('data-retention/purge-orphan-addrbooks/{type}', [App\Http\Controllers\DataRetentionController::class, 'purgeOrphanAddrbooks'])->name('data-retention.purge-orphan-addrbooks');
    Route::get('data-retention/item-purge', [App\Http\Controllers\ItemPurgeController::class, 'index'])->name('data-retention.item-purge.index');
    Route::post('data-retention/item-purge', [App\Http\Controllers\ItemPurgeController::class, 'purge'])->name('data-retention.item-purge.purge');

    // Archive (read-only)
    Route::get('archive', [App\Http\Controllers\ArchiveDashboardController::class, 'index'])->name('archive.index');
    Route::get('archive/transactions', [App\Http\Controllers\ArchiveTransactionsController::class, 'index'])->name('archive.transactions.index');
    Route::get('archive/transactions/{id}', [App\Http\Controllers\ArchiveTransactionsController::class, 'show'])->name('archive.transactions.show');
    Route::get('archive/items', [App\Http\Controllers\ArchiveItemsController::class, 'index'])->name('archive.items.index');
    Route::get('archive/items/{id}', [App\Http\Controllers\ArchiveItemsController::class, 'show'])->name('archive.items.show');

    // Cron Manager
    Route::get('cron-manager', [App\Http\Controllers\ScheduledTaskController::class, 'index'])->name('scheduled-tasks.index');
    Route::patch('cron-manager/{scheduledTask}', [App\Http\Controllers\ScheduledTaskController::class, 'update'])->name('scheduled-tasks.update');
    Route::post('cron-manager/{scheduledTask}/toggle', [App\Http\Controllers\ScheduledTaskController::class, 'toggle'])->name('scheduled-tasks.toggle');

    Route::get('shopee-ads', [App\Http\Controllers\ShopeeAdsController::class, 'index'])->name('shopee-ads.index');
    Route::patch('shopee-ads/settings', [App\Http\Controllers\ShopeeAdsController::class, 'updateSettings'])->name('shopee-ads.settings.update');
    Route::post('shopee-ads/schedules', [App\Http\Controllers\ShopeeAdsController::class, 'storeSchedule'])->name('shopee-ads.schedules.store');
    Route::delete('shopee-ads/schedules/{shopeeAdsSchedule}', [App\Http\Controllers\ShopeeAdsController::class, 'destroySchedule'])->name('shopee-ads.schedules.destroy');
    Route::post('shopee-ads/toggle-pause', [App\Http\Controllers\ShopeeAdsController::class, 'togglePause'])->name('shopee-ads.toggle-pause');
    Route::get('shopee-ads/authorize', [App\Http\Controllers\ShopeeAdsController::class, 'authorizeShop'])->name('shopee-ads.authorize');
    Route::post('shopee-ads/sync-item-ads', [App\Http\Controllers\ShopeeAdsController::class, 'syncItemAds'])->name('shopee-ads.sync-item-ads');
    Route::post('shopee-ads/run-schedules', [App\Http\Controllers\ShopeeAdsController::class, 'runSchedules'])->name('shopee-ads.run-schedules');
    Route::post('shopee-ads/replenish', [App\Http\Controllers\ShopeeAdsController::class, 'replenish'])->name('shopee-ads.replenish');
    Route::post('shopee-ads/daily-reset', [App\Http\Controllers\ShopeeAdsController::class, 'dailyReset'])->name('shopee-ads.daily-reset');
    Route::post('shopee-ads/boost-budget', [App\Http\Controllers\ShopeeAdsController::class, 'boostBudget'])->name('shopee-ads.boost');
    Route::post('shopee-ads/suggest-group-ads', [App\Http\Controllers\ShopeeAdsController::class, 'suggestGroupAds'])->name('shopee-ads.suggest-group-ads');

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
    Route::get('/{type}/{addrbook}/items/export', [App\Http\Controllers\AddrbookController::class, 'itemsTypeExport'])
        ->where('type', $addrbookTypes)
        ->name('addrbook.type.items.export');

    Route::get('/{type}/{addrbook}/stats', [App\Http\Controllers\AddrbookController::class, 'statType'])
        ->where('type', $addrbookTypes)
        ->name('addrbook.type.stats');

    Route::get('/{type}/{addrbook}/item-sales', [App\Http\Controllers\AddrbookController::class, 'itemSalesType'])
        ->where('type', $addrbookTypes)
        ->name('addrbook.type.item-sales');
    Route::get('/{type}/{addrbook}/item-sales/export', [App\Http\Controllers\AddrbookController::class, 'itemSalesTypeExport'])
        ->where('type', $addrbookTypes)
        ->name('addrbook.type.item-sales.export');

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
    Route::post('assetlancar/{item}/convert-identity', [App\Http\Controllers\ItemIdentityConvertController::class, 'store'])->name('assetlancar.convert-identity');
    Route::post('assetlancar/{item}/recalculate-qty', [App\Http\Controllers\ItemsController::class, 'recalculateQuantity'])->name('assetlancar.recalculate-qty');

    Route::get('assettetap', [App\Http\Controllers\AssetTetapController::class, 'index'])->name('assettetap.index');
    Route::get('assettetap/create', [App\Http\Controllers\AssetTetapController::class, 'create'])->name('assettetap.create');
    Route::post('assettetap', [App\Http\Controllers\AssetTetapController::class, 'store'])->name('assettetap.store');
    Route::get('assettetap/depreciate', [App\Http\Controllers\AssetTetapController::class, 'depreciate'])->name('assettetap.depreciate');
    Route::post('assettetap/depreciate', [App\Http\Controllers\AssetTetapController::class, 'storeDepreciate'])->middleware('prevent.duplicate')->name('assettetap.depreciate.store');
    Route::get('assettetap/{item}', [App\Http\Controllers\AssetTetapController::class, 'show'])->name('assettetap.show');
    Route::get('assettetap/{item}/edit', [App\Http\Controllers\AssetTetapController::class, 'edit'])->name('assettetap.edit');
    Route::put('assettetap/{item}', [App\Http\Controllers\AssetTetapController::class, 'update'])->name('assettetap.update');
    Route::delete('assettetap/{item}', [App\Http\Controllers\AssetTetapController::class, 'destroy'])->name('assettetap.destroy');
    Route::get('assettetap/{item}/buy', [App\Http\Controllers\AssetTetapController::class, 'buy'])->name('assettetap.buy');
    Route::post('assettetap/{item}/buy', [App\Http\Controllers\AssetTetapController::class, 'storeBuy'])->middleware('prevent.duplicate')->name('assettetap.buy.store');

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
    Route::post('transactions', [App\Http\Controllers\TransactionsController::class, 'store'])->middleware('prevent.duplicate')->name('transactions.store');
    Route::post('transactions/batch-parse', [App\Http\Controllers\TransactionsController::class, 'batchParse'])->name('transactions.batch-parse');

    Route::get('transactions/cash-in', [App\Http\Controllers\TransactionsController::class, 'cashIn'])->name('transactions.cash-in');
    Route::post('transactions/cash-in', [App\Http\Controllers\TransactionsController::class, 'storeCashIn'])->middleware('prevent.duplicate')->name('transactions.cash-in.store');

    Route::get('transactions/cash-out', [App\Http\Controllers\TransactionsController::class, 'cashOut'])->name('transactions.cash-out');
    Route::post('transactions/cash-out', [App\Http\Controllers\TransactionsController::class, 'storeCashOut'])->middleware('prevent.duplicate')->name('transactions.cash-out.store');

    Route::get('transactions/transfer', [App\Http\Controllers\TransactionsController::class, 'transfer'])->name('transactions.transfer');
    Route::post('transactions/transfer', [App\Http\Controllers\TransactionsController::class, 'storeTransfer'])->middleware('prevent.duplicate')->name('transactions.transfer.store');

    Route::get('transactions/adjust', [App\Http\Controllers\TransactionsController::class, 'adjust'])->name('transactions.adjust');
    Route::post('transactions/adjust', [App\Http\Controllers\TransactionsController::class, 'storeAdjust'])->middleware('prevent.duplicate')->name('transactions.adjust.store');

    Route::get('transactions/export', [App\Http\Controllers\TransactionsController::class, 'export'])->name('transactions.export');
    Route::get('transactions/export-sell', [App\Http\Controllers\ExportSellController::class, 'index'])->name('transactions.export-sell');
    Route::get('transactions/export-sell/build', [App\Http\Controllers\ExportSellController::class, 'export'])->name('transactions.export-sell.build');
    Route::get('transactions/{transaction}/receipt', [App\Http\Controllers\TransactionsController::class, 'receipt'])->name('transactions.receipt');
    Route::get('transactions/{transaction}/print', [App\Http\Controllers\TransactionsController::class, 'printInvoice'])->name('transactions.print');
    Route::get('transactions/{transaction}/pdf', [App\Http\Controllers\TransactionsController::class, 'showPdf'])->name('transactions.pdf.show');
    Route::post('transactions/{transaction}/pdf', [App\Http\Controllers\TransactionsController::class, 'storePdf'])->name('transactions.pdf.store');
    Route::match(['get', 'post'], 'transactions/{transaction}/draft-return', [App\Http\Controllers\TransactionsController::class, 'draftReturn'])->name('transactions.draft-return');
    Route::post('transactions/{transaction}/whatsapp', [App\Http\Controllers\TransactionsController::class, 'sendWhatsapp'])->name('transactions.whatsapp');

    Route::get('invoice-maker/settings', [App\Http\Controllers\InvoiceMakerSettingsController::class, 'index'])->name('invoice-maker.settings.index');
    Route::get('invoice-maker/settings/create', [App\Http\Controllers\InvoiceMakerSettingsController::class, 'create'])->name('invoice-maker.settings.create');
    Route::post('invoice-maker/settings', [App\Http\Controllers\InvoiceMakerSettingsController::class, 'store'])->name('invoice-maker.settings.store');
    Route::get('invoice-maker/settings/{preset}/edit', [App\Http\Controllers\InvoiceMakerSettingsController::class, 'edit'])->name('invoice-maker.settings.edit');
    Route::put('invoice-maker/settings/{preset}', [App\Http\Controllers\InvoiceMakerSettingsController::class, 'update'])->name('invoice-maker.settings.update');
    Route::delete('invoice-maker/settings/{preset}', [App\Http\Controllers\InvoiceMakerSettingsController::class, 'destroy'])->name('invoice-maker.settings.destroy');
    Route::get('invoice-maker/{invoice}/pdf', [App\Http\Controllers\StandaloneInvoicesController::class, 'showPdf'])->name('invoice-maker.pdf.show');
    Route::get('invoice-maker/{invoice}/pdf/download', [App\Http\Controllers\StandaloneInvoicesController::class, 'downloadPdf'])->name('invoice-maker.pdf.download');
    Route::post('invoice-maker/{invoice}/pdf', [App\Http\Controllers\StandaloneInvoicesController::class, 'storePdf'])->name('invoice-maker.pdf.store');
    Route::patch('invoice-maker/{invoice}/discount', [App\Http\Controllers\StandaloneInvoicesController::class, 'updateDiscount'])->name('invoice-maker.discount');
    Route::resource('invoice-maker', App\Http\Controllers\StandaloneInvoicesController::class)
        ->parameters(['invoice-maker' => 'invoice']);

    Route::post('transactions/{transaction}/cash-in', [App\Http\Controllers\TransactionsController::class, 'storeSellCashIn'])->middleware('prevent.duplicate')->name('transactions.sell-cash-in.store');
    Route::patch('transactions/{transaction}/note', [App\Http\Controllers\TransactionsController::class, 'updateNote'])->name('transactions.update-note');
    Route::patch('transactions/{transaction}/invoice', [App\Http\Controllers\TransactionsController::class, 'updateInvoice'])->name('transactions.update-invoice');
    Route::patch('transactions/{transaction}/ppn', [App\Http\Controllers\TransactionsController::class, 'updatePpn'])->name('transactions.update-ppn');
    Route::get('transactions/{transaction}', [App\Http\Controllers\TransactionsController::class, 'show'])->name('transactions.show');
    Route::delete('transactions/{transaction}', [App\Http\Controllers\TransactionsController::class, 'destroy'])->name('transactions.destroy');

    Route::get('transactions/{type}/create', [App\Http\Controllers\TransactionsController::class, 'create'])->name('transactions.create');
    Route::get('transactions/{type}/item-by-id', [App\Http\Controllers\TransactionsController::class, 'itemById'])->name('transactions.item-by-id');
    Route::get('transactions/{type}/item-by-code', [App\Http\Controllers\TransactionsController::class, 'itemByCode'])->name('transactions.item-by-code');
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
    Route::get('cuti', [\App\Http\Controllers\CutiController::class, 'index'])->name('cuti.index');
    Route::get('cuti/create', [\App\Http\Controllers\CutiController::class, 'create'])->name('cuti.create');
    Route::post('cuti', [\App\Http\Controllers\CutiController::class, 'store'])->name('cuti.store');
    Route::get('cuti/{cuti}/edit', [\App\Http\Controllers\CutiController::class, 'edit'])->name('cuti.edit');
    Route::put('cuti/{cuti}', [\App\Http\Controllers\CutiController::class, 'update'])->name('cuti.update');
    Route::delete('cuti/{cuti}', [\App\Http\Controllers\CutiController::class, 'destroy'])->name('cuti.destroy');
    Route::get('karyawan/{karyawan}/cuti/create', [\App\Http\Controllers\CutiController::class, 'create'])->name('karyawan.cuti.create');
    Route::post('karyawan/{karyawan}/cuti', [\App\Http\Controllers\CutiController::class, 'store'])->name('karyawan.cuti.store');
    Route::patch('karyawan/{karyawan}/cuti-sisa', [\App\Http\Controllers\CutiSisaController::class, 'update'])->name('karyawan.cuti-sisa.update');

    Route::get('gaji', [\App\Http\Controllers\GajiController::class, 'index'])->name('gaji.index');
    Route::get('gaji/{gaji}/edit', [\App\Http\Controllers\GajiController::class, 'edit'])->name('gaji.edit');
    Route::put('gaji/{gaji}', [\App\Http\Controllers\GajiController::class, 'update'])->name('gaji.update');
    Route::delete('gaji/{gaji}', [\App\Http\Controllers\GajiController::class, 'destroy'])->name('gaji.destroy');
    Route::get('karyawan/{karyawan}/gaji/create', [\App\Http\Controllers\GajiController::class, 'create'])->name('karyawan.gaji.create');
    Route::post('karyawan/{karyawan}/gaji', [\App\Http\Controllers\GajiController::class, 'store'])->name('karyawan.gaji.store');

    Route::get('hari-libur', [\App\Http\Controllers\HariLiburController::class, 'index'])->name('hari-libur.index');
    Route::post('hari-libur', [\App\Http\Controllers\HariLiburController::class, 'store'])->name('hari-libur.store');
    Route::delete('hari-libur/{hari_libur}', [\App\Http\Controllers\HariLiburController::class, 'destroy'])->name('hari-libur.destroy');

    Route::get('absensi', [\App\Http\Controllers\AbsensiController::class, 'index'])->name('absensi.index');
    Route::get('absensi/import', [\App\Http\Controllers\AbsensiController::class, 'create'])->name('absensi.create');
    Route::post('absensi/import', [\App\Http\Controllers\AbsensiController::class, 'store'])->name('absensi.store');
    Route::get('absensi/{absensi}', [\App\Http\Controllers\AbsensiController::class, 'show'])->name('absensi.show');

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/nett-cash-sby', \App\Http\Controllers\Reports\NettCashController::class)->name('nett-cash-sby');
        Route::get('/asset-tetap', \App\Http\Controllers\Reports\AssetTetapReportController::class)->name('asset-tetap');
        Route::get('/warehouse-item', [\App\Http\Controllers\Reports\WarehouseItemReportController::class, 'index'])->name('warehouse-item');
        Route::redirect('/item-sales', '/transactions/export-sell');
        Route::get('/warehouse-arrangement', [\App\Http\Controllers\Reports\WarehouseArrangementController::class, 'index'])->name('warehouse-arrangement');
        Route::get('/warehouse-arrangement/export', [\App\Http\Controllers\Reports\WarehouseArrangementController::class, 'export'])->name('warehouse-arrangement.export');
        Route::post('/warehouse-arrangement/draft-move', [\App\Http\Controllers\Reports\WarehouseArrangementController::class, 'draftMove'])->name('warehouse-arrangement.draft-move');
        Route::post('/warehouse-arrangement/refresh', [\App\Http\Controllers\Reports\WarehouseArrangementController::class, 'refresh'])->name('warehouse-arrangement.refresh');
        Route::post('/warehouse-arrangement/cancel-refresh', [\App\Http\Controllers\Reports\WarehouseArrangementController::class, 'cancelRefresh'])->name('warehouse-arrangement.cancel-refresh');
        Route::post('/warehouse-arrangement/tick-refresh', [\App\Http\Controllers\Reports\WarehouseArrangementController::class, 'tickRefresh'])->name('warehouse-arrangement.tick-refresh');
        Route::get('/product-performance', [\App\Http\Controllers\Reports\ProductPerformanceController::class, 'index'])->name('product-performance');
        Route::get('/inventory-health', [\App\Http\Controllers\Reports\InventoryHealthController::class, 'index'])->name('inventory-health');
        Route::get('/produksi-potong', \App\Http\Controllers\Reports\ProduksiPotongReportController::class)->name('produksi-potong');
        Route::get('/produksi-jahit', \App\Http\Controllers\Reports\ProduksiJahitReportController::class)->name('produksi-jahit');
        Route::get('/produksi-qc', \App\Http\Controllers\Reports\ProduksiQcReportController::class)->name('produksi-qc');
        Route::get('/produksi-pritil', \App\Http\Controllers\Reports\ProduksiPritilReportController::class)->name('produksi-pritil');
        Route::get('/neraca', \App\Http\Controllers\Reports\NeracaReportController::class)->name('neraca');
        Route::get('/laba-rugi', \App\Http\Controllers\Reports\LabaRugiReportController::class)->name('laba-rugi');
        Route::get('/channel-pnl', \App\Http\Controllers\Reports\ChannelPnlReportController::class)->name('channel-pnl');
        Route::get('/receivables', \App\Http\Controllers\Reports\ReceivablesReportController::class)->name('receivables');
        Route::get('/payables', \App\Http\Controllers\Reports\PayablesReportController::class)->name('payables');
        Route::get('/tax/ppn', \App\Http\Controllers\Reports\TaxPpnReportController::class)->name('tax.ppn');
        Route::get('/tax/pph', \App\Http\Controllers\Reports\TaxPphReportController::class)->name('tax.pph');
        Route::get('/tax/faktur', [\App\Http\Controllers\Reports\TaxFakturImportController::class, 'index'])->name('tax.faktur.index');
        Route::get('/tax/faktur/create', [\App\Http\Controllers\Reports\TaxFakturImportController::class, 'create'])->name('tax.faktur.create');
        Route::post('/tax/faktur/parse', [\App\Http\Controllers\Reports\TaxFakturImportController::class, 'parse'])->name('tax.faktur.parse');
        Route::get('/tax/faktur/review', [\App\Http\Controllers\Reports\TaxFakturImportController::class, 'review'])->name('tax.faktur.review');
        Route::get('/tax/faktur/counterparty-lookup', [\App\Http\Controllers\Reports\TaxFakturImportController::class, 'counterpartyLookup'])->name('tax.faktur.counterparty-lookup');
        Route::get('/tax/faktur/cash-in-suggestions', [\App\Http\Controllers\Reports\TaxFakturImportController::class, 'cashInSuggestions'])->name('tax.faktur.cash-in-suggestions');
        Route::get('/tax/faktur/sell-suggestions', [\App\Http\Controllers\Reports\TaxFakturImportController::class, 'sellSuggestions'])->name('tax.faktur.sell-suggestions');
        Route::post('/tax/faktur', [\App\Http\Controllers\Reports\TaxFakturImportController::class, 'store'])->name('tax.faktur.store');
        Route::get('/tax/faktur/{import}', [\App\Http\Controllers\Reports\TaxFakturImportController::class, 'show'])->name('tax.faktur.show');
        Route::delete('/tax/faktur/{import}', [\App\Http\Controllers\Reports\TaxFakturImportController::class, 'destroy'])->name('tax.faktur.destroy');
        Route::patch('/tax/faktur/{import}/payment', [\App\Http\Controllers\Reports\TaxFakturImportController::class, 'updatePayment'])->name('tax.faktur.payment.update');
        Route::post('/tax/faktur/{import}/cash-in', [\App\Http\Controllers\Reports\TaxFakturImportController::class, 'storeCashIn'])->middleware('prevent.duplicate')->name('tax.faktur.cash-in.store');
        Route::post('/tax/faktur/{import}/link-sells', [\App\Http\Controllers\Reports\TaxFakturImportController::class, 'linkSells'])->name('tax.faktur.link-sells');
        Route::delete('/tax/faktur/{import}/sells/{transaction}', [\App\Http\Controllers\Reports\TaxFakturImportController::class, 'unlinkSell'])->name('tax.faktur.unlink-sell');
        Route::post('/tax/faktur/{import}/post-sell', [\App\Http\Controllers\Reports\TaxFakturImportController::class, 'postSell'])->name('tax.faktur.post-sell');
        Route::get('/tax/faktur/{import}/line-item-matches', [\App\Http\Controllers\Reports\TaxFakturImportController::class, 'lineItemMatches'])->name('tax.faktur.line-item-matches');
        Route::get('/tax/faktur/{import}/pdf', [\App\Http\Controllers\Reports\TaxFakturImportController::class, 'downloadPdf'])->name('tax.faktur.pdf');
        Route::get('/entities', [\App\Http\Controllers\Reports\ReportingEntityController::class, 'index'])->name('entities.index');
        Route::post('/entities', [\App\Http\Controllers\Reports\ReportingEntityController::class, 'store'])->name('entities.store');
        Route::get('/entities/{entity}/edit', [\App\Http\Controllers\Reports\ReportingEntityController::class, 'edit'])->name('entities.edit');
        Route::put('/entities/{entity}', [\App\Http\Controllers\Reports\ReportingEntityController::class, 'update'])->name('entities.update');
        Route::post('/entities/ledger-roles', [\App\Http\Controllers\Reports\ReportingEntityController::class, 'storeLedgerRole'])->name('entities.ledger-roles.store');
        Route::delete('/entities/ledger-roles/{role}', [\App\Http\Controllers\Reports\ReportingEntityController::class, 'destroyLedgerRole'])->name('entities.ledger-roles.destroy');
        Route::post('/entities/fulfillment', [\App\Http\Controllers\Reports\ReportingEntityController::class, 'storeFulfillment'])->name('entities.fulfillment.store');
        Route::delete('/entities/fulfillment/{fulfillment}', [\App\Http\Controllers\Reports\ReportingEntityController::class, 'destroyFulfillment'])->name('entities.fulfillment.destroy');
    });

    Route::prefix('stock-notifications')->name('stock-notifications.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ItemStockNotificationController::class, 'index'])->name('index');
        Route::get('/unread-count', [\App\Http\Controllers\ItemStockNotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('/mark-all-read', [\App\Http\Controllers\ItemStockNotificationController::class, 'markAllRead'])->name('mark-all-read');
        Route::post('/{notification}/read', [\App\Http\Controllers\ItemStockNotificationController::class, 'markRead'])->name('read');
        Route::post('/{notification}/dismiss', [\App\Http\Controllers\ItemStockNotificationController::class, 'dismiss'])->name('dismiss');
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
