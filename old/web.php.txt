<?php

use App\Helpers\JubelioHelper;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AddrbookController;
use App\Http\Controllers\AjaxController;
use App\Http\Controllers\ApiJubelioController;
use App\Http\Controllers\AsetLancarController;
use App\Http\Controllers\BoronganController;
use App\Http\Controllers\CashFlowController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\ContributorController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\CronrunController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CutiController;
use App\Http\Controllers\DeletedController;
use App\Http\Controllers\DestyController;
use App\Http\Controllers\DestySyncController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FilterQueryController;
use App\Http\Controllers\GajihController;
use App\Http\Controllers\HashController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ItemsController;
use App\Http\Controllers\JubelioController;
use App\Http\Controllers\JubelioGetOrderController;
use App\Http\Controllers\JubelioReturnController;
use App\Http\Controllers\JubelioSyncController;
use App\Http\Controllers\KaryawanController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LogJubelioController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\PoController;
use App\Http\Controllers\ProduksiController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResellerController;
use App\Http\Controllers\RestockController;
use App\Http\Controllers\SetoranController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StatSellController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TransactionsController;
use App\Http\Controllers\UserRoleController;
use App\Http\Controllers\VAccountController;
use App\Http\Controllers\VWarehouseController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\JubelioStockCheckController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Route::post('jub/order', [ApiJubelioController::class, 'order']);
// Route::post('jub/retur', [ApiJubelioController::class, 'retur']);

Route::get('jubelio/test', [ApiJubelioController::class, 'jubelioLoginTest'])->name('jubelio.test');
Route::get('jubelio/stock', [ApiJubelioController::class, 'getStock'])->name('jubelio.stock');

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/jubelio-token', function () {
    
    $auth = JubelioHelper::jubelioAuth();

    dd($auth);
});

Route::get('/reset-jubelio-cache', function () {
    Cache::forget('jubelio_data');

    return response()->json([
        'success' => true,
        'message' => 'Cache jubelio_data berhasil dihapus.',
    ]);
});

Route::get('/cache-role-reset', function () {
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return 'cache role dihapus';
});

Route::get('/role-set', function () {

    // $user = User::find(1);

    // $user->password =  Hash::make('12345678');

    // $user->save();

    // $user->assignRole('superadmin');

    // $role = Role::create(['name' => 'ban']);

    $permission = Permission::create(['name' => 'transactions.desty.limit.download']);

    // $role = Role::where('name','superadmin')->first();

    // $role->syncPermissions('superadmin');


    return 'berhasil';
});

Route::get('/logjubelio', [LogJubelioController::class, 'index']);
Route::get('/logjubelio/{id}/detail', [LogJubelioController::class, 'detail']);

Route::get('/edititemstat', [CronController::class, 'itemEdit']);
Route::get('/itemcron', [CronController::class, 'itemCron']);

Route::get('/viewTest', function () {

    return base_path();
});

Route::get('/role-create', function () {

    $perm = [
        'transactions.jubelio.return',
        'cron runner',
        'jubelio sync',
        'jubelio webhook',
        'jubelio get order',
        'jubelio cek order',
    ];

    foreach ($perm as $p) {

        $permission = Permission::create(['name' => $p]);
    }




    return 'berhasil';
});
Route::get('/statsell/generete', [StatSellController::class, 'generet'])->name('statsale.generate');


Route::get('/dashboard', [HomeController::class, 'index'])->middleware(['auth', 'verified', 'exceptRole:ban'])->name('dashboard');


Route::get('/cek', [HomeController::class, 'cekData']);

Route::post('/filter', [FilterQueryController::class, 'getFilter'])->name('filter.get');


