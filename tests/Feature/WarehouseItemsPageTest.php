<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\PermissionGenerator;

beforeEach(function () {
    app(PermissionGenerator::class)->generateForModule('Addrbook');
});

it('requires warehouse-items permission for the stock list page', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('addrbook-warehouse-list');

    $warehouse = Addrbook::factory()->warehouse()->create();

    $this->actingAs($user)
        ->get(route('addrbook.type.items', ['warehouse', $warehouse->id]))
        ->assertForbidden();
});

it('links warehouse items to item or asset lancar show pages', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('addrbook-warehouse-items');

    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang A']);

    $regularItem = Item::factory()->create(['name' => 'Regular SKU', 'code' => 'REG-001']);
    $assetItem = Item::factory()->create([
        'type' => \App\Enums\ItemType::ASSET_LANCAR,
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

    $response = $this->actingAs($user)
        ->get(route('addrbook.type.items', ['type' => 'warehouse', 'addrbook' => $warehouse->id]));

    $response->assertOk()
        ->assertSee(route('items.show', $regularItem), false)
        ->assertSee(route('items.edit', $regularItem), false)
        ->assertSee(route('assetlancar.show', $assetItem), false)
        ->assertSee(route('assetlancar.edit', $assetItem), false)
        ->assertSee('REG-001', false)
        ->assertSee('AST-001', false)
        ->assertSee('Export Excel', false);
});

it('hides stock below one unless show empty is checked', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('addrbook-warehouse-items');

    $warehouse = Addrbook::factory()->warehouse()->create();
    $visible = Item::factory()->create(['code' => 'STOCK-VISIBLE']);
    $hidden = Item::factory()->create(['code' => 'STOCK-HIDDEN']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $visible->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 1,
    ]);
    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $hidden->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 0.5,
    ]);

    $this->actingAs($user)
        ->get(route('addrbook.type.items', ['warehouse', $warehouse->id]))
        ->assertOk()
        ->assertSee('STOCK-VISIBLE', false)
        ->assertDontSee('STOCK-HIDDEN', false);
});

it('exports warehouse stock to excel', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('addrbook-warehouse-items');

    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Export WH']);
    $item = Item::factory()->create(['code' => 'EXPORT-SKU']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 4,
    ]);

    $response = $this->actingAs($user)->get(route('addrbook.type.items.export', [
        'warehouse',
        $warehouse->id,
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('exposes warehouse items for virtual warehouses', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('addrbook-v-warehouse-items');

    $vWarehouse = Addrbook::factory()->create([
        'type' => Addrbook::TYPE_V_WAREHOUSE,
        'name' => 'Virtual WH',
    ]);

    $this->actingAs($user)
        ->get(route('addrbook.type.items', ['vwarehouse', $vWarehouse->id]))
        ->assertOk()
        ->assertSee('Warehouse Stock', false);
});
