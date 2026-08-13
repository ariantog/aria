<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\User;
use App\Models\WarehouseItem;
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

it('resolves an asset lancar item by id for transaction rows', function () {
    $this->user->givePermissionTo('transactions-type-sell');

    $asset = Item::factory()->create([
        'name' => 'Meja Kantor',
        'code' => 'ASET-MEJA-01',
        'type' => Item::TYPE_ASSET_LANCAR,
        'price' => 750_000,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('transactions.item-by-id', ['type' => 'sell', 'id' => $asset->id]))
        ->assertSuccessful()
        ->assertJsonPath('item.id', $asset->id)
        ->assertJsonPath('item.name', 'Meja Kantor')
        ->assertJsonPath('item.type', Item::TYPE_ASSET_LANCAR);
});

it('exposes warehouse stock so scanned rows can show on-hand quantity', function () {
    $this->user->givePermissionTo('transactions-type-sell');

    $item = Item::factory()->create(['name' => 'Stocked Item']);
    $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
    WarehouseItem::create([
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => Addrbook::class,
        'quantity' => 7,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('transactions.item-by-id', ['type' => 'sell', 'id' => $item->id]))
        ->assertSuccessful()
        ->assertJsonPath('item.warehouse_item.0.warehouse_id', (string) $warehouse->id);

    expect((float) $response->json('item.warehouse_item.0.quantity'))->toBe(7.0);
});

it('finds an item by numeric id through the items search json endpoint', function () {
    $this->user->givePermissionTo('items-list');

    $item = Item::factory()->create([
        'name' => 'Warehouse Tee',
        'code' => 'WH-TEE-01',
        'price' => 120_000,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/items?search='.$item->id.'&json=1');

    $response->assertSuccessful();
    $match = collect($response->json())->first(fn ($row) => $row['id'] === $item->id);
    expect($match)->not->toBeNull()
        ->and($match['name'])->toBe('Warehouse Tee')
        ->and($match['code'])->toBe('WH-TEE-01');
});

it('finds items when spaces separate name tokens', function () {
    $this->user->givePermissionTo('items-list');

    Item::factory()->create(['name' => 'Soft Edition Elbow', 'code' => 'ELB-01']);
    Item::factory()->create(['name' => 'Soft Edition Knee', 'code' => 'KNE-01']);
    Item::factory()->create(['name' => 'Hard Edition Elbow', 'code' => 'HEL-01']);

    $names = collect($this->actingAs($this->user)
        ->getJson('/items?search='.urlencode('Soft Elbow').'&json=1')
        ->assertSuccessful()
        ->json())->pluck('name');

    expect($names)->toContain('Soft Edition Elbow')
        ->not->toContain('Soft Edition Knee')
        ->not->toContain('Hard Edition Elbow');
});
