<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\JubelioService;
use Mockery\MockInterface;

it('defaults jubelio orders index to pending only', function () {
    $user = User::factory()->create();

    Jubelioorder::create([
        'jubelio_order_id' => 'pending-1',
        'source' => 1,
        'invoice' => 'INV-PENDING',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'success-1',
        'source' => 1,
        'invoice' => 'INV-SUCCESS',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 1,
        'status' => 2,
        'error_type' => 10,
    ]);

    $this->actingAs($user)
        ->get(route('jubelio.index'))
        ->assertSuccessful()
        ->assertSee('INV-PENDING')
        ->assertDontSee('INV-SUCCESS');
});

it('shows jubelio order summary without raw payload on list page', function () {
    $user = User::factory()->create();

    mockJubelioSalesOrder('jb-100', [
        'salesorder_no' => 'INV-SUMMARY-TEST',
        'transaction_date' => '2026-05-10T10:00:00',
        'source_name' => 'Tokopedia',
        'location_name' => 'Gudang Pusat',
        'real_total' => 150000,
        'sub_total' => 150000,
        'items' => [
            ['item_code' => 'SKU-1', 'qty' => 2, 'price' => 75000],
        ],
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'jb-100',
        'source' => 1,
        'invoice' => 'INV-SUMMARY-TEST',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('jubelio.index'))
        ->assertSuccessful()
        ->assertSee('INV-SUMMARY-TEST')
        ->assertSee('Tokopedia')
        ->assertSee('Gudang Pusat')
        ->assertSee('Cek transaksi')
        ->assertDontSee('"salesorder_no"');
});

it('loads jubelio order payload on demand', function () {
    $user = User::factory()->create();

    mockJubelioSalesOrder('jb-101', [
        'salesorder_no' => 'INV-PAYLOAD-TEST',
        'real_total' => 99000,
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'jb-101',
        'source' => 1,
        'invoice' => 'INV-PAYLOAD-TEST',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $this->actingAs($user)
        ->getJson(route('jubelio.payload', $order))
        ->assertSuccessful()
        ->assertJsonPath('payload.salesorder_no', 'INV-PAYLOAD-TEST')
        ->assertJsonPath('payload.real_total', 99000);

    $this->actingAs($user)
        ->get(route('jubelio.show', $order))
        ->assertSuccessful()
        ->assertSee('INV-PAYLOAD-TEST')
        ->assertSee('Cek duplikat di Transaksi')
        ->assertSee('Buat Transaksi Manual')
        ->assertSee('Tampilkan JSON')
        ->assertDontSee('"real_total": 99000');
});

it('links jubelio invoice to transactions search', function () {
    $order = Jubelioorder::create([
        'jubelio_order_id' => 'jb-102',
        'source' => 1,
        'invoice' => 'INV-LINK-TEST',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    expect($order->transactionsSearchUrl())
        ->toBe(route('transactions.index', ['invoice' => 'INV-LINK-TEST']));
});

it('can manually process a pending jubelio sell order', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create(['code' => 'SKU-MANUAL-1']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => $warehouse->type,
        'item_id' => $item->id,
        'quantity' => 10,
    ]);

    Jubeliosync::create([
        'jubelio_store_id' => 10,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 20,
        'jubelio_location_name' => 'Gudang',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    mockJubelioSalesOrder('manual-1', [
        'salesorder_no' => 'INV-MANUAL-1',
        'store_id' => 10,
        'location_id' => 20,
        'sub_total' => 100000,
        'real_total' => 100000,
        'transaction_date' => '2026-05-10',
        'items' => [
            ['item_code' => 'SKU-MANUAL-1', 'qty' => 1, 'price' => 100000],
        ],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'manual-1',
        'source' => 1,
        'invoice' => 'INV-MANUAL-1',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('jubelio.process', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe(2)
        ->and($order->error_type)->toBe(10)
        ->and($order->execute_by)->toBe($user->id);
});

it('shows jubelio and aria warehouse names on orders list', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Aria Utama']);

    Jubeliosync::create([
        'jubelio_store_id' => 55,
        'jubelio_store_name' => 'Tokopedia',
        'jubelio_location_id' => 66,
        'jubelio_location_name' => 'Gudang Jubelio Pusat',
        'warehouse_id' => $warehouse->id,
        'customer_id' => 0,
        'bin_id' => 0,
    ]);

    mockJubelioSalesOrder('wh-ware-1', [
        'salesorder_no' => 'INV-WAREHOUSE-TEST',
        'store_id' => 55,
        'location_id' => 66,
        'source_name' => 'Tokopedia',
        'location_name' => 'Gudang Jubelio Pusat',
        'real_total' => 100000,
        'items' => [],
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'wh-ware-1',
        'source' => 1,
        'invoice' => 'INV-WAREHOUSE-TEST',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('jubelio.index'))
        ->assertSuccessful()
        ->assertSee('Gudang Jubelio Pusat')
        ->assertSee('Gudang Aria Utama')
        ->assertSee(route('addrbook.type.show', ['type' => $warehouse->type_slug, 'addrbook' => $warehouse->id]), false);
});

it('filters jubelio orders by warehouse using jubelio store location keys', function () {
    $user = User::factory()->create();
    $warehouseA = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Backfill A']);
    $warehouseB = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Backfill B']);

    Jubeliosync::create([
        'jubelio_store_id' => 911,
        'jubelio_store_name' => 'Store A',
        'jubelio_location_id' => 912,
        'jubelio_location_name' => 'Loc A',
        'warehouse_id' => $warehouseA->id,
        'customer_id' => 0,
        'bin_id' => 0,
    ]);

    Jubeliosync::create([
        'jubelio_store_id' => 913,
        'jubelio_store_name' => 'Store B',
        'jubelio_location_id' => 914,
        'jubelio_location_name' => 'Loc B',
        'warehouse_id' => $warehouseB->id,
        'customer_id' => 0,
        'bin_id' => 0,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'wh-backfill-a',
        'source' => 1,
        'invoice' => 'INV-WH-BACKFILL-A',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'jubelio_store_id' => 911,
        'jubelio_location_id' => 912,
        'warehouse_id' => 0,
        'status' => 0,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'wh-backfill-b',
        'source' => 1,
        'invoice' => 'INV-WH-BACKFILL-B',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'jubelio_store_id' => 913,
        'jubelio_location_id' => 914,
        'warehouse_id' => 0,
        'status' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('jubelio.index', ['warehouse_id' => $warehouseA->id, 'status' => 'pending']))
        ->assertSuccessful()
        ->assertSee('INV-WH-BACKFILL-A')
        ->assertDontSee('INV-WH-BACKFILL-B');
});

it('shows return warehouse from original sell not return payload sync', function () {
    $user = User::factory()->create();
    $warehouseA = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Sell Asal']);
    $warehouseB = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Return Salah']);
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    Jubeliosync::create([
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 10,
        'jubelio_location_name' => 'Loc Sell',
        'warehouse_id' => $warehouseA->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    Jubeliosync::create([
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 99,
        'jubelio_location_name' => 'Loc Return',
        'warehouse_id' => $warehouseB->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-RET-LIST',
        'sender_id' => $warehouseA->id,
        'sender_type' => $warehouseA->type,
        'receiver_id' => $customer->id,
        'receiver_type' => $customer->type,
    ]);

    mockJubelioSalesReturn('ret-list-1', [
        'return_no' => 'RET-LIST-1',
        'salesorder_no' => 'INV-RET-LIST',
        'store_id' => 1,
        'location_id' => 99,
        'location_name' => 'Loc Return',
        'items' => [],
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'ret-list-1',
        'source' => 1,
        'invoice' => 'RET-LIST-1',
        'type' => 'RETURN',
        'order_status' => 'RETURN',
        'run_count' => 0,
        'warehouse_id' => $warehouseA->id,
        'jubelio_store_id' => 0,
        'jubelio_location_id' => 0,
        'status' => 0,
    ]);

    $warehouseUrl = route('addrbook.type.show', ['type' => $warehouseA->type_slug, 'addrbook' => $warehouseA->id]);

    $this->actingAs($user)
        ->get(route('jubelio.index', ['invoice' => 'RET-LIST-1']))
        ->assertSuccessful()
        ->assertSee('Gudang Sell Asal')
        ->assertSee($warehouseUrl, false);
});

it('can refresh jubelio order payload without changing updated_at or status', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Refresh']);
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create(['code' => 'SKU-REFRESH-ERR']);

    Jubeliosync::create([
        'jubelio_store_id' => 951,
        'jubelio_store_name' => 'Tokopedia',
        'jubelio_location_id' => 952,
        'jubelio_location_name' => 'BSD - ONLINE',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    mockJubelioSalesOrder('refresh-1', [
        'salesorder_no' => 'INV-REFRESH-1',
        'store_id' => 951,
        'location_id' => 952,
        'location_name' => 'BSD - ONLINE',
        'sub_total' => 5000,
        'real_total' => 5000,
        'items' => [
            ['item_code' => 'SKU-REFRESH-ERR', 'qty' => 2, 'price' => 2500],
        ],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'refresh-1',
        'source' => 1,
        'invoice' => 'INV-REFRESH-1',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 1,
        'error_type' => 1,
        'error' => 'Stok tidak cukup',
        'status' => 1,
    ]);

    $syncedAt = now()->subDays(2);
    \Illuminate\Support\Facades\DB::table('jubelioorders')
        ->where('id', $order->id)
        ->update(['updated_at' => $syncedAt]);
    $order->refresh();
    $status = $order->status;
    $errorType = $order->error_type;

    $this->actingAs($user)
        ->post(route('jubelio.refresh-payload', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    $order->refresh();
    expect($order->jubelio_store_id)->toBe(951)
        ->and($order->jubelio_location_id)->toBe(952)
        ->and($order->warehouse_id)->toBe($warehouse->id)
        ->and($order->status)->toBe($status)
        ->and($order->error_type)->toBe($errorType)
        ->and($order->updated_at->toDateTimeString())->toBe($syncedAt->toDateTimeString())
        ->and($order->stockErrorItemsList())->toHaveCount(1)
        ->and($order->stockErrorItemsList()[0]['code'])->toBe('SKU-REFRESH-ERR')
        ->and($order->stockErrorItemsList()[0]['item_id'])->toBe($item->id);
});

it('can refresh jubelio order payload and report warehouse mapping', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Refresh']);

    Jubeliosync::create([
        'jubelio_store_id' => 951,
        'jubelio_store_name' => 'Tokopedia',
        'jubelio_location_id' => 952,
        'jubelio_location_name' => 'BSD - ONLINE',
        'warehouse_id' => $warehouse->id,
        'customer_id' => 0,
        'bin_id' => 0,
    ]);

    mockJubelioSalesOrder('refresh-1', [
        'salesorder_no' => 'INV-REFRESH-1',
        'store_id' => 951,
        'location_id' => 952,
        'location_name' => 'BSD - ONLINE',
        'real_total' => 100000,
        'items' => [],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'refresh-1',
        'source' => 1,
        'invoice' => 'INV-REFRESH-1',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 1,
        'error_type' => 1,
        'error' => 'Stok tidak cukup',
        'status' => 1,
    ]);

    $this->actingAs($user)
        ->post(route('jubelio.refresh-payload', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    $order->refresh();
    expect($order->jubelio_store_id)->toBe(951)
        ->and($order->jubelio_location_id)->toBe(952)
        ->and($order->warehouse_id)->toBe($warehouse->id);
});

it('shows clickable customer warehouse and item links on order detail', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Detail Link']);
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER, 'name' => 'Tokopedia Channel']);
    $item = Item::factory()->create(['code' => 'SKU-LINK-DETAIL', 'name' => 'Produk Link']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => $warehouse->type,
        'item_id' => $item->id,
        'quantity' => 5,
    ]);

    Jubeliosync::create([
        'jubelio_store_id' => 77,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 88,
        'jubelio_location_name' => 'Gudang Jubelio',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    mockJubelioSalesOrder('detail-link-1', [
        'salesorder_no' => 'INV-DETAIL-LINK',
        'store_id' => 77,
        'location_id' => 88,
        'location_name' => 'Gudang Jubelio',
        'customer_name' => 'Nama di Payload',
        'sub_total' => 50000,
        'real_total' => 50000,
        'items' => [
            ['item_code' => 'SKU-LINK-DETAIL', 'qty' => 2, 'price' => 25000],
        ],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'detail-link-1',
        'source' => 1,
        'invoice' => 'INV-DETAIL-LINK',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $warehouseUrl = route('addrbook.type.show', ['type' => $warehouse->type_slug, 'addrbook' => $warehouse->id]);
    $customerUrl = route('addrbook.type.show', ['type' => $customer->type_slug, 'addrbook' => $customer->id]);
    $itemUrl = route('items.show', $item);

    $this->actingAs($user)
        ->get(route('jubelio.show', $order))
        ->assertSuccessful()
        ->assertSee($warehouseUrl, false)
        ->assertSee('Gudang Detail Link')
        ->assertSee($customerUrl, false)
        ->assertSee('Tokopedia Channel')
        ->assertSee($itemUrl, false)
        ->assertSee('SKU-LINK-DETAIL')
        ->assertSee('Produk Link')
        ->assertSee('Stok Aria');
});

it('processes jubelio return into the original sell warehouse', function () {
    $warehouseA = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Asal']);
    $warehouseB = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Lain']);
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create(['code' => 'SKU-RET-WH']);

    Jubeliosync::create([
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Store A',
        'jubelio_location_id' => 10,
        'jubelio_location_name' => 'Loc A',
        'warehouse_id' => $warehouseA->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    Jubeliosync::create([
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Store B',
        'jubelio_location_id' => 99,
        'jubelio_location_name' => 'Loc B',
        'warehouse_id' => $warehouseB->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-SELL-ORIG',
        'sender_id' => $warehouseA->id,
        'sender_type' => $warehouseA->type,
        'receiver_id' => $customer->id,
        'receiver_type' => $customer->type,
    ]);

    mockJubelioSalesReturn('ret-wh-1', [
        'return_no' => 'RET-WH-1',
        'salesorder_no' => 'INV-SELL-ORIG',
        'store_id' => 1,
        'location_id' => 99,
        'sub_total' => 10000,
        'real_total' => 10000,
        'items' => [
            ['item_code' => 'SKU-RET-WH', 'qty_in_base' => 1, 'price' => 10000],
        ],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'ret-wh-1',
        'source' => 1,
        'invoice' => 'RET-WH-1',
        'type' => 'RETURN',
        'order_status' => 'RETURN',
        'run_count' => 0,
        'status' => 0,
    ]);

    app(\App\Actions\Jubelio\ProcessJubelioOrder::class)->execute($order);

    $returnTrx = Transaction::where('type', Transaction::TYPE_RETURN)->where('invoice', 'RET-WH-1')->first();
    expect($returnTrx)->not->toBeNull()
        ->and($returnTrx->receiver_id)->toBe($warehouseA->id)
        ->and($returnTrx->sender_id)->toBe($customer->id)
        ->and((float) $returnTrx->total)->toBe(10000.0)
        ->and((float) $returnTrx->real_total)->toBe(10000.0);
});

it('rejects jubelio sell when mapped warehouse stock is insufficient', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Kosong']);
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create(['code' => 'SKU-NO-STOCK']);

    Jubeliosync::create([
        'jubelio_store_id' => 5,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 6,
        'jubelio_location_name' => 'Loc',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    mockJubelioSalesOrder('no-stock-1', [
        'salesorder_no' => 'INV-NO-STOCK',
        'store_id' => 5,
        'location_id' => 6,
        'sub_total' => 5000,
        'real_total' => 5000,
        'items' => [
            ['item_code' => 'SKU-NO-STOCK', 'qty' => 1, 'price' => 5000],
        ],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'no-stock-1',
        'source' => 1,
        'invoice' => 'INV-NO-STOCK',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $result = app(\App\Actions\Jubelio\ProcessJubelioOrder::class)->execute($order);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Gudang Kosong')
        ->and($result['message'])->toContain('SKU-NO-STOCK');

    $order->refresh();
    expect($order->stockErrorItemsList())->toHaveCount(1)
        ->and($order->stockErrorItemsList()[0]['code'])->toBe('SKU-NO-STOCK')
        ->and($order->stockErrorItemsList()[0]['item_id'])->toBe($item->id);

    expect(Transaction::where('invoice', 'INV-NO-STOCK')->exists())->toBeFalse();
});

it('filters jubelio orders index by mapped warehouse', function () {
    $user = User::factory()->create();
    $warehouseA = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Filter A']);
    $warehouseB = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Filter B']);

    Jubeliosync::create([
        'jubelio_store_id' => 901,
        'jubelio_store_name' => 'Store A',
        'jubelio_location_id' => 902,
        'jubelio_location_name' => 'Loc A',
        'warehouse_id' => $warehouseA->id,
        'customer_id' => 0,
        'bin_id' => 0,
    ]);

    Jubeliosync::create([
        'jubelio_store_id' => 903,
        'jubelio_store_name' => 'Store B',
        'jubelio_location_id' => 904,
        'jubelio_location_name' => 'Loc B',
        'warehouse_id' => $warehouseB->id,
        'customer_id' => 0,
        'bin_id' => 0,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'wh-filter-a',
        'source' => 1,
        'invoice' => 'INV-WH-FILTER-A',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'jubelio_store_id' => 901,
        'jubelio_location_id' => 902,
        'warehouse_id' => $warehouseA->id,
        'status' => 0,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'wh-filter-b',
        'source' => 1,
        'invoice' => 'INV-WH-FILTER-B',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'jubelio_store_id' => 903,
        'jubelio_location_id' => 904,
        'warehouse_id' => $warehouseB->id,
        'status' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('jubelio.index', ['warehouse_id' => $warehouseA->id, 'status' => 'pending']))
        ->assertSuccessful()
        ->assertSee('INV-WH-FILTER-A')
        ->assertDontSee('INV-WH-FILTER-B')
        ->assertSee('Gudang Filter A', false);
});

it('omits the source column and keeps sync status on the error list', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Error List']);

    Jubeliosync::create([
        'jubelio_store_id' => 801,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 802,
        'jubelio_location_name' => 'Loc',
        'warehouse_id' => $warehouse->id,
        'customer_id' => 0,
        'bin_id' => 0,
    ]);

    mockJubelioSalesOrder('compact-error-1', [
        'salesorder_no' => 'INV-COMPACT-ERROR',
        'store_id' => 801,
        'location_id' => 802,
        'source_name' => 'Tokopedia',
        'location_name' => 'Loc',
        'real_total' => 10000,
        'items' => [
            ['item_code' => 'SKU-COMPACT', 'qty' => 1, 'price' => 10000],
        ],
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'compact-error-1',
        'source' => 1,
        'invoice' => 'INV-COMPACT-ERROR',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 1,
        'error_type' => 1,
        'error' => 'Stok tidak cukup',
        'jubelio_store_id' => 801,
        'jubelio_location_id' => 802,
        'warehouse_id' => $warehouse->id,
        'status' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('jubelio.index', ['status' => 'error']))
        ->assertSuccessful()
        ->assertSee('INV-COMPACT-ERROR')
        ->assertSee('data-testid="jubelio-warehouse-filter"', false)
        ->assertSee('data-testid="jubelio-orders-sync-status"', false)
        ->assertSee('Sync Status')
        ->assertSee('Qty')
        ->assertDontSee('>Source</th>', false);
});

it('filters error jubelio orders by warehouse', function () {
    $user = User::factory()->create();
    $warehouseA = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Error A']);
    $warehouseB = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Error B']);

    Jubeliosync::create([
        'jubelio_store_id' => 821,
        'jubelio_store_name' => 'Store A',
        'jubelio_location_id' => 822,
        'jubelio_location_name' => 'Loc A',
        'warehouse_id' => $warehouseA->id,
        'customer_id' => 0,
        'bin_id' => 0,
    ]);

    Jubeliosync::create([
        'jubelio_store_id' => 823,
        'jubelio_store_name' => 'Store B',
        'jubelio_location_id' => 824,
        'jubelio_location_name' => 'Loc B',
        'warehouse_id' => $warehouseB->id,
        'customer_id' => 0,
        'bin_id' => 0,
    ]);

    test()->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchSalesOrder')->andReturnUsing(function (string $id) {
            return match ($id) {
                'err-wh-a' => [
                    'salesorder_no' => 'INV-ERR-WH-A',
                    'store_id' => 821,
                    'location_id' => 822,
                    'items' => [],
                ],
                'err-wh-b' => [
                    'salesorder_no' => 'INV-ERR-WH-B',
                    'store_id' => 823,
                    'location_id' => 824,
                    'items' => [],
                ],
                default => [],
            };
        });
    });

    Jubelioorder::create([
        'jubelio_order_id' => 'err-wh-a',
        'source' => 1,
        'invoice' => 'INV-ERR-WH-A',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 1,
        'error_type' => 1,
        'error' => 'Stok tidak cukup',
        'jubelio_store_id' => 821,
        'jubelio_location_id' => 822,
        'warehouse_id' => $warehouseA->id,
        'status' => 1,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'err-wh-b',
        'source' => 1,
        'invoice' => 'INV-ERR-WH-B',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 1,
        'error_type' => 1,
        'error' => 'Stok tidak cukup',
        'jubelio_store_id' => 823,
        'jubelio_location_id' => 824,
        'warehouse_id' => $warehouseB->id,
        'status' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('jubelio.index', ['status' => 'error', 'warehouse_id' => $warehouseA->id]))
        ->assertSuccessful()
        ->assertSee('INV-ERR-WH-A')
        ->assertDontSee('INV-ERR-WH-B')
        ->assertSee('Gudang Error A', false);
});

it('shows clickable stock error codes on jubelio orders index', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Kosong']);
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create(['code' => 'SKU-LIST-STOCK']);

    Jubeliosync::create([
        'jubelio_store_id' => 8,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 9,
        'jubelio_location_name' => 'Loc',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    mockJubelioSalesOrder('list-stock-1', [
        'salesorder_no' => 'INV-LIST-STOCK',
        'store_id' => 8,
        'location_id' => 9,
        'sub_total' => 5000,
        'real_total' => 5000,
        'items' => [
            ['item_code' => 'SKU-LIST-STOCK', 'qty' => 1, 'price' => 5000],
        ],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'list-stock-1',
        'source' => 1,
        'invoice' => 'INV-LIST-STOCK',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    app(\App\Actions\Jubelio\ProcessJubelioOrder::class)->execute($order);

    $itemUrl = route('items.show', $item);

    $this->actingAs($user)
        ->get(route('jubelio.index', ['status' => 'error']))
        ->assertSuccessful()
        ->assertSee('SKU-LIST-STOCK')
        ->assertSee($itemUrl, false);
});

it('ignores inflated jubelio sub_total when line prices already match grand total', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create(['code' => 'SKU-SHOPEE-DISC']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => $warehouse->type,
        'item_id' => $item->id,
        'quantity' => 5,
    ]);

    Jubeliosync::create([
        'jubelio_store_id' => 501,
        'jubelio_store_name' => 'Shopee',
        'jubelio_location_id' => 502,
        'jubelio_location_name' => 'WTC',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    mockJubelioSalesOrder('shopee-disc-1', [
        'salesorder_no' => 'SP-SHOPEE-DISC',
        'store_id' => 501,
        'location_id' => 502,
        'sub_total' => 122590,
        'grand_total' => 79000,
        'real_total' => 79000,
        'transaction_date' => '2026-08-14',
        'items' => [
            ['item_code' => 'SKU-SHOPEE-DISC', 'qty' => 1, 'price' => 79000],
        ],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'shopee-disc-1',
        'source' => 1,
        'invoice' => 'SP-SHOPEE-DISC',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    app(\App\Actions\Jubelio\ProcessJubelioOrder::class)->execute($order);

    $transaction = Transaction::where('invoice', 'SP-SHOPEE-DISC')->first();

    expect($transaction)->not->toBeNull()
        ->and((float) $transaction->total)->toBe(-79000.0)
        ->and((float) $transaction->adjustment)->toBe(0.0)
        ->and((float) $transaction->real_total)->toBe(-79000.0);

    $detail = $transaction->details->first();
    expect((float) $detail->price)->toBe(79000.0)
        ->and((float) $detail->total)->toBe(79000.0);
});

it('applies marketplace discount adjustment when line prices use list amounts', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create(['code' => 'SKU-LIST-PRICE']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => $warehouse->type,
        'item_id' => $item->id,
        'quantity' => 5,
    ]);

    Jubeliosync::create([
        'jubelio_store_id' => 601,
        'jubelio_store_name' => 'Shopee',
        'jubelio_location_id' => 602,
        'jubelio_location_name' => 'WTC',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    mockJubelioSalesOrder('shopee-list-1', [
        'salesorder_no' => 'SP-LIST-PRICE',
        'store_id' => 601,
        'location_id' => 602,
        'sub_total' => 122590,
        'grand_total' => 79000,
        'real_total' => 79000,
        'transaction_date' => '2026-08-14',
        'items' => [
            ['item_code' => 'SKU-LIST-PRICE', 'qty' => 1, 'price' => 122590],
        ],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'shopee-list-1',
        'source' => 1,
        'invoice' => 'SP-LIST-PRICE',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    app(\App\Actions\Jubelio\ProcessJubelioOrder::class)->execute($order);

    $transaction = Transaction::where('invoice', 'SP-LIST-PRICE')->first();

    expect($transaction)->not->toBeNull()
        ->and((float) $transaction->real_total)->toBe(-122590.0)
        ->and((float) $transaction->adjustment)->toBe(-79000.0)
        ->and((float) $transaction->total)->toBe(-43590.0);
});

it('books seller income for marketplace orders with fee breakdown', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create(['code' => 'SKU-PAD-M']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => $warehouse->type,
        'item_id' => $item->id,
        'quantity' => 5,
    ]);

    Jubeliosync::create([
        'jubelio_store_id' => 701,
        'jubelio_store_name' => 'Shopee',
        'jubelio_location_id' => 702,
        'jubelio_location_name' => 'WTC',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    mockJubelioSalesOrder('shopee-income-1', [
        'salesorder_no' => 'SP-SELLER-INCOME',
        'store_id' => 701,
        'location_id' => 702,
        'sub_total' => 64000,
        'grand_total' => 64000,
        'escrow_amount' => '42935.0000',
        'transaction_date' => '2026-08-14',
        'items' => [
            ['item_code' => 'SKU-PAD-M', 'qty' => 1, 'price' => 64000],
        ],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'shopee-income-1',
        'source' => 1,
        'invoice' => 'SP-SELLER-INCOME',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    app(\App\Actions\Jubelio\ProcessJubelioOrder::class)->execute($order);

    $transaction = Transaction::where('invoice', 'SP-SELLER-INCOME')->first();

    expect($transaction)->not->toBeNull()
        ->and((float) $transaction->real_total)->toBe(-64000.0)
        ->and((float) $transaction->adjustment)->toBe(-21065.0)
        ->and((float) $transaction->total)->toBe(-42935.0);
});

it('can mark duplicate jubelio order as solved', function () {
    $user = User::factory()->create();

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'dup-1',
        'source' => 1,
        'invoice' => 'INV-DUP-1',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 1,
        'error' => 'Transaction sudah ada',
        'error_type' => 2,
        'status' => 2,
    ]);

    $this->actingAs($user)
        ->post(route('jubelio.solve', $order))
        ->assertRedirect(route('jubelio.index'))
        ->assertSessionHas('success');

    $order->refresh();
    expect($order->error_type)->toBe(10)
        ->and($order->error)->toBeNull()
        ->and($order->execute_by)->toBe($user->id);
});
