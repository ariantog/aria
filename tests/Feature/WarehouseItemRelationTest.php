<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\User;
use App\Models\WarehouseItem;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('resolves warehouse names on item show when warehouse_type is an addrbook type id', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Pusat']);
    $item = Item::factory()->create(['name' => 'Test SKU']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'quantity' => 12,
    ]);

    $this->actingAs($this->user)
        ->get(route('items.show', $item))
        ->assertOk()
        ->assertSee('Gudang Pusat', false)
        ->assertDontSee('Unknown', false);
});

it('resolves virtual warehouse names on asset lancar show', function () {
    $warehouse = Addrbook::factory()->create([
        'name' => 'V-WH Online',
        'type' => Addrbook::TYPE_V_WAREHOUSE,
    ]);
    $item = Item::factory()->create(['type' => \App\Enums\ItemType::ASSET_LANCAR]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => (string) Addrbook::TYPE_V_WAREHOUSE,
        'quantity' => 3,
    ]);

    $this->actingAs($this->user)
        ->get(route('assetlancar.show', $item))
        ->assertOk()
        ->assertSee('V-WH Online', false);
});

it('warehouse item belongsTo resolves addrbook by warehouse_id', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'WH Link Test']);
    $item = Item::factory()->create();

    $wi = WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => '2',
        'quantity' => 1,
    ]);

    expect($wi->warehouse?->name)->toBe('WH Link Test');
});

it('items index active qty excludes stock in deleted warehouses', function () {
    $activeWarehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Aktif']);
    $deletedWarehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Lama']);
    $deletedWarehouse->delete();

    $item = Item::factory()->create(['name' => 'Qty Split SKU', 'qty' => 15]);

    WarehouseItem::create([
        'warehouse_id' => $activeWarehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'quantity' => 10,
    ]);
    WarehouseItem::create([
        'warehouse_id' => $deletedWarehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'quantity' => 5,
    ]);

    $this->actingAs($this->user)
        ->get(route('items.index'))
        ->assertOk()
        ->assertSee('Qty Split SKU', false)
        ->assertSee('>10<', false)
        ->assertDontSee('>15<', false);
});

it('item show lists deleted warehouse stock in a collapsible section', function () {
    $activeWarehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Aktif']);
    $deletedWarehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Dihapus']);
    $deletedWarehouse->delete();

    $item = Item::factory()->create(['name' => 'Detail Qty SKU']);

    WarehouseItem::create([
        'warehouse_id' => $activeWarehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'quantity' => 8,
    ]);
    WarehouseItem::create([
        'warehouse_id' => $deletedWarehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'quantity' => 3,
    ]);

    $this->actingAs($this->user)
        ->get(route('items.show', $item))
        ->assertOk()
        ->assertSee('Gudang Aktif', false)
        ->assertSee('Active Stock:', false)
        ->assertSee('8 Units', false)
        ->assertSee('3 in deleted warehouses', false)
        ->assertSee('Deleted Warehouses (3 units)', false)
        ->assertSee('Gudang Dihapus', false)
        ->assertSee('Deleted warehouse', false)
        ->assertSee('showDeletedWarehouses', false);
});

it('asset lancar show lists deleted warehouse stock separately from active stock', function () {
    $activeWarehouse = Addrbook::factory()->create([
        'name' => 'V-WH Active',
        'type' => Addrbook::TYPE_V_WAREHOUSE,
    ]);
    $deletedWarehouse = Addrbook::factory()->create([
        'name' => 'V-WH Retired',
        'type' => Addrbook::TYPE_V_WAREHOUSE,
    ]);
    $deletedWarehouse->delete();

    $item = Item::factory()->create(['type' => \App\Enums\ItemType::ASSET_LANCAR]);

    WarehouseItem::create([
        'warehouse_id' => $activeWarehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => (string) Addrbook::TYPE_V_WAREHOUSE,
        'quantity' => 4,
    ]);
    WarehouseItem::create([
        'warehouse_id' => $deletedWarehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => (string) Addrbook::TYPE_V_WAREHOUSE,
        'quantity' => 2,
    ]);

    $this->actingAs($this->user)
        ->get(route('assetlancar.show', $item))
        ->assertOk()
        ->assertSee('V-WH Active', false)
        ->assertSee('4 Units', false)
        ->assertSee('Deleted Warehouses (2 units)', false)
        ->assertSee('V-WH Retired', false);
});
