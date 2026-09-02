<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubeliosync;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\JubelioService;
use App\Services\PermissionGenerator;
use Mockery\MockInterface;

beforeEach(function () {
    app(PermissionGenerator::class)->generateForModule('Addrbook');
});

function seedWarehouseJubelioSync(Addrbook $warehouse, int $locationId = 10, string $locationName = 'Gudang Pusat'): Jubeliosync
{
    return Jubeliosync::create([
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Store 1',
        'jubelio_location_id' => $locationId,
        'jubelio_location_name' => $locationName,
        'warehouse_id' => $warehouse->id,
        'customer_id' => 0,
        'bin_id' => 0,
    ]);
}

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

    $regularItem = Item::factory()->create(['name' => 'Regular SKU', 'code' => 'REG-001', 'price' => 214500]);
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
        ->assertSee('Export Excel', false)
        ->assertSee('data-testid="copy-warehouse-items-table"', false)
        ->assertSee('data-copy-col="code"', false)
        ->assertSee('data-copy-value="214500"', false)
        ->assertSee('copyRowsTable()', false);
});

it('filters warehouse stock by group product title', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('addrbook-warehouse-items');

    $warehouse = Addrbook::factory()->warehouse()->create();
    $group = \App\Models\ItemGroup::factory()->create(['name' => 'ENERGY']);
    $match = Item::factory()->create([
        'group_id' => $group->id,
        'name' => 'hj00022',
        'code' => 'WH-ENERGY-HJ-M',
    ]);
    $other = Item::factory()->create(['name' => 'other-item', 'code' => 'WH-OTHER-M']);

    foreach ([$match, $other] as $item) {
        WarehouseItem::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
            'quantity' => 5,
        ]);
    }

    $this->actingAs($user)
        ->get(route('addrbook.type.items', ['warehouse', $warehouse->id, 'name' => 'energy']))
        ->assertOk()
        ->assertSee('WH-ENERGY-HJ-M', false)
        ->assertDontSee('WH-OTHER-M', false);
});

it('shows shared item list filters on warehouse stock page', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('addrbook-warehouse-items');

    $warehouse = Addrbook::factory()->warehouse()->create();
    $match = Item::factory()->create(['code' => 'WH-90151-M', 'legacy_code' => null]);
    $other = Item::factory()->create(['code' => 'WH-OTHER-M', 'legacy_code' => null]);

    foreach ([$match, $other] as $item) {
        WarehouseItem::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
            'quantity' => 5,
        ]);
    }

    $this->actingAs($user)
        ->get(route('addrbook.type.items', ['warehouse', $warehouse->id, 'code' => '90151']))
        ->assertOk()
        ->assertSee('data-testid="warehouse-items-filters-toggle"', false)
        ->assertSee('WH-90151-M', false)
        ->assertDontSee('WH-OTHER-M', false);
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

it('shows jubelio on-hand stock for synced warehouses', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('addrbook-warehouse-items');

    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Jub WH']);
    seedWarehouseJubelioSync($warehouse);

    $linkedItem = Item::factory()->create([
        'code' => 'JUB-LINKED',
        'jubelio_item_id' => 123,
    ]);
    $unlinkedItem = Item::factory()->create([
        'code' => 'JUB-UNLINKED',
        'jubelio_item_id' => null,
    ]);

    foreach ([$linkedItem, $unlinkedItem] as $item) {
        WarehouseItem::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
            'quantity' => 50,
        ]);
    }

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchItemsAllStocks')
            ->once()
            ->with([123])
            ->andReturn([
                'data' => [[
                    'item_id' => 123,
                    'location_stocks' => [[
                        'location_id' => 10,
                        'on_hand' => 40,
                        'on_order' => 5,
                    ]],
                ]],
            ]);
    });

    $response = $this->actingAs($user)
        ->get(route('addrbook.type.items', ['warehouse', $warehouse->id]));

    $response->assertOk()
        ->assertSee('Jubelio', false)
        ->assertSee('Gudang Pusat', false)
        ->assertSee('40', false)
        ->assertSee('Not linked', false)
        ->assertSee('item(s) on this page are not linked to Jubelio', false);
});

it('does not show jubelio column when warehouse is not mapped', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('addrbook-warehouse-items');

    $warehouse = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create(['code' => 'NO-JUB-SYNC', 'jubelio_item_id' => 999]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 3,
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('fetchItemsAllStocks');
    });

    $this->actingAs($user)
        ->get(route('addrbook.type.items', ['warehouse', $warehouse->id]))
        ->assertOk()
        ->assertDontSee('Not linked', false)
        ->assertDontSee('Jubelio location:', false);
});

it('sorts warehouse stock by code ascending by default', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('addrbook-warehouse-items');

    $warehouse = Addrbook::factory()->warehouse()->create();
    $zebra = Item::factory()->create(['code' => 'WH-ZEBRA-M']);
    $alpha = Item::factory()->create(['code' => 'WH-ALPHA-M']);

    foreach ([$zebra, $alpha] as $item) {
        WarehouseItem::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
            'quantity' => 5,
        ]);
    }

    $html = $this->actingAs($user)
        ->get(route('addrbook.type.items', ['warehouse', $warehouse->id]))
        ->assertOk()
        ->getContent();

    expect(strpos($html, 'WH-ALPHA-M'))->toBeLessThan(strpos($html, 'WH-ZEBRA-M'));
});

it('sorts warehouse stock when sort query param is provided', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('addrbook-warehouse-items');

    $warehouse = Addrbook::factory()->warehouse()->create();
    $lowQty = Item::factory()->create(['code' => 'WH-LOW-QTY']);
    $highQty = Item::factory()->create(['code' => 'WH-HIGH-QTY']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $lowQty->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 2,
    ]);
    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $highQty->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 20,
    ]);

    $html = $this->actingAs($user)
        ->get(route('addrbook.type.items', ['warehouse', $warehouse->id, 'sort' => 'qtydesc']))
        ->assertOk()
        ->getContent();

    expect(strpos($html, 'WH-HIGH-QTY'))->toBeLessThan(strpos($html, 'WH-LOW-QTY'));
});

it('renders sortable column headers on warehouse stock page', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('addrbook-warehouse-items');

    $warehouse = Addrbook::factory()->warehouse()->create();

    $this->actingAs($user)
        ->get(route('addrbook.type.items', ['warehouse', $warehouse->id]))
        ->assertOk()
        ->assertSee('sort=codedesc', false)
        ->assertSee('sort=qtyasc', false);
});
