<?php

use App\Http\Requests\StoreItemTransactionRequest;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\User;


beforeEach(function () { $this->user = User::factory()->create(); });

test('StoreItemTransactionRequest requires all mandatory fields', function () {
    $this->actingAs($this->user)->postJson(route('transactions.store'), [])
        ->assertUnprocessable()->assertJsonValidationErrors(['date', 'type', 'sender_id', 'receiver_id', 'items']);
});

test('StoreItemTransactionRequest validates item quantity and price', function () {
    $item = Item::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $this->actingAs($this->user)->postJson(route('transactions.store'), [
        'date' => now()->toDateString(), 'type' => 'sell',
        'sender_id' => $warehouse->id, 'receiver_id' => $customer->id,
        'items' => [['item_id' => $item->id, 'quantity' => -1, 'price' => -100]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items.0.quantity', 'items.0.price']);
});

test('StoreItemTransactionRequest allows valid sell', function () {
    $item = Item::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    \App\Models\WarehouseItem::create(['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'quantity' => 100]);
    $this->actingAs($this->user)->postJson(route('transactions.store'), [
        'date' => now()->toDateString(), 'type' => 'sell',
        'sender_id' => $warehouse->id, 'receiver_id' => $customer->id,
        'items' => [['item_id' => $item->id, 'quantity' => 1, 'price' => 50000]],
    ])->assertRedirect();
});

test('StoreItemTransactionRequest allows fractional item quantities', function () {
    $item = Item::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    \App\Models\WarehouseItem::create(['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'quantity' => 100]);

    $this->actingAs($this->user)->postJson(route('transactions.store'), [
        'date' => now()->toDateString(),
        'type' => 'sell',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'items' => [['item_id' => $item->id, 'quantity' => 2.5, 'price' => 10000]],
    ])->assertRedirect();

    $this->assertDatabaseHas('transaction_details', [
        'item_id' => $item->id,
        'quantity' => 2.5,
        'price' => 10000,
        'total' => 25000,
    ]);
});