Route::middleware('auth')->group(function () {
    Route::get('/desty/json/warehouse', [DestyController::class, 'warehouse'])->name('desty.warehouse.json');
    Route::post('/desty/adjust/warehouse', [DestyController::class, 'adjustmentDesty'])->name('desty.adjustmentDesty');

    Route::get('/desty/stock/increase', [DestyController::class, 'increaseDesty'])->name('desty.stock.increase');
    Route::get('/desty/stock/decrease', [DestyController::class, 'decreaseDesty'])->name('desty.stock.decrease');
    Route::get('/desty/initial/webhook', [DestyController::class, 'initialSync']);
    Route::get('/desty/payload', [DestyController::class, 'payload'])->name('desty.payload');
    Route::get('/desty/payload/{id}/detail', [DestyController::class, 'detailPayload'])->name('desty.payloadDetail');
    Route::get('/desty/data', [DestyController::class, 'dataDesty']);
    
    Route::post('/desty/create/{id}/manual', [DestyController::class, 'createManual'])->name('desty.createManual');
    Route::get('/desty/sync', [DestySyncController::class, 'index'])->name('desty.sync.index');
    Route::get('/desty/sync/create', [DestySyncController::class, 'create'])->name('desty.sync.create');
    Route::post('/desty/sync/store', [DestySyncController::class, 'store'])->name('desty.sync.store');
    Route::get('/desty/sync/{id}/edit', [DestySyncController::class, 'edit'])->name('desty.sync.edit');
    Route::delete('/desty/sync/{id}/delete', [DestySyncController::class, 'delete'])->name('desty.sync.delete');
    Route::patch('/desty/sync/{id}/update', [DestySyncController::class, 'update'])->name('desty.sync.update');
    Route::get('/desty/sync/warehouse/create', [DestySyncController::class, 'warehouseCreate'])->name('desty.sync.warehouse.create');
    Route::post('/desty/sync/warehouse/store', [DestySyncController::class, 'warehouseStore'])->name('desty.sync.warehouse.store');
    Route::get('desty/cek', [DestyController::class, 'cek'])->name('desty.cek');

    Route::get('restock', [RestockController::class, 'index'])->name('restock.index')->middleware('permission:restock');
    Route::get('restock/create', [RestockController::class, 'create'])->name('restock.create')->middleware('permission:restock');
    Route::post('restock/add-item', [RestockController::class, 'addItem'])->name('restock.addItem')->middleware('permission:restock');
    Route::get('restock/list-item', [RestockController::class, 'listItem'])->name('restock.listItem')->middleware('permission:restock');
    Route::delete('restock/{id}/remove-item', [RestockController::class, 'removeItem'])->name('restock.removeItem')->middleware('permission:restock');
    Route::post('restock/store', [RestockController::class, 'store'])->name('restock.store')->middleware('permission:restock');
    Route::get('restock/{id}/update', [RestockController::class, 'update'])->name('restock.update')->middleware('permission:restock');
    Route::post('restock/{id}/update-qty', [RestockController::class, 'updateQty'])->name('restock.updateQty')->middleware('permission:restock');
    Route::get('restock/received', [RestockController::class, 'received'])->name('restock.received')->middleware('permission:restock');
    Route::post('restock/post-received', [RestockController::class, 'receiveStore'])->name('restock.postReceived')->middleware('permission:restock');
    Route::get('restock/{id}/history', [RestockController::class, 'history'])->name('restock.history')->middleware('permission:restock');
    Route::post('/restock/{id}/reset', [RestockController::class, 'resetSingleQty'])->name('restock.resetSingleQty')->middleware('permission:restock');
    Route::post('/restock/{id}/to-gudang-cart', [RestockController::class, 'addToGudangCart'])
        ->name('restock.toGudangCart')->middleware('permission:restock');
    Route::delete('/restock/remove-cart/{code}', [RestockController::class, 'removeCartItem'])
        ->name('restock.removeCartItem')->middleware('permission:restock');
    Route::get('/restock/upload', [RestockController::class, 'uploadExcel'])->name('restock.uploadExcel')->middleware('permission:restock');
    Route::post('/restock/import-excel', [RestockController::class, 'importExcel'])->name('restock.importExcel')->middleware('permission:restock');



    Route::get('/test-running/{id}', [ApiJubelioController::class, 'testApi']);
    // Route::get('/trx', [ApiJubelioController::class, 'cektrx']);
    // Route::get('/dua', [ApiJubelioController::class, 'dua']);

    Route::get('/tag', [TagController::class, 'index'])->name('tag.index');
    Route::get('/tag/create', [TagController::class, 'create'])->name('tag.create');
    Route::post('/tag/store', [TagController::class, 'store'])->name('tag.store');
    Route::get('/tag/{id}/edit', [TagController::class, 'edit'])->name('tag.edit');
    Route::patch('/tag/{id}/update', [TagController::class, 'update'])->name('tag.update');

    Route::get('/cron-runner', [CronrunController::class, 'index'])->name('cronrunner.index')->middleware('permission:cron runner');
    Route::get('/cron-runner/{id}/edit', [CronrunController::class, 'edit'])->name('cronrunner.edit')->middleware('permission:cron runner');
    Route::patch('/cron-runner/{id}/update', [CronrunController::class, 'update'])->name('cronrunner.update')->middleware('permission:cron runner');

    Route::get('/jubelio/sync', [JubelioSyncController::class, 'index'])->name('jubelio.sync.index')->middleware('permission:jubelio sync');
    Route::get('/jubelio/sync/{id}/edit', [JubelioSyncController::class, 'edit'])->name('jubelio.sync.edit')->middleware('permission:jubelio sync');
    Route::patch('/jubelio/sync/{id}/update', [JubelioSyncController::class, 'update'])->name('jubelio.sync.update')->middleware('permission:jubelio sync');
    Route::get('/jubelio/sync/{id}/getBin', [JubelioSyncController::class, 'getBin'])->name('jubelio.sync.getBin')->middleware('permission:jubelio sync');
    Route::delete('/jubelio/sync/{id}/delete', [JubelioSyncController::class, 'delete'])->name('jubelio.sync.delete')->middleware('permission:jubelio sync');
    Route::get('/jubelio/sync/create', [JubelioSyncController::class, 'create'])->name('jubelio.sync.create')->middleware('permission:jubelio sync');
    Route::post('/jubelio/sync/store', [JubelioSyncController::class, 'store'])->name('jubelio.sync.store')->middleware('permission:jubelio sync');

    Route::get('/jubelio/webhook', [JubelioController::class, 'index'])->name('jubelio.webhook.order')->middleware('permission:jubelio webhook');
    Route::get('/jubelio/webhook/{id}/detail', [JubelioController::class, 'detail'])->name('jubelio.webhook.detail')->middleware('permission:jubelio webhook');
    Route::get('/jubelio/webhook/{id}/manual/create', [JubelioController::class, 'createManual'])->name('jubelio.webhook.createManual')->middleware('permission:jubelio webhook');
    Route::post('/jubelio/webhook/{id}/manual/store', [JubelioController::class, 'storeManual'])->name('jubelio.webhook.storeManual')->middleware('permission:jubelio webhook');
    Route::get('/jubelio/webhook/{id}/solved/create', [JubelioController::class, 'createSolved'])->name('jubelio.webhook.createSolved')->middleware('permission:jubelio webhook');
    Route::post('/jubelio/webhook/{id}/solved/store', [JubelioController::class, 'storeSolved'])->name('jubelio.webhook.storeSolved')->middleware('permission:jubelio webhook');

    Route::get('/jubelio/order/getall', [JubelioGetOrderController::class, 'index'])->name('jubelio.order.getall')->middleware('permission:transactions.jubelio.return');
    Route::post('/jubelio/order/getall/reset', [JubelioGetOrderController::class, 'reset'])->name('jubelio.order.getallreset')->middleware('permission:transactions.jubelio.return');
    Route::post('/jubelio/order/getall/store', [JubelioGetOrderController::class, 'store'])->name('jubelio.order.storegetall')->middleware('permission:transactions.jubelio.return');
    Route::post('/jubelio/order/getall/cekTransaksi', [JubelioGetOrderController::class, 'cekTransaction'])->name('jubelio.order.cekTransaction')->middleware('permission:transactions.jubelio.return');
    Route::post('/jubelio/order/getall/toLog', [JubelioGetOrderController::class, 'toLog'])->name('jubelio.order.toLog')->middleware('permission:transactions.jubelio.return');
    Route::post('/jubelio/order/getall/deleteAll', [JubelioGetOrderController::class, 'deleteAll'])->name('jubelio.order.deleteAll')->middleware('permission:transactions.jubelio.return');

    Route::get('/jubelio/order/cek', [JubelioController::class, 'cekOrder'])->name('jubelio.order.cek')->middleware('permission:jubelio cek order');

    Route::get('/jubelio/return', [JubelioReturnController::class, 'index'])->name('jubelio.return.index')->middleware('permission:jubelio cek order');
    Route::get('/jubelio/return/{id}/detail', [JubelioReturnController::class, 'jubelioReturn'])->name('jubelio.return.detail')->middleware('permission:jubelio cek order');
    Route::post('/jubelio/return/{id}/store', [JubelioReturnController::class, 'jubelioReturnPost'])->name('jubelio.return.store')->middleware('permission:jubelio cek order');
    Route::get('/jubelio/return/{id}/finished', [JubelioReturnController::class, 'createSolved'])->name('jubelio.return.finished')->middleware('permission:jubelio cek order');
    Route::post('/jubelio/return/{id}/finishedstore', [JubelioReturnController::class, 'storeSolved'])->name('jubelio.return.finishedstore')->middleware('permission:jubelio cek order');


    Route::get('/get/seles-order', [ApiJubelioController::class, 'getSaleOrder'])->name('jubelio.getSaleOrder');
    Route::get('/log/system', [SettingController::class, 'systemLog'])->name('log.system');



    Route::get('/logjubelio', [LogJubelioController::class, 'index'])->name('jubelio.log.index');
    Route::get('/logjubelio/{id}/json', [LogJubelioController::class, 'viewJson'])->name('jubelio.log.viewJson');
    Route::get('/logjubelio/{id}/transaction/detail', [LogJubelioController::class, 'gotoTransaction'])->name('jubelio.transaction.detail');

    Route::get('/jubelio/manual/{id}/create', [LogJubelioController::class, 'createManual'])->name('jubelio.manual.create');
    Route::post('/jubelio/manual/{id}/store', [LogJubelioController::class, 'postManualSeek'])->name('jubelio.manual.store');
    Route::get('/jubelio/solved/{id}/create', [LogJubelioController::class, 'createSolved'])->name('jubelio.solved.create');
    Route::post('/jubelio/solved/{id}/store', [LogJubelioController::class, 'storeSolved'])->name('jubelio.solved.store');

    Route::post('/jubelio/adjust/{id}/warehouse', [ApiJubelioController::class, 'adjustStok'])->name('jubelio.adjustStok');

       // Jubelio Stock Check Routes
    Route::resource('jubelio-stock-checks', JubelioStockCheckController::class)->only(['index', 'create', 'store', 'show', 'destroy']);


    Route::get('/cash-flow', [CashFlowController::class, 'index'])->name('cashflow.index');
    Route::get('/cash-flow/book-addrs', [CashFlowController::class, 'book'])->name('cashflow.book');

    Route::get('/location', [LocationController::class, 'index'])->name('location.index')->middleware('permission:location');
    Route::get('/location/create', [LocationController::class, 'create'])->name('location.create')->middleware('permission:location');
    Route::post('/location/store', [LocationController::class, 'store'])->name('location.store')->middleware('permission:location');
    Route::get('/location/{id}/detail', [LocationController::class, 'locationDetail'])->name('location.detail')->middleware('permission:location');
    Route::post('/location/{id}/storeLocation', [LocationController::class, 'storeLocation'])->name('location.storeLocation')->middleware('permission:location');

    Route::post('/location/{id}/{cis}/dismis', [LocationController::class, 'dismis'])->name('location.dismis')->middleware('permission:location');

    Route::get('/location/{id}/edit', [LocationController::class, 'edit'])->name('location.edit')->middleware('permission:location');
    Route::patch('/location/{id}/update', [LocationController::class, 'update'])->name('location.update')->middleware('permission:location');





    Route::get('/compare', [CompareController::class, 'index'])->name('compare.index')->middleware('permission:report.compare');
    Route::post('/compare/store', [CompareController::class, 'store'])->name('compare.store')->middleware('permission:report.compare');
    Route::delete('/compare/{id}/delete', [CompareController::class, 'delete'])->name('compare.delete')->middleware('permission:report.compare');

    Route::get('/report/itemsale', [StatSellController::class, 'index'])->name('statsale.index')->middleware('permission:report.itemsale');

    Route::get('/transaction/po', [PoController::class, 'index'])->name('transaction.Poindex')->middleware('permission:cnpo list');
    Route::get('/transaction/po/{id}/detail', [PoController::class, 'getDetail'])->name('transaction.Podetail')->middleware('permission:cnpo detail');
    Route::get('/transaction/po/{id}/move', [PoController::class, 'poMove'])->name('transaction.poMove')->middleware('permission:cnpo detail');
    Route::post('/transaction/po/{id}/move/store', [PoController::class, 'postMove'])->name('transaction.poMovePost')->middleware('permission:cnpo detail');
    Route::patch('/transaction/po/{id}/update/qty', [PoController::class, 'updateQty'])->name('transaction.poUpdateQty')->middleware('permission:cnpo detail');
    Route::post('/transaction/po/delete/batch', [PoController::class, 'batchDelete'])->name('transaction.poBatchDelete')->middleware('permission:cnpo detail');



    Route::delete('/transaction/po/{id}/delete', [PoController::class, 'delete'])->name('transaction.podelete')->middleware('permission:cnpo delete');

    Route::patch('/transaction/po/item/{id}/update', [PoController::class, 'updateItemQty'])->name('transaction.PoUpdateItemQty')->middleware('permission:cnpo update');
    Route::patch('/transaction/po/item/{id}/kosong', [PoController::class, 'updateItemKosong'])->name('transaction.updateItemKosong')->middleware('permission:cnpo kosong');
    Route::patch('/transaction/po/item/{id}/success', [PoController::class, 'success'])->name('transaction.poSuccess')->middleware('permission:cnpo update');

    Route::get('/ajax/getCustomer', [AjaxController::class, 'getCostumer'])->name('ajax.getCostumer');
    Route::get('/ajax/getTag', [AjaxController::class, 'getTag'])->name('ajax.getTag');
    Route::get('/ajax/getItemSetoran', [AjaxController::class, 'getItemSetoran'])->name('ajax.getItemSetoran');
    Route::get('/ajax/getCustomerCash', [AjaxController::class, 'getCostumerCash'])->name('ajax.getCostumerCash');
    Route::get('/ajax/getCustomerSingle', [AjaxController::class, 'getCostumerSingle'])->name('ajax.getCostumerSingle');
    Route::post('/ajax/sellBatch', [AjaxController::class, 'sellBatch'])->name('ajax.sellBatch');
    Route::get('/getItem', [AjaxController::class, 'getItemAjax'])->name('ajax.getitem');
    Route::get('/getItemId', [AjaxController::class, 'getItemId'])->name('ajax.getitemId');
    Route::get('/getItemName', [AjaxController::class, 'getItem'])->name('ajax.getitemName');
    Route::get('/getItemByAll', [AjaxController::class, 'getItemByAll'])->name('ajax.getitembyall');
    Route::get('/getInvoice', [AjaxController::class, 'getInvoice'])->name('ajax.getInvoice');
    Route::post('/scan-barcode', [AjaxController::class, 'processBarcode']);
    Route::get('/ajax/getWarna', [AjaxController::class, 'getWarna'])->name('ajax.getWarna');
    Route::get('/ajax/getType', [AjaxController::class, 'getType'])->name('ajax.getType');


    Route::get('/transaction', [TransactionsController::class, 'index'])->name('transaction.index')->middleware('permission:transactions.list');
    Route::get('/transaction/filter', [FilterQueryController::class, 'transactionFilter'])->name('transaction.filter')->middleware('permission:transactions.list');

    Route::get('/transaction/sell', [TransactionsController::class, 'sell'])->name('transaction.sell')->middleware('permission:transactions.sell');
    Route::post('/transaction/sell/post', [TransactionsController::class, 'postSell'])->name('transaction.postSell')->middleware('permission:transactions.sell');

    Route::get('/transaction/sell-batch', [TransactionsController::class, 'sellBatch'])->name('transaction.postSellBatch')->middleware('permission:transactions.sellbatch');
    Route::post('/transaction/sell-batch/store', [TransactionsController::class, 'postSellBatch'])->name('transaction.sellBatchStore')->middleware('permission:transactions.sellbatch');

    Route::get('/transaction/buy', [TransactionsController::class, 'buy'])->name('transaction.buy')->middleware('permission:transactions.buy');
    Route::post('/transaction/buy/post', [TransactionsController::class, 'postbuy'])->name('transaction.postBuy')->middleware('permission:transactions.buy');

    Route::get('/transaction/buy-batch', [TransactionsController::class, 'buyBatch'])->name('transaction.postBuyBatch')->middleware('permission:transactions.buy');
    Route::post('/transaction/buy-batch/store', [TransactionsController::class, 'postBuyBatch'])->name('transaction.buyBatchStore')->middleware('permission:transactions.buy');

    Route::get('/transaction/move', [TransactionsController::class, 'move'])->name('transaction.move')->middleware('permission:transactions.move');
    Route::post('/transaction/move/post', [TransactionsController::class, 'postmove'])->name('transaction.postMove')->middleware('permission:transactions.move');
    Route::get('/transaction/move-batch', [TransactionsController::class, 'moveBatch'])->name('transaction.postMoveBatch')->middleware('permission:transactions.movebatch');
    Route::post('/transaction/move-batch/store', [TransactionsController::class, 'postMoveBatch'])->name('transaction.moveBatchStore')->middleware('permission:transactions.movebatch');

    Route::get('/transaction/use', [TransactionsController::class, 'use'])->name('transaction.use')->middleware('permission:transactions.use');
    Route::post('/transaction/use/post', [TransactionsController::class, 'postuse'])->name('transaction.postUse')->middleware('permission:transactions.use');

    Route::get('/transaction/cash-in', [TransactionsController::class, 'cashIn'])->name('transaction.cashIn')->middleware('permission:transactions.cashIn');
    Route::post('/transaction/cash-in/post', [TransactionsController::class, 'postCashIn'])->name('transaction.cashInPost')->middleware('permission:transactions.cashIn');

    Route::get('/transaction/cash-out', [TransactionsController::class, 'cashOut'])->name('transaction.cashOut')->middleware('permission:transactions.cashOut');
    Route::post('/transaction/cash-out/post', [TransactionsController::class, 'postCashOut'])->name('transaction.cashOutPost')->middleware('permission:transactions.cashOut');

    Route::get('/transaction/adjust', [TransactionsController::class, 'adjust'])->name('transaction.adjust')->middleware('permission:transactions.adjust');
    Route::post('/transaction/adjust/post', [TransactionsController::class, 'postAdjust'])->name('transaction.postAdjust')->middleware('permission:transactions.adjust');

    Route::get('/transaction/transfer', [TransactionsController::class, 'transfer'])->name('transaction.transfer')->middleware('permission:transactions.transfer');
    Route::post('/transaction/transfer/post', [TransactionsController::class, 'postTransfer'])->name('transaction.postTransfer')->middleware('permission:transactions.transfer');

    Route::get('/transaction/return', [TransactionsController::class, 'return'])->name('transaction.return')->middleware('permission:transactions.return');
    Route::post('/transaction/return/post', [TransactionsController::class, 'postReturn'])->name('transaction.postReturn')->middleware('permission:transactions.return');

    Route::get('/transaction/return-supplier', [TransactionsController::class, 'returnSupplier'])->name('transaction.returnSupplier')->middleware('permission:transactions transactions.returnSuplier');
    Route::post('/transaction/return-supplier/post', [TransactionsController::class, 'postReturnSupplier'])->name('transaction.postReturnSupplier')->middleware('permission:transactions.returnSuplier');

    Route::get('/transaction/{id}/return/jubelio', [TransactionsController::class, 'jubelioReturn'])->name('transaction.jubelioReturn')->middleware('permission:transactions.detail');

    Route::post('/transaction/{id}/return/jubelio/store', [TransactionsController::class, 'jubelioReturnPost'])->name('transaction.jubelioReturnPost')->middleware('permission:transactions.detail');


    Route::get('/transaction/{id}/detail', [TransactionsController::class, 'getDetail'])->name('transaction.getDetail')->middleware('permission:transactions.detail');

    Route::get('/transaction/{id}/invoice', [TransactionsController::class, 'generateInvoice'])->name('transaction.genereteInvoice')->middleware('permission:transactions.detail');

    Route::get('/transaction/{id}/receipt', [TransactionsController::class, 'generateReceipt'])->name('transaction.generateReceipt')->middleware('permission:transactions.detail');

    Route::post('/transaction/{id}/sendWa', [TransactionsController::class, 'sendToWhatsapp'])->name('transaction.sendToWhatsapp')->middleware('permission:transactions.detail');

    Route::get('/transaction/export/sell/item', [ExportController::class, 'sellItem'])->name('export.sellItem')->middleware('permission:transactions.detail');
    Route::get('/transaction/export/sell/item/build', [ExportController::class, 'exportSellItem'])->name('export.sellItemBuild')->middleware('permission:transactions.detail');
    Route::get('/transaction/export/sell/item/build/desty', [ExportController::class, 'exportDesty'])->name('export.desty')->middleware('permission:transactions.detail');

    Route::get('/transaction/sync', [TransactionsController::class, 'transactionSync'])->name('transaction.transactionSync')->middleware('permission:transactions.detail');
    
     Route::get('/transaction/{id}/desty/export', [TransactionsController::class, 'destyExport'])->name('transaction.exportDesty')->middleware('permission:transactions.detail');

    Route::patch('/transaction/sync/{id}/display', [TransactionsController::class, 'transactionSyncDisplay'])->name('transaction.transactionSyncDisplay')->middleware('permission:transactions.detail');

    Route::get('/transaction/{id}/detail/jubelio-sync', [TransactionsController::class, 'detailJubelioSync'])->name('transaction.detailJubelioSync')->middleware('permission:transactions.detail');
    

    Route::get('/transaction/{id}/detail/jubelio-sync/warning', [TransactionsController::class, 'warning'])->name('transaction.warningJubelioSync')->middleware('permission:transactions.detail');

    Route::post('/transaction/{id}/detail/jubelio-sync/confirmation', [TransactionsController::class, 'warningKonfirmasi'])->name('transaction.warningKonfirmasiJubelioSync')->middleware('permission:transactions.detail');

    Route::post('/transaction/{id}/detail/jubelio-sync/clear', [TransactionsController::class, 'clearWarning'])->name('transaction.clearWarningJubelioSync')->middleware('permission:transactions.detail');

    Route::get('/transaction/{id}/detail/desty-sync', [TransactionsController::class, 'detailDestySync'])->name('transaction.detailDestySync')->middleware('permission:transactions.detail');


    Route::get('/transaction/{id}/edit/note', [TransactionsController::class, 'editNote'])->name('transaction.editNote')->middleware('permission:transactions.detail');
    Route::patch('/transaction/{id}/update/note', [TransactionsController::class, 'updateNote'])->name('transaction.updateNote')->middleware('permission:transactions.detail');

    Route::get('/transaction/delete', [DeletedController::class, 'index'])->name('transaction.delete')->middleware('permission:transactions.deleteList');

    Route::get('/transaction/delete/filter', [FilterQueryController::class, 'transactionFilterDelete'])->name('transaction.deletefilter')->middleware('permission:transactions.deleteList');
    Route::get('/transaction/{id}/delete/detail', [DeletedController::class, 'getDetailDelete'])->name('transaction.getDetailDelete')->middleware('permission:transactions.deleteList');
    Route::delete('/transaction/{id}/destroy', [TransactionsController::class, 'postDelete'])->name('transaction.destroy')->middleware('permission:transactions.delete');

    Route::get('/transaction/{id}/success', function ($id) {
        return redirect()->route('transaction.getDetail', $id)->with('success', 'Transaction # ' . $id . ' created.');
    })->name('transaction.success');


    Route::get('/item', [ItemsController::class, 'index'])->name('item.index')->middleware('permission:item list');
    Route::get('/item/create', [ItemsController::class, 'create'])->name('item.create')->middleware('permission:item create');
    Route::post('/item/store', [ItemsController::class, 'postCreate'])->name('item.post')->middleware('permission:item create');
    Route::get('/item/{id}/edit', [ItemsController::class, 'edit'])->name('item.edit')->middleware('permission:item edit');
    Route::post('/item/{id}/update', [ItemsController::class, 'postEdit'])->name('item.update')->middleware('permission:item edit');
    Route::get('/item/filter', [FilterQueryController::class, 'itemFilter'])->name('item.filter')->middleware('permission:item search');
    Route::get('/item/{id}/detail', [ItemsController::class, 'detail'])->name('item.detail')->middleware('permission:item detail');

    Route::get('/item/{id}/jubelio', [ItemsController::class, 'jubelio'])->name('item.jubelio')->middleware('permission:item detail');

    Route::get('/item/{id}/jubelio/cek', [AsetLancarController::class, 'getItem'])->name('item.jubelioGetItem')->middleware('permission:item detail');
    Route::patch('/item/{id}/jubelio/cek/update', [AsetLancarController::class, 'updateJubelioId'])->name('item.updateJubelioId')->middleware('permission:item detail');

    Route::get('/item/{id}/transaction', [ItemsController::class, 'transaction'])->name('item.transaction')->middleware('permission:item transaction');
    Route::get('/item/{id}/transaction/filter', [FilterQueryController::class, 'itemTransFilter'])->name('item.transactionFilter')->middleware('permission:item transaction');
    Route::get('/item/{id}/stat', [ItemsController::class, 'stat'])->name('item.stat')->middleware('permission:item stat');
    Route::get('/item/{id}/stat/filter', [FilterQueryController::class, 'itemStatFilter'])->name('item.statFilter')->middleware('permissionitem stat');
    Route::get('/item/group', [ItemsController::class, 'group'])->name('item.group')->middleware('permission:item group');
    Route::get('/item/group/filter', [FilterQueryController::class, 'itemGroupTransFilter'])->name('item.filterGroup')->middleware('permission:item group');
    Route::get('/item/group/{id}/detail', [ItemsController::class, 'groupDetail'])->name('item.detailGroup')->middleware('permission:item group');
    Route::get('/item/group/{id}/stat', [ItemsController::class, 'groupStat'])->name('item.statGroup')->middleware('permission:item group');
    Route::get('/item/group/{id}/stat/filter', [FilterQueryController::class, 'itemGroupStatFilter'])->name('item.statFilterGroup')->middleware('permission:item group');

    Route::get('/asset-lancar', [AsetLancarController::class, 'index'])->name('asetLancar.index')->middleware('permission:asset lancar list');
    Route::get('/asset-lancar/create', [AsetLancarController::class, 'create'])->name('asetLancar.create')->middleware('permission:asset lancar create');
    Route::post('/asset-lancar/store', [AsetLancarController::class, 'postCreate'])->name('asetLancar.postCreate')->middleware('permission:asset lancar create');
    Route::get('/asset-lancar/{id}/edit', [AsetLancarController::class, 'edit'])->name('asetLancar.edit')->middleware('permission:asset lancar edit');
    Route::post('/asset-lancar/{id}/update', [AsetLancarController::class, 'postEdit'])->name('asetLancar.update')->middleware('permission:asset lancar edit');
    Route::get('/asset-lancar/{id}/duplicate', [AsetLancarController::class, 'duplicate'])->name('asetLancar.duplicate')->middleware('permission:asset lancar create');
    Route::get('/asset-lancar/filter', [FilterQueryController::class, 'assetLancarFilter'])->name('asetLancar.filter')->middleware('permission:asset lancar list');
    Route::get('/asset-lancar/{id}/detail', [AsetLancarController::class, 'detail'])->name('asetLancar.detail')->middleware('permission:asset lancar detail');


    Route::get('/asset-lancar/{id}/jubelio', [AsetLancarController::class, 'jubelio'])->name('asetLancar.jubelio')->middleware('permission:asset lancar detail');

    Route::get('/asset-lancar/{id}/jubelio/cek', [AsetLancarController::class, 'getItem'])->name('asetLancar.jubelioGetItem')->middleware('permission:asset lancar detail');
    Route::patch('/asset-lancar/{id}/jubelio/cek/update', [AsetLancarController::class, 'updateJubelioId'])->name('asetLancar.updateJubelioId')->middleware('permission:asset lancar detail');



    Route::get('/asset-lancar/{id}/transaction', [AsetLancarController::class, 'transaction'])->name('asetLancar.transaction')->middleware('permission:asset lancar transaction');
    Route::get('/asset-lancar/{id}/transaction/filter', [FilterQueryController::class, 'assetLancarTransFilter'])->name('asetLancar.transactionFilter')->middleware('permission:asset lancar transaction');
    Route::get('/asset-lancar/{id}/stat', [AsetLancarController::class, 'stat'])->name('asetLancar.stat')->middleware('permission:asset lancar stat');
    Route::get('/asset-lancar/{id}/stat/filter', [FilterQueryController::class, 'assetLancarStatFilter'])->name('asetLancar.statFilter')->middleware('permission:asset lancar stat');

    Route::get('/contributors', [ContributorController::class, 'index'])->name('contributor.index')->middleware('permission:contributor');
    Route::get('/contributors/filter', [FilterQueryController::class, 'contributorFilter'])->name('contributor.filter')->middleware('permission:contributor');

    Route::get('/customer', [CustomerController::class, 'index'])->name('customer.index')->middleware('permission:customer list')->middleware('permission:customer list');
    Route::get('/customer/create', [CustomerController::class, 'create'])->name('customer.create')->middleware('permission:customer create')->middleware('permission:customer create');
    Route::get('/customer/{id}/edit', [CustomerController::class, 'edit'])->name('customer.edit')->middleware('permission:customer edit')->middleware('permission:customer edit');
    Route::post('/customer/{id}/update', [CustomerController::class, 'postEdit'])->name('customer.update')->middleware('permission:customer edit')->middleware('permission:customer edit');
    Route::get('/customer/{id}/transaction', [CustomerController::class, 'transaction'])->name('customer.transaction')->middleware('permission:customer transaction')->middleware('permission:customer transaction');
    Route::get('/customer/{id}/detail', [CustomerController::class, 'detail'])->name('customer.detail')->middleware('permission:customer detail')->middleware('permission:customer detail');
    Route::get('/customer/{id}/item', [CustomerController::class, 'items'])->name('customer.items')->middleware('permission:customer item')->middleware('permission:customer item');
    Route::get('/customer/{id}/stat', [CustomerController::class, 'stat'])->name('customer.stat')->middleware('permission:customer stat')->middleware('permission:customer stat');
    Route::get('/customer/{id}/itemsale', [CustomerController::class, 'itemsale'])->name('customer.itemsale')->middleware('permission:customer stat');


    Route::get('/supplier', [SupplierController::class, 'index'])->name('supplier.index')->middleware('permission:supplier list');
    Route::get('/supplier/create', [SupplierController::class, 'create'])->name('supplier.create')->middleware('permission:supplier create');
    Route::get('/supplier/{id}/edit', [SupplierController::class, 'edit'])->name('supplier.edit')->middleware('permission:supplier edit');
    Route::get('/supplier/{id}/transaction', [SupplierController::class, 'transaction'])->name('supplier.transaction')->middleware('permission:supplier transaction');
    Route::get('/supplier/{id}/detail', [SupplierController::class, 'detail'])->name('supplier.detail')->middleware('permission:supplier detail');
    Route::get('/supplier/{id}/item', [SupplierController::class, 'items'])->name('supplier.items')->middleware('permission:supplier item');
    Route::get('/supplier/{id}/stat', [SupplierController::class, 'stat'])->name('supplier.stat')->middleware('permission:supplier stat');
    Route::get('/supplier/{id}/itemsale', [SupplierController::class, 'itemsale'])->name('supplier.itemsale')->middleware('permission:supplier stat');

    Route::get('/warehouse', [WarehouseController::class, 'index'])->name('warehouse.index')->middleware('permission:warehouse list');
    Route::get('/warehouse/create', [WarehouseController::class, 'create'])->name('warehouse.create')->middleware('permission:warehouse create');
    Route::get('/warehouse/{id}/edit', [WarehouseController::class, 'edit'])->name('warehouse.edit')->middleware('permission:warehouse edit');
    Route::get('/warehouse/{id}/transaction', [WarehouseController::class, 'transaction'])->name('warehouse.transaction')->middleware('permission:warehouse transaction');
    Route::get('/warehouse/{id}/detail', [WarehouseController::class, 'detail'])->name('warehouse.detail')->middleware('permission:warehouse detail');
    Route::get('/warehouse/{id}/item', [WarehouseController::class, 'items'])->name('warehouse.items')->middleware('permission:warehouse item');
    Route::get('/warehouse/{id}/stat', [WarehouseController::class, 'stat'])->name('warehouse.stat')->middleware('permission:warehouse stat');
    Route::get('/warehouse/{id}/itemsale', [WarehouseController::class, 'itemsale'])->name('warehouse.itemsale')->middleware('permission:warehouse stat');
    Route::get('/warehouse/{id}/sync', [WarehouseController::class, 'syncJubelio'])->name('warehouse.jubelio')->middleware('permission:warehouse detail');
    Route::patch('/warehouse/{id}/syncStore', [WarehouseController::class, 'syncJubelioStore'])->name('warehouse.syncJubelioStore')->middleware('permission:warehouse detail');

    Route::get('/vwarehouse', [VWarehouseController::class, 'index'])->name('vwarehouse.index')->middleware('permission:vwarehouse list');
    Route::get('/vwarehouse/create', [VWarehouseController::class, 'create'])->name('vwarehouse.create')->middleware('permission:vwarehouse create');
    Route::get('/vwarehouse/{id}/edit', [VWarehouseController::class, 'edit'])->name('vwarehouse.edit')->middleware('permission:vwarehouse edit');
    Route::get('/vwarehouse/{id}/transaction', [VWarehouseController::class, 'transaction'])->name('vwarehouse.transaction')->middleware('permission:vwarehouse transaction');
    Route::get('/vwarehouse/{id}/detail', [VWarehouseController::class, 'detail'])->name('vwarehouse.detail')->middleware('permission:vwarehouse detail');
    Route::get('/vwarehouse/{id}/item', [VWarehouseController::class, 'items'])->name('vwarehouse.items')->middleware('permission:vwarehouse item');
    Route::get('/vwarehouse/{id}/stat', [VWarehouseController::class, 'stat'])->name('vwarehouse.stat')->middleware('permission:vwarehouse stat');
    Route::get('/vwarehouse/{id}/itemsale', [VWarehouseController::class, 'itemsale'])->name('vwarehouse.itemsale')->middleware('permission:vwarehouse stat');



    Route::get('/account', [AccountController::class, 'index'])->name('account.index')->middleware('permission:account list');
    Route::get('/account/create', [AccountController::class, 'create'])->name('account.create')->middleware('permission:account create');
    Route::get('/account/{id}/edit', [AccountController::class, 'edit'])->name('account.edit')->middleware('permission:account edit');
    Route::get('/account/{id}/transaction', [AccountController::class, 'transaction'])->name('account.transaction')->middleware('permission:account transaction');
    Route::get('/account/{id}/detail', [AccountController::class, 'detail'])->name('account.detail')->middleware('permission:account detail');
    Route::get('/account/{id}/item', [AccountController::class, 'items'])->name('account.items')->middleware('permission:account item');
    Route::get('/account/{id}/stat', [AccountController::class, 'stat'])->name('account.stat')->middleware('permission:account stat');
    Route::get('/account/{id}/itemsale', [AccountController::class, 'itemsale'])->name('account.itemsale')->middleware('permission:account stat')->middleware('permission:account stat');


    Route::get('/vaccount', [VAccountController::class, 'index'])->name('vaccount.index')->middleware('permission:vaccount list');
    Route::get('/vaccount/create', [VAccountController::class, 'create'])->name('vaccount.create')->middleware('permission:vaccount create');
    Route::get('/vaccount/{id}/edit', [VAccountController::class, 'edit'])->name('vaccount.edit')->middleware('permission:vaccount edit');
    Route::get('/vaccount/{id}/transaction', [VAccountController::class, 'transaction'])->name('vaccount.transaction')->middleware('permission:vaccount transaction');
    Route::get('/vaccount/{id}/detail', [VAccountController::class, 'detail'])->name('vaccount.detail')->middleware('permission:vaccount detail');
    Route::get('/vaccount/{id}/item', [VAccountController::class, 'items'])->name('vaccount.items')->middleware('permission:vaccount item');
    Route::get('/vaccount/{id}/stat', [VAccountController::class, 'stat'])->name('vaccount.stat')->middleware('permission:vaccount stat');
    Route::get('/vaccount/{id}/itemsale', [VAccountController::class, 'itemsale'])->name('vaccount.itemsale')->middleware('permission:vaccount stat');


    Route::get('/reseller', [ResellerController::class, 'index'])->name('reseller.index')->middleware('permission:reseller list');
    Route::get('/reseller/create', [ResellerController::class, 'create'])->name('reseller.create')->middleware('permission:reseller create');
    Route::get('/reseller/{id}/edit', [ResellerController::class, 'edit'])->name('reseller.edit')->middleware('permission:reseller edit');
    Route::get('/reseller/{id}/transaction', [ResellerController::class, 'transaction'])->name('reseller.transaction')->middleware('permission:reseller transaction');
    Route::get('/reseller/{id}/detail', [ResellerController::class, 'detail'])->name('reseller.detail')->middleware('permission:reseller detail');
    Route::get('/reseller/{id}/item', [ResellerController::class, 'items'])->name('reseller.items')->middleware('permission:reseller item');
    Route::get('/reseller/{id}/stat', [ResellerController::class, 'stat'])->name('reseller.stat')->middleware('permission:reseller stat');
    Route::get('/reseller/{id}/itemsale', [ResellerController::class, 'itemsale'])->name('reseller.itemsale')->middleware('permission:reseller stat');

    Route::post('/addrbook/store', [AddrbookController::class, 'postCreate'])->name('addrbook.store')->middleware('permission:customer create|supplier create|reseller create|warehouse create|vwarehouse create|account create|vaccount create');
    Route::post('/addrbook/{id}/update', [AddrbookController::class, 'postEdit'])->name('addrbook.update')->middleware('permission:customer edit|supplier edit|reseller edit|warehouse edit|vwarehouse edit|account edit|vaccount edit');
    Route::post('/addrbook/{id}/delete', [AddrbookController::class, 'postDelete'])->name('addrbook.delete')->middleware('permission:customer delete|supplier delete|reseller delete|warehouse delete|vwarehouse delete|account delete|vaccount delete');
    Route::post('/addrbook/{id}/restore', [AddrbookController::class, 'postRestore'])->name('addrbook.restore')->middleware('permission:customer restore|supplier restore|reseller restore|warehouse restore|vwarehouse restore|account restore|vaccount restore');
    Route::get('/addrbook/downloand/transaction', [AddrbookController::class, 'exportTransaction'])->name('export.transaction')->middleware('permission:customer transaction');
    Route::get('/addrbook/downloand/item', [AddrbookController::class, 'exportItem'])->name('export.item')->middleware('permission:customer item');

    Route::get('/operation', [OperationController::class, 'index'])->name('operation.index')->middleware('permission:operation list');
    Route::get('/operation/create', [OperationController::class, 'create'])->name('operation.create')->middleware('permission:operation create');
    Route::post('/operation/store', [OperationController::class, 'store'])->name('operation.store')->middleware('permission:operation create');
    Route::get('/operation/{id}/edit', [OperationController::class, 'edit'])->name('operation.edit')->middleware('permission:operation edit');
    Route::patch('/operation/{id}/store', [OperationController::class, 'update'])->name('operation.update')->middleware('permission:operation edit');
    Route::get('/operation/{id}/account/edit', [OperationController::class, 'editAccount'])->name('operation.account.edit')->middleware('permission:operation account edit');
    Route::patch('/operation/{id}/account/update', [OperationController::class, 'updateAccount'])->name('operation.account.update')->middleware('permission:operation account edit');
    Route::get('/operation/{id}/detail', [OperationController::class, 'detail'])->name('operation.detail')->middleware('permission:operation detail');
    Route::get('/operation/{id}/account', [OperationController::class, 'account'])->name('operation.account')->middleware('permission:operation account detail');
    Route::get('/operation/account', [OperationController::class, 'accountList'])->name('operation.account.list')->middleware('permission:operation account');
    Route::get('/operation/account/create', [OperationController::class, 'createAccount'])->name('operation.account.create')->middleware('permission:operation account create');
    Route::post('/operation/account/store', [OperationController::class, 'postCreateAccount'])->name('operation.postCreateAccount')->middleware('permission:operation account create');

    Route::get('/produksi', [ProduksiController::class, 'index'])->name('produksi.index')->middleware('permission:produksi list');
    Route::get('/produksi/create', [ProduksiController::class, 'create'])->name('produksi.create')->middleware('permission:produksi create');
    Route::post('/produksi/store', [ProduksiController::class, 'storeProduksi'])->name('produksi.store')->middleware('permission:produksi create');
    Route::get('/produksi/{id}/detail', [ProduksiController::class, 'detail'])->name('produksi.detail')->middleware('permission:produksi detail');
    Route::patch('/produksi/{id}/detail/updateJahit', [ProduksiController::class, 'postSaveRow'])->name('produksi.postSaveRow')->middleware('permission:produksi edit');
    Route::patch('/produksi/{id}/detail/updatewc', [ProduksiController::class, 'postEdit'])->name('produksi.postEdit')->middleware('permission:produksi edit');
    Route::post('/produksi/{id}/detail/split', [ProduksiController::class, 'postPisahJahit'])->name('produksi.postPisahJahit')->middleware('permission:produksi edit');
    Route::patch('/produksi/{id}/detail/gantiJahit', [ProduksiController::class, 'postGantiJahit'])->name('produksi.postGantiJahit')->middleware('permission:produksi edit');

    Route::patch('/produksi/{id}/setor', [ProduksiController::class, 'postSetor'])->name('produksi.postSetor')->middleware('permission:produksi setor');

    Route::get('/produksi/potong/list', [ProduksiController::class, 'getPotongList'])->name('produksi.getPotongList')->middleware('permission:produksi potong');
    Route::get('/produksi/potong/create', [ProduksiController::class, 'getPotongCreate'])->name('produksi.getPotongCreate')->middleware('permission:produksi potong create');
    Route::post('/produksi/potong/store', [ProduksiController::class, 'createPotong'])->name('produksi.createPotong')->middleware('permission:produksi potong create');
    Route::get('/produksi/potong/{id}/edit', [ProduksiController::class, 'getPotongEdit'])->name('produksi.getPotongEdit')->middleware('permission:produksi potong edit');
    Route::patch('/produksi/potong/{id}/update', [ProduksiController::class, 'updatePotong'])->name('produksi.updatePotong')->middleware('permission:produksi potong edit');
    Route::delete('/produksi/potong/{id}/delete', [ProduksiController::class, 'postDeletePotong'])->name('produksi.postDeletePotong')->middleware('permission:produksi potong delete');

    Route::get('/produksi/jahit/list', [ProduksiController::class, 'getJahitList'])->name('produksi.getJahitList')->middleware('permission:produksi jahit');
    Route::get('/produksi/jahit/create', [ProduksiController::class, 'getJahitCreate'])->name('produksi.getJahitCreate')->middleware('permission:produksi jahit create');
    Route::post('/produksi/jahit/store', [ProduksiController::class, 'createJahit'])->name('produksi.createJahit')->middleware('permission:produksi jahit create');
    Route::get('/produksi/jahit/{id}/edit', [ProduksiController::class, 'getJahitEdit'])->name('produksi.getJahitEdit')->middleware('permission:produksi jahit edit');
    Route::patch('/produksi/jahit/{id}/update', [ProduksiController::class, 'updateJahit'])->name('produksi.updateJahit')->middleware('permission:produksi jahit edit');
    Route::delete('/produksi/jahit/{id}/delete', [ProduksiController::class, 'postDeleteJahit'])->name('produksi.postDeleteJahit')->middleware('permission:produksi jahit delete');

    Route::get('/produksi/qc/list', [ProduksiController::class, 'getQcList'])->name('produksi.getQcList');
    Route::get('/produksi/qc/create', [ProduksiController::class, 'getQcCreate'])->name('produksi.getQcCreate');
    Route::post('/produksi/qc/store', [ProduksiController::class, 'createQc'])->name('produksi.createQc');
    Route::get('/produksi/qc/{id}/edit', [ProduksiController::class, 'getQcEdit'])->name('produksi.getQcEdit');
    Route::patch('/produksi/qc/{id}/update', [ProduksiController::class, 'updateQc'])->name('produksi.updateQc');
    Route::delete('/produksi/qc/{id}/delete', [ProduksiController::class, 'postDeleteQc'])->name('produksi.postDeleteQc');

    Route::post('/produksi/{id}/{type}/restore', [ProduksiController::class, 'postRestore'])->name('produksi.restore');

    Route::get('/setoran', [SetoranController::class, 'index'])->name('setoran.index')->middleware('permission:setoran list');
    Route::get('/setoran/{id}/detail', [SetoranController::class, 'detail'])->name('setoran.detail')->middleware('permission:setoran detail');
    Route::patch('/setoran/{id}/updatekode', [SetoranController::class, 'postEditItem'])->name('setoran.postEditItem')->middleware('permission:setoran edit item');
    Route::patch('/setoran/{id}/detail/updatewc', [SetoranController::class, 'postEdit'])->name('setoran.postEdit')->middleware('permission:setoran edit');
    Route::patch('/setoran/{id}/detail/gantiJahit', [SetoranController::class, 'postGantiJahit'])->name('setoran.postGantiJahit')->middleware('permission:setoran edit jahit');
    Route::patch('/setoran/{id}/detail/gantiStatus', [SetoranController::class, 'postEditStatus'])->name('setoran.postEditStatus')->middleware('permission:setoran edit status');
    Route::patch('/setoran/{id}/detail/postGudang', [SetoranController::class, 'postGudang'])->name('setoran.postGudang')->middleware('permission:setoran ke gudang');
    Route::patch('/setoran/{id}/detail/gantiQc', [SetoranController::class, 'postGantiQc'])->name('setoran.postGantiQc')->middleware('permission:setoran edit jahit');

    Route::get('/borongan', [BoronganController::class, 'index'])->name('borongan.index')->middleware('permission:borongan list');
    Route::get('/borongan/ajax', [BoronganController::class, 'getAjaxBorongan'])->name('borongan.ajax')->middleware('permission:borongan create');
    Route::get('/borongan/create', [BoronganController::class, 'create'])->name('borongan.create')->middleware('permission:borongan create');
    Route::post('/borongan/store', [BoronganController::class, 'postAdd'])->name('borongan.postAdd')->middleware('permission:borongan create');
    Route::get('/borongan/{id}/detail', [BoronganController::class, 'detail'])->name('borongan.detail')->middleware('permission:borongan detail');

    Route::get('/setting', [SettingController::class, 'index'])->name('setting.index')->middleware('permission:setting edit');
    Route::post('/setting/update', [SettingController::class, 'update'])->name('setting.update')->middleware('permission:setting edit');

    Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])->name('report.profitLoss')->middleware('permission:report nett cash');
    Route::get('/reports/cash', [ReportController::class, 'cash'])->name('report.cash')->middleware('permission:report nett cash');
    Route::get('/reports/pembelian', [ReportController::class, 'pembelian'])->name('report.pembelian')->middleware('permission:report nett cash');
    Route::get('/reports/pendapatan', [ReportController::class, 'income'])->name('report.income');
    Route::get('/reports/pendapatan/{id}/book', [ReportController::class, 'incomeBook'])->name('report.incomeBook');
    Route::get('/reports/item-gudang', [ReportController::class, 'grubItem'])->name('report.grubItem');
    Route::get('/reports/biaya-jurnal-bank', [ReportController::class, 'laporanBiaya'])->name('report.laporanBiaya');

    Route::get('/user', [UserRoleController::class, 'userList'])->name('user.list')->middleware('permission:user list');
    Route::get('/user/create', [UserRoleController::class, 'userCreate'])->name('user.create')->middleware('permission:user create');
    Route::post('/user/store', [UserRoleController::class, 'userStore'])->name('user.store')->middleware('permission:user create');
    Route::get('/user/{id}/edit', [UserRoleController::class, 'userEdit'])->name('user.edit')->middleware('permission:user edit');
    Route::patch('/user/{id}/update', [UserRoleController::class, 'userUpdate'])->name('user.update')->middleware('permission:user edit');
    Route::post('/user/{id}/ban', [UserRoleController::class, 'ban'])->name('user.ban')->middleware('permission:user ban');

    Route::get('/user/setting', [UserRoleController::class, 'userDefault'])->name('user.userDefault');
    Route::get('/user/setting/create', [UserRoleController::class, 'userDefaultCreate'])->name('user.userDefaultCreate');
    Route::post('/user/setting/store', [UserRoleController::class, 'userDefaultStore'])->name('user.userDefaultStore');
    Route::get('/user/setting/{id}/edit', [UserRoleController::class, 'userDefaultEdit'])->name('user.userDefaultEdit');
    Route::patch('/user/setting/{id}/update', [UserRoleController::class, 'userDefaultUpdate'])->name('user.userDefaultUpdate');
    Route::delete('/user/setting/{id}/delete', [UserRoleController::class, 'userDefaultDelete'])->name('user.userDefaultDelete');


    Route::get('/role', [UserRoleController::class, 'indexRole'])->name('role.indexRole')->middleware('permission:user role');
    Route::get('/role/create', [UserRoleController::class, 'createRole'])->name('role.createRole')->middleware('permission:user create role');
    Route::post('/role/store', [UserRoleController::class, 'storeRole'])->name('role.storeRole')->middleware('permission:user create role');
    Route::get('/role/{id}/edit', [UserRoleController::class, 'editRole'])->name('role.editRole')->middleware('permission:user edit role');
    Route::post('/role/{id}/update', [UserRoleController::class, 'roleUpdate'])->name('role.roleUpdate')->middleware('permission:user edit role');
    Route::delete('/role/{id}/delete', [UserRoleController::class, 'deleteRole'])->name('role.deleteRole')->middleware('permission:user edit role');

    Route::get('/hash/{id}/transaction', [HashController::class, 'getTransactions'])->name('hash.getTransactions');

    Route::get('/karyawan', [KaryawanController::class, 'index'])->name('karyawan.index')->middleware('permission:karyawan list');
    Route::get('/karyawan/create', [KaryawanController::class, 'create'])->name('karyawan.create')->middleware('permission:karyawan create');
    Route::post('/karyawan/store', [KaryawanController::class, 'store'])->name('karyawan.store')->middleware('permission:karyawan create');
    Route::get('/karyawan/{id}/edit', [KaryawanController::class, 'edit'])->name('karyawan.edit')->middleware('permission:karyawan edit');
    Route::patch('/karyawan/{id}/update', [KaryawanController::class, 'update'])->name('karyawan.update')->middleware('permission:karyawan edit');
    Route::get('/karyawan/{id}/detail', [KaryawanController::class, 'detail'])->name('karyawan.detail')->middleware('permission:karyawan detail');
    Route::delete('/karyawan/{id}/delete', [KaryawanController::class, 'delete'])->name('karyawan.delete')->middleware('permission:karyawan delete');

    Route::get('/karyawan/{id}/cuti/create', [CutiController::class, 'create'])->name('cuti.create')->middleware('permission:cuti create');
    Route::post('/karyawan/{id}/cuti/store', [CutiController::class, 'store'])->name('cuti.store')->middleware('permission:cuti create');
    Route::get('/karyawan/{id}/cuti/list', [CutiController::class, 'cutiList'])->name('cuti.cutiList')->middleware('permission:cuti list');

    Route::get('/karyawan/{id}/gaji/create', [GajihController::class, 'create'])->name('gajih.create')->middleware('permission:gajih create');
    Route::post('/karyawan/{id}/gaji/store', [GajihController::class, 'store'])->name('gajih.store')->middleware('permission:gajih create');
    Route::get('/karyawan/{id}/gaji/list', [GajihController::class, 'list'])->name('gajih.list')->middleware('permission:gajih list');

    Route::get('/gaji', [GajihController::class, 'index'])->name('gaji.index')->middleware('permission:gajih list');
    Route::delete('/gaji/{id}/delete', [GajihController::class, 'delete'])->name('gaji.delete')->middleware('permission:gajih list');



    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
