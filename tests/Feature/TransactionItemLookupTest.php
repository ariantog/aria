<?php

use App\Models\Item;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'transactions-type-sell']);
    Permission::firstOrCreate(['name' => 'items-list']);

    // User id 1 bypasses all authorization (superadmin).
    User::factory()->create();
    $this->user = User::factory()->create();
});

it('allows transaction users without items-list to lookup an item by id', function () {
    $this->user->givePermissionTo('transactions-type-sell');

    $item = Item::factory()->create([
        'name' => 'Scanned Product',
        'code' => 'AJD-SCAN-01-S',
        'price' => 99_000,
        'cost' => 55_000,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('transactions.item-by-id', ['type' => 'sell', 'id' => $item->id]));

    $response->assertSuccessful()
        ->assertJsonPath('item.id', $item->id)
        ->assertJsonPath('item.code', 'AJD-SCAN-01-S')
        ->assertJsonPath('item.name', 'Scanned Product');
});

it('returns null item when barcode id does not exist', function () {
    $this->user->givePermissionTo('transactions-type-sell');

    $this->actingAs($this->user)
        ->getJson(route('transactions.item-by-id', ['type' => 'sell', 'id' => 999999]))
        ->assertSuccessful()
        ->assertJsonPath('item', null);
});

it('blocks item lookup without transaction type access', function () {
    $item = Item::factory()->create();

    $this->actingAs($this->user)
        ->getJson(route('transactions.item-by-id', ['type' => 'sell', 'id' => $item->id]))
        ->assertForbidden();
});

it('requires items-list permission for the generic items index id lookup', function () {
    $this->user->givePermissionTo('transactions-type-sell');

    $item = Item::factory()->create();

    $this->actingAs($this->user)
        ->getJson('/items?id='.$item->id.'&json=1')
        ->assertForbidden();
});
