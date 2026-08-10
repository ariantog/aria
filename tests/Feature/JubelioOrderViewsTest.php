<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\User;

it('defaults jubelio orders index to pending only', function () {
    $user = User::factory()->create();

    Jubelioorder::create([
        'jubelio_order_id' => 'pending-1',
        'source' => 1,
        'invoice' => 'INV-PENDING',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'payload' => '{}',
        'status' => 0,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'success-1',
        'source' => 1,
        'invoice' => 'INV-SUCCESS',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 1,
        'payload' => '{}',
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

    Jubelioorder::create([
        'jubelio_order_id' => 'jb-100',
        'source' => 1,
        'invoice' => 'INV-SUMMARY-TEST',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'payload' => json_encode([
            'salesorder_no' => 'INV-SUMMARY-TEST',
            'transaction_date' => '2026-05-10T10:00:00',
            'source_name' => 'Tokopedia',
            'location_name' => 'Gudang Pusat',
            'grand_total' => 150000,
            'sub_total' => 150000,
            'items' => [
                ['item_code' => 'SKU-1', 'qty' => 2, 'price' => 75000],
            ],
        ]),
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

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'jb-101',
        'source' => 1,
        'invoice' => 'INV-PAYLOAD-TEST',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'payload' => json_encode(['salesorder_no' => 'INV-PAYLOAD-TEST', 'grand_total' => 99000]),
        'status' => 0,
    ]);

    $this->actingAs($user)
        ->getJson(route('jubelio.payload', $order))
        ->assertSuccessful()
        ->assertJsonPath('payload.salesorder_no', 'INV-PAYLOAD-TEST')
        ->assertJsonPath('payload.grand_total', 99000);

    $this->actingAs($user)
        ->get(route('jubelio.show', $order))
        ->assertSuccessful()
        ->assertSee('INV-PAYLOAD-TEST')
        ->assertSee('Cek duplikat di Transaksi')
        ->assertSee('Buat Transaksi Manual')
        ->assertSee('Tampilkan JSON')
        ->assertDontSee('"grand_total": 99000');
});

it('links jubelio invoice to transactions search', function () {
    $order = Jubelioorder::create([
        'jubelio_order_id' => 'jb-102',
        'source' => 1,
        'invoice' => 'INV-LINK-TEST',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'payload' => '{}',
        'status' => 0,
    ]);

    expect($order->transactionsSearchUrl())
        ->toBe(route('transactions.index', ['invoice_number' => 'INV-LINK-TEST']));
});

it('can manually process a pending jubelio sell order', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create(['code' => 'SKU-MANUAL-1']);

    Jubeliosync::create([
        'jubelio_store_id' => 10,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 20,
        'jubelio_location_name' => 'Gudang',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'manual-1',
        'source' => 1,
        'invoice' => 'INV-MANUAL-1',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'payload' => json_encode([
            'salesorder_no' => 'INV-MANUAL-1',
            'store_id' => 10,
            'location_id' => 20,
            'sub_total' => 100000,
            'grand_total' => 100000,
            'transaction_date' => '2026-05-10',
            'items' => [
                ['item_code' => 'SKU-MANUAL-1', 'qty' => 1, 'price' => 100000],
            ],
        ]),
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

    Jubelioorder::create([
        'jubelio_order_id' => 'wh-ware-1',
        'source' => 1,
        'invoice' => 'INV-WAREHOUSE-TEST',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'payload' => json_encode([
            'salesorder_no' => 'INV-WAREHOUSE-TEST',
            'store_id' => 55,
            'location_id' => 66,
            'source_name' => 'Tokopedia',
            'location_name' => 'Gudang Jubelio Pusat',
            'grand_total' => 100000,
            'items' => [],
        ]),
        'status' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('jubelio.index'))
        ->assertSuccessful()
        ->assertSee('Gudang Jubelio Pusat')
        ->assertSee('Gudang Aria Utama');
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
        'payload' => '{}',
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
