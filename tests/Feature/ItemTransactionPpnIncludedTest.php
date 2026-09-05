<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Services\UserPreferenceService;

function postPpnItemTransaction(User $user, array $payload)
{
    return test()->actingAs($user)->post(route('transactions.store'), $payload);
}

function seedPpnWarehouseStock(Addrbook $warehouse, Item $item, float $quantity = 100): void
{
    \App\Models\WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => $warehouse->type,
        'quantity' => $quantity,
    ]);
}

it('stores buy total with included ppn without adding tax on top', function () {
    $user = User::factory()->create();
    $supplier = Addrbook::factory()->supplier()->create(['ppn' => true]);
    $warehouse = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create(['price' => 10_000, 'cost' => 5_000]);

    postPpnItemTransaction($user, [
        'date' => now()->toDateString(),
        'type' => 'buy',
        'sender_id' => $supplier->id,
        'receiver_id' => $warehouse->id,
        'ppn_included' => true,
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 10,
            'price' => 5_550,
            'discount' => 0,
        ]],
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_BUY,
        'total' => 55_500,
        'ppn' => 5_500,
    ]);
});

it('stores sell total with included ppn as signed gross payable', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create(['ppn' => true]);
    $item = Item::factory()->create(['price' => 11_100, 'cost' => 5_000]);
    seedPpnWarehouseStock($warehouse, $item);

    postPpnItemTransaction($user, [
        'date' => now()->toDateString(),
        'type' => 'sell',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'ppn_included' => true,
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 2,
            'price' => 11_100,
            'discount' => 0,
        ]],
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_SELL,
        'total' => -22_200,
        'ppn' => 2_200,
    ]);
});

it('keeps excluded ppn behavior when ppn_included is false', function () {
    $user = User::factory()->create();
    $supplier = Addrbook::factory()->supplier()->create(['ppn' => true]);
    $warehouse = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create(['price' => 10_000, 'cost' => 5_000]);

    postPpnItemTransaction($user, [
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

it('applies included ppn on return from taxable customer', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create(['ppn' => true]);
    $item = Item::factory()->create(['price' => 11_100, 'cost' => 5_000]);

    postPpnItemTransaction($user, [
        'date' => now()->toDateString(),
        'type' => 'return',
        'sender_id' => $customer->id,
        'receiver_id' => $warehouse->id,
        'ppn_included' => true,
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 1,
            'price' => 11_100,
            'discount' => 0,
        ]],
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_RETURN,
        'total' => 11_100,
        'ppn' => 1_100,
    ]);
});

it('transaction create form defaults ppn switch from user preference', function () {
    $user = User::factory()->create();
    app(UserPreferenceService::class)->setPpnIncludedDefault($user, false);

    $this->actingAs($user)
        ->get(route('transactions.create', ['type' => 'sell']))
        ->assertOk()
        ->assertSee('data-testid="ppn-mode-switch"', false)
        ->assertSee('const _PpnIncludedDefault = false;', false);
});

it('user can save default ppn mode in transaction defaults', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('transaction-defaults.update'), [
            'default_ppn_included' => '0',
        ])
        ->assertRedirect(route('transaction-defaults.edit'))
        ->assertSessionHas('success');

    expect(app(UserPreferenceService::class)->ppnIncludedDefaultFor($user))->toBeFalse();
});
