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
