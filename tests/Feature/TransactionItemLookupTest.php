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

it('finds an item by numeric id through the items id json endpoint', function () {
    $this->user->givePermissionTo('items-list');

    $item = Item::factory()->create([
        'name' => 'Warehouse Tee',
        'code' => 'WH-TEE-01',
        'price' => 120_000,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/items?id='.$item->id.'&json=1');

    $response->assertSuccessful();
    $match = collect($response->json())->first(fn ($row) => $row['id'] === $item->id);
    expect($match)->not->toBeNull()
        ->and($match['name'])->toBe('Warehouse Tee')
        ->and($match['code'])->toBe('WH-TEE-01');
});

it('resolves an item by canonical code for transaction rows', function () {
    $this->user->givePermissionTo('transactions-type-sell');

    $item = Item::factory()->create([
        'name' => 'Canonical SKU',
        'code' => 'AJD-CX90324-05-S',
        'price' => 88_000,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('transactions.item-by-code', ['type' => 'sell', 'code' => 'AJD-CX90324-05-S']))
        ->assertSuccessful()
        ->assertJsonPath('item.id', $item->id)
        ->assertJsonPath('item.code', 'AJD-CX90324-05-S')
        ->assertJsonPath('item.name', 'Canonical SKU');
});

it('resolves an item by legacy_code for transaction rows', function () {
    $this->user->givePermissionTo('transactions-type-sell');

    $item = Item::factory()->create([
        'name' => 'Legacy SKU Product',
        'code' => 'NEW-SKU-01',
        'legacy_code' => 'LEGACY-SKU-01',
        'price' => 99_000,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('transactions.item-by-code', ['type' => 'sell', 'code' => 'LEGACY-SKU-01']))
        ->assertSuccessful()
        ->assertJsonPath('item.id', $item->id)
        ->assertJsonPath('item.code', 'NEW-SKU-01')
        ->assertJsonPath('item.name', 'Legacy SKU Product');
});

it('returns null when sku does not match code or legacy_code', function () {
    $this->user->givePermissionTo('transactions-type-sell');

    Item::factory()->create([
        'code' => 'NEW-SKU-01',
        'legacy_code' => 'LEGACY-SKU-01',
    ]);

    $this->actingAs($this->user)
        ->getJson(route('transactions.item-by-code', ['type' => 'sell', 'code' => 'UNKNOWN-SKU']))
        ->assertSuccessful()
        ->assertJsonPath('item', null);
});

it('resolves an item by exact name when code and legacy_code do not match', function () {
    $this->user->givePermissionTo('transactions-type-sell');

    $item = Item::factory()->create([
        'name' => 'AJD CX00084/01 L',
        'code' => 'AJD-CX00084-01-L',
        'price' => 150_000,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('transactions.item-by-code', ['type' => 'sell', 'code' => 'AJD CX00084/01 L']))
        ->assertSuccessful()
        ->assertJsonPath('item.id', $item->id)
        ->assertJsonPath('item.code', 'AJD-CX00084-01-L')
        ->assertJsonPath('item.name', 'AJD CX00084/01 L');
});

it('resolves an item by name case-insensitively', function () {
    $this->user->givePermissionTo('transactions-type-sell');

    $item = Item::factory()->create([
        'name' => 'Essential Shirt - Red - L',
        'code' => 'AJD-CX00084-01-L',
    ]);

    $this->actingAs($this->user)
        ->getJson(route('transactions.item-by-code', ['type' => 'sell', 'code' => 'essential shirt - red - l']))
        ->assertSuccessful()
        ->assertJsonPath('item.id', $item->id);
});

it('returns null when multiple items share the same name', function () {
    $this->user->givePermissionTo('transactions-type-sell');

    Item::factory()->create(['name' => 'Duplicate Name', 'code' => 'DUP-01']);
    Item::factory()->create(['name' => 'Duplicate Name', 'code' => 'DUP-02']);

    $this->actingAs($this->user)
        ->getJson(route('transactions.item-by-code', ['type' => 'sell', 'code' => 'Duplicate Name']))
        ->assertSuccessful()
        ->assertJsonPath('item', null);
});

it('prefers code match over name when both could match', function () {
    $this->user->givePermissionTo('transactions-type-sell');

    $byCode = Item::factory()->create([
        'name' => 'Other Product',
        'code' => 'TARGET-CODE',
    ]);
    Item::factory()->create([
        'name' => 'TARGET-CODE',
        'code' => 'OTHER-CODE',
    ]);

    $this->actingAs($this->user)
        ->getJson(route('transactions.item-by-code', ['type' => 'sell', 'code' => 'TARGET-CODE']))
        ->assertSuccessful()
        ->assertJsonPath('item.id', $byCode->id)
        ->assertJsonPath('item.name', 'Other Product');
});

it('prefers numeric barcode id lookup before legacy sku when both could match', function () {
    $this->user->givePermissionTo('transactions-type-sell');

    $byId = Item::factory()->create([
        'name' => 'Barcode Item',
        'code' => 'SKU-BY-ID',
    ]);
    Item::factory()->create([
        'name' => 'Legacy Numeric',
        'code' => 'OTHER',
        'legacy_code' => (string) $byId->id,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('transactions.item-by-id', ['type' => 'sell', 'id' => $byId->id]))
        ->assertSuccessful()
        ->assertJsonPath('item.id', $byId->id)
        ->assertJsonPath('item.name', 'Barcode Item');
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

it('returns no item autocomplete results until the search term is longer than two characters', function () {
    $this->user->givePermissionTo('items-list');

    Item::factory()->create(['name' => 'Zeta Tee', 'code' => 'ZEE-01']);

    $this->actingAs($this->user)->getJson('/items?json=1')->assertSuccessful()->assertExactJson([]);
    $this->actingAs($this->user)->getJson('/items?search=Ze&json=1')->assertSuccessful()->assertExactJson([]);
    $this->actingAs($this->user)->getJson('/items?search=Zet&json=1')->assertSuccessful()->assertJsonCount(1);
});

it('caps item autocomplete results at eight rows', function () {
    $this->user->givePermissionTo('items-list');

    foreach (range(1, 10) as $i) {
        Item::factory()->create(['name' => "Lookup Item {$i}", 'code' => "LK-{$i}"]);
    }

    expect($this->actingAs($this->user)
        ->getJson('/items?search=Lookup&json=1')
        ->assertSuccessful()
        ->json())->toHaveCount(8);
});
