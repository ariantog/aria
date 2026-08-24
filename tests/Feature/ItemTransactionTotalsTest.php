<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;

function postItemTransaction(User $user, array $payload)
{
    return test()->actingAs($user)->post(route('transactions.store'), $payload);
}

function seedWarehouseStock(Addrbook $warehouse, Item $item, float $quantity = 100): void
{
    \App\Models\WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => $warehouse->type,
        'quantity' => $quantity,
    ]);
}

it('stores buy transaction subtotal in total and matches real_total when no tax or header discount', function () {
    $user = User::factory()->create();
    $supplier = Addrbook::factory()->supplier()->create(['ppn' => false]);
    $warehouse = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create(['price' => 10_000, 'cost' => 5_000]);

    postItemTransaction($user, [
        'date' => now()->toDateString(),
        'type' => 'buy',
        'sender_id' => $supplier->id,
        'receiver_id' => $warehouse->id,
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 10,
            'price' => 5_000,
            'discount' => 0,
        ]],
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_BUY,
        'total' => 50_000,
        'real_total' => 50_000,
        'ppn' => 0,
    ]);
});

it('stores sell subtotal in total and signed grand total in real_total', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create(['ppn' => false]);
    $item = Item::factory()->create(['price' => 10_000, 'cost' => 5_000]);
    seedWarehouseStock($warehouse, $item);

    postItemTransaction($user, [
        'date' => now()->toDateString(),
        'type' => 'sell',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 2,
            'price' => 10_000,
            'discount' => 0,
        ]],
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_SELL,
        'total' => 20_000,
        'real_total' => -20_000,
        'ppn' => 0,
    ]);
});

it('stores return subtotal in total and matches real_total when no tax or header discount', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['price' => 10_000, 'cost' => 5_000]);

    postItemTransaction($user, [
        'date' => now()->toDateString(),
        'type' => 'return',
        'sender_id' => $customer->id,
        'receiver_id' => $warehouse->id,
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 3,
            'price' => 10_000,
            'discount' => 0,
        ]],
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_RETURN,
        'total' => 30_000,
        'real_total' => 30_000,
        'ppn' => 0,
    ]);
});

it('stores return-supplier subtotal in total and signed grand total in real_total', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $supplier = Addrbook::factory()->supplier()->create(['ppn' => false]);
    $item = Item::factory()->create(['price' => 10_000, 'cost' => 5_000]);
    seedWarehouseStock($warehouse, $item);

    postItemTransaction($user, [
        'date' => now()->toDateString(),
        'type' => 'return-supplier',
        'sender_id' => $warehouse->id,
        'receiver_id' => $supplier->id,
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 5,
            'price' => 5_000,
            'discount' => 0,
        ]],
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_RETURN_SUPPLIER,
        'total' => 25_000,
        'real_total' => -25_000,
        'ppn' => 0,
    ]);
});

it('keeps total as line subtotal while real_total includes supplier PPN on buy', function () {
    $user = User::factory()->create();
    $supplier = Addrbook::factory()->supplier()->create(['ppn' => true]);
    $warehouse = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create(['price' => 10_000, 'cost' => 5_000]);

    postItemTransaction($user, [
        'date' => now()->toDateString(),
        'type' => 'buy',
        'sender_id' => $supplier->id,
        'receiver_id' => $warehouse->id,
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 10,
            'price' => 5_000,
            'discount' => 0,
        ]],
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_BUY,
        'total' => 50_000,
        'real_total' => 55_500,
        'ppn' => 5_500,
    ]);
});

it('keeps total as line subtotal while real_total reflects header discount on sell', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create(['ppn' => false]);
    $item = Item::factory()->create(['price' => 10_000, 'cost' => 5_000]);
    seedWarehouseStock($warehouse, $item);

    postItemTransaction($user, [
        'date' => now()->toDateString(),
        'type' => 'sell',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'discount_percent' => 10,
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 2,
            'price' => 10_000,
            'discount' => 0,
        ]],
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_SELL,
        'total' => 20_000,
        'real_total' => -18_000,
        'discount' => 10,
        'ppn' => 0,
    ]);
});
