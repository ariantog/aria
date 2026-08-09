<?php

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\User;
use App\Models\WarehouseItem;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('links warehouse items to item or asset lancar show pages', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang A']);

    $regularItem = Item::factory()->create(['name' => 'Regular SKU', 'code' => 'REG-001']);
    $assetItem = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'name' => 'Asset SKU',
        'code' => 'AST-001',
    ]);

    foreach ([$regularItem, $assetItem] as $item) {
        WarehouseItem::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
            'quantity' => 5,
        ]);
    }

    $response = $this->actingAs($this->user)
        ->get(route('addrbook.type.items', ['type' => 'warehouse', 'addrbook' => $warehouse->id]));

    $response->assertOk()
        ->assertSee(route('items.show', $regularItem), false)
        ->assertSee(route('items.edit', $regularItem), false)
        ->assertSee(route('assetlancar.show', $assetItem), false)
        ->assertSee(route('assetlancar.edit', $assetItem), false)
        ->assertSee('REG-001', false)
        ->assertSee('AST-001', false);
});
