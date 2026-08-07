<?php

use App\Models\Jubelioorder;
use App\Models\User;

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
