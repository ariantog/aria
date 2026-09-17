<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang SoftDel Txn']);
    $this->customer = Addrbook::factory()->customer()->create(['name' => 'Pelanggan SoftDel Txn']);

    $this->transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-SOFTDEL-PARTY-1',
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $this->warehouse->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $this->customer->id,
        'total' => -100_000,
        'real_total' => 0,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    $this->item = Item::factory()->create();
    TransactionDetail::factory()->create([
        'transaction_id' => $this->transaction->id,
        'item_id' => $this->item->id,
        'sender_id' => $this->warehouse->id,
        'receiver_id' => $this->customer->id,
        'transaction_type' => Transaction::TYPE_SELL,
    ]);

    $this->customer->delete();
});

it('shows soft-deleted sender and receiver names on transaction show', function () {
    $this->actingAs($this->user)
        ->get(route('transactions.show', $this->transaction))
        ->assertSuccessful()
        ->assertSee('Gudang SoftDel Txn')
        ->assertSee('Pelanggan SoftDel Txn');
});

it('shows soft-deleted party names on transactions list', function () {
    $this->actingAs($this->user)
        ->get(route('transactions.index', ['invoice' => 'INV-SOFTDEL-PARTY-1']))
        ->assertSuccessful()
        ->assertSee('Gudang SoftDel Txn')
        ->assertSee('Pelanggan SoftDel Txn');
});

it('shows soft-deleted counterparty on addrbook transactions', function () {
    $this->actingAs($this->user)
        ->get(route('addrbook.type.transactions', ['type' => 'warehouse', 'addrbook' => $this->warehouse->id]))
        ->assertSuccessful()
        ->assertSee('Pelanggan SoftDel Txn');
});

it('shows soft-deleted parties on item transactions', function () {
    $this->actingAs($this->user)
        ->get(route('items.transactions', $this->item))
        ->assertSuccessful()
        ->assertSee('Gudang SoftDel Txn')
        ->assertSee('Pelanggan SoftDel Txn');
});

it('shows soft-deleted warehouse on jubelio orders index', function () {
    Jubeliosync::create([
        'jubelio_store_id' => 8801,
        'jubelio_store_name' => 'Shopee SoftDel',
        'jubelio_location_id' => 8802,
        'jubelio_location_name' => 'Pusat',
        'warehouse_id' => $this->warehouse->id,
        'customer_id' => $this->customer->id,
        'bin_id' => 0,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'softdel-order-1',
        'source' => 1,
        'invoice' => 'INV-JUB-SOFTDEL-1',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'jubelio_store_id' => 8801,
        'jubelio_location_id' => 8802,
        'warehouse_id' => $this->warehouse->id,
        'status' => 0,
    ]);

    $this->warehouse->delete();

    $this->actingAs($this->user)
        ->get(route('jubelio.index', ['invoice' => 'INV-JUB-SOFTDEL-1']))
        ->assertSuccessful()
        ->assertSee('Gudang SoftDel Txn');
});

it('shows soft-deleted parties on jubelio transaction sync list', function () {
    Jubeliosync::create([
        'jubelio_store_id' => 8901,
        'jubelio_store_name' => 'Sync List',
        'jubelio_location_id' => 8902,
        'jubelio_location_name' => 'Loc',
        'warehouse_id' => $this->warehouse->id,
        'customer_id' => $this->customer->id,
        'bin_id' => 0,
    ]);

    $this->actingAs($this->user)
        ->get(route('jubelio.transaction.sync', ['invoice' => 'INV-SOFTDEL-PARTY-1']))
        ->assertSuccessful()
        ->assertSee('Gudang SoftDel Txn')
        ->assertSee('Pelanggan SoftDel Txn');
});
