<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;

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
        ->assertSee('Gudang Aria Utama');
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
    expect($order->warehouse_id)->toBe($warehouse->id)
        ->and($order->stockErrorItemsList())->toHaveCount(1)
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
