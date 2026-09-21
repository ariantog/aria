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

it('stores buy transaction payable in total when no tax or header discount', function () {
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
        'ppn' => 0,
    ]);
});

it('stores sell payable as a signed total', function () {
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
        'total' => -20_000,
        'ppn' => 0,
    ]);
});

it('stores return payable in total when no tax or header discount', function () {
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
        'ppn' => 0,
    ]);
});

it('stores return-supplier payable as a signed total', function () {
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
        'total' => -25_000,
        'ppn' => 0,
    ]);
});

it('includes supplier PPN in buy total', function () {
    $user = User::factory()->create();
    $supplier = Addrbook::factory()->supplier()->create(['ppn' => true, 'ppn_included' => false]);
    $warehouse = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create(['price' => 10_000, 'cost' => 5_000]);

    postItemTransaction($user, [
        'date' => now()->toDateString(),
        'type' => 'buy',
        'sender_id' => $supplier->id,
        'receiver_id' => $warehouse->id,
        'ppn_included' => false,
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 10,
            'price' => 5_000,
            'discount' => 0,
        ]],
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_BUY,
        'total' => 55_500,
        'ppn' => 5_500,
    ]);
});

it('stores sell total after header discount', function () {
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
        'total' => -18_000,
        'discount' => 10,
        'ppn' => 0,
    ]);
});

it('stores sell total after header discount and adjustment', function () {
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
        'adjustment' => -1_000,
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 2,
            'price' => 10_000,
            'discount' => 0,
        ]],
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_SELL,
        'total' => -17_000,
        'discount' => 10,
        'adjustment' => -1_000,
        'ppn' => 0,
    ]);
});

it('stores prod sell 618383 net payable on total not line subtotal', function () {
    // One line Rp 135,000, invoice disc 5%, adj −250 → net Rp 128,000 (not Rp 135,000).
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create(['ppn' => false]);
    $item = Item::factory()->create(['price' => 135_000, 'cost' => 80_000]);
    seedWarehouseStock($warehouse, $item);

    postItemTransaction($user, [
        'date' => '2026-09-01',
        'type' => 'sell',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'discount_percent' => 5,
        'adjustment' => -250,
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 1,
            'price' => 135_000,
            'discount' => 0,
        ]],
    ])->assertRedirect();

    $transaction = Transaction::query()
        ->where('type', Transaction::TYPE_SELL)
        ->where('discount', 5)
        ->where('adjustment', -250)
        ->latest('id')
        ->first();

    expect($transaction)->not->toBeNull()
        ->and((float) $transaction->total)->toBe(-128_000.0)
        ->and((float) $transaction->ppn)->toBe(0.0);

    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'type' => Transaction::TYPE_SELL,
        'total' => -128_000,
        'discount' => 5,
        'adjustment' => -250,
        'ppn' => 0,
    ]);
});

it('stores a zero total when sell invoice discount is 100 percent', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create(['ppn' => false]);
    $item = Item::factory()->create(['price' => 1_591_000, 'cost' => 800_000]);
    seedWarehouseStock($warehouse, $item);

    postItemTransaction($user, [
        'date' => now()->toDateString(),
        'type' => 'sell',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'discount_percent' => 100,
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 1,
            'price' => 1_591_000,
            'discount' => 0,
        ]],
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_SELL,
        'total' => 0,
        'discount' => 100,
        'ppn' => 0,
    ]);
});

it('stores move transactions with informational line totals on the header', function () {
    $user = User::factory()->create();
    $source = Addrbook::factory()->warehouse()->create();
    $destination = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create(['price' => 10_000, 'cost' => 5_000]);
    seedWarehouseStock($source, $item);

    postItemTransaction($user, [
        'date' => now()->toDateString(),
        'type' => 'move',
        'sender_id' => $source->id,
        'receiver_id' => $destination->id,
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 4,
            'price' => 10_000,
            'discount' => 0,
        ]],
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_MOVE,
        'total' => -40_000,
        'total_items' => 4,
        'ppn' => 0,
    ]);

    $this->assertDatabaseHas('transaction_details', [
        'item_id' => $item->id,
        'price' => 10_000,
        'total' => 40_000,
    ]);
});
