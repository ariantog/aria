<?php

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Models\WarehouseItemMonthlyStat;
use App\Services\WarehouseArrangementService;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('builds move suggestions for missing skus at arrangement destinations', function () {
    $source = Addrbook::factory()->warehouse()->create(['name' => 'Source WH']);
    $destination = Addrbook::factory()->warehouse()->create([
        'name' => 'Flagship WH',
        'arrangement_enabled' => true,
    ]);

    $group = ItemGroup::factory()->create([
        'name' => 'SLASH RUNNING SHIRT',
        'master' => 'CX90028',
        'variant' => '02',
    ]);

    $itemWithStock = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90028-02',
        'code' => 'AJD-CX90028-02-S',
    ]);

    $missingItem = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90028-02',
        'code' => 'AJD-CX90028-02-M',
    ]);

    WarehouseItem::create([
        'warehouse_id' => $source->id,
        'item_id' => $missingItem->id,
        'quantity' => 5,
    ]);

    WarehouseItem::create([
        'warehouse_id' => $destination->id,
        'item_id' => $itemWithStock->id,
        'quantity' => 2,
    ]);

    $now = now();
    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $itemWithStock->id,
        'month' => $now->month,
        'year' => $now->year,
        'sold_qty' => 10,
        'returned_qty' => 0,
    ]);

    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $missingItem->id,
        'month' => $now->month,
        'year' => $now->year,
        'sold_qty' => 3,
        'returned_qty' => 0,
    ]);

    $result = app(WarehouseArrangementService::class)->buildSuggestions($destination->id, 365);

    expect($result['families'])->not->toBeEmpty();
    expect($result['suggestions'])->toHaveCount(1);
    expect($result['suggestions'][0]['item_id'])->toBe($missingItem->id);
    expect($result['suggestions'][0]['sources'][0]['from_warehouse_id'])->toBe($source->id);
    expect($result['suggestions'][0]['to_warehouse_id'])->toBe($destination->id);
    expect($result['suggestions'][0]['sources'][0]['from_warehouse_name'])->toBe('Source WH');
});

it('lists every physical warehouse that holds a missing sku on one row', function () {
    $sourceA = Addrbook::factory()->warehouse()->create(['name' => 'WH Alpha']);
    $sourceB = Addrbook::factory()->warehouse()->create(['name' => 'WH Beta']);
    $destination = Addrbook::factory()->warehouse()->create([
        'name' => 'Flagship WH',
        'arrangement_enabled' => true,
    ]);

    $group = ItemGroup::factory()->create(['master' => 'CX90031', 'variant' => '02']);
    $anchor = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90031-02-S']);
    $missing = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90031-02-M']);

    WarehouseItem::create(['warehouse_id' => $sourceA->id, 'item_id' => $missing->id, 'quantity' => 8]);
    WarehouseItem::create(['warehouse_id' => $sourceB->id, 'item_id' => $missing->id, 'quantity' => 3]);
    WarehouseItem::create(['warehouse_id' => $destination->id, 'item_id' => $anchor->id, 'quantity' => 1]);

    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $anchor->id,
        'month' => now()->month,
        'year' => now()->year,
        'sold_qty' => 4,
        'returned_qty' => 0,
    ]);

    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $missing->id,
        'month' => now()->month,
        'year' => now()->year,
        'sold_qty' => 2,
        'returned_qty' => 0,
    ]);

    $result = app(WarehouseArrangementService::class)->buildSuggestions($destination->id, 365);

    expect($result['suggestions'])->toHaveCount(1);
    expect($result['suggestions'][0]['sources'])->toHaveCount(2);
    expect(collect($result['suggestions'][0]['sources'])->pluck('from_warehouse_id')->all())
        ->toEqualCanonicalizing([$sourceA->id, $sourceB->id]);
    expect($result['suggestions'][0]['sources'][0]['source_stock'])
        ->toBeGreaterThan($result['suggestions'][0]['sources'][1]['source_stock']);
});

it('ignores virtual warehouses and customers as move sources', function () {
    $virtual = Addrbook::factory()->create([
        'name' => 'VWH Only',
        'type' => Addrbook::TYPE_V_WAREHOUSE,
    ]);
    $customer = Addrbook::factory()->customer()->create(['name' => 'Customer Stock']);
    $realSource = Addrbook::factory()->warehouse()->create(['name' => 'Real WH']);
    $destination = Addrbook::factory()->warehouse()->create([
        'name' => 'Flagship WH',
        'arrangement_enabled' => true,
    ]);

    $group = ItemGroup::factory()->create(['master' => 'CX90029', 'variant' => '02']);
    $item = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90029-02-S']);
    $soldItem = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90029-02-M']);

    WarehouseItem::create(['warehouse_id' => $virtual->id, 'item_id' => $item->id, 'quantity' => 99]);
    WarehouseItem::create(['warehouse_id' => $customer->id, 'item_id' => $item->id, 'quantity' => 50]);
    WarehouseItem::create(['warehouse_id' => $realSource->id, 'item_id' => $item->id, 'quantity' => 2]);
    WarehouseItem::create(['warehouse_id' => $destination->id, 'item_id' => $soldItem->id, 'quantity' => 1]);

    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $soldItem->id,
        'month' => now()->month,
        'year' => now()->year,
        'sold_qty' => 5,
        'returned_qty' => 0,
    ]);

    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $item->id,
        'month' => now()->month,
        'year' => now()->year,
        'sold_qty' => 1,
        'returned_qty' => 0,
    ]);

    $result = app(WarehouseArrangementService::class)->buildSuggestions($destination->id, 365);

    expect($result['suggestions'])->toHaveCount(1);
    expect($result['suggestions'][0]['sources'][0]['from_warehouse_id'])->toBe($realSource->id);
    expect($result['suggestions'][0]['sources'][0]['from_warehouse_name'])->toBe('Real WH');
});

it('does not suggest moves when only non-warehouse addrbooks hold stock', function () {
    $virtual = Addrbook::factory()->create([
        'name' => 'VWH Only',
        'type' => Addrbook::TYPE_V_WAREHOUSE,
    ]);
    $destination = Addrbook::factory()->warehouse()->create([
        'name' => 'Flagship WH',
        'arrangement_enabled' => true,
    ]);

    $group = ItemGroup::factory()->create(['master' => 'CX90030', 'variant' => '02']);
    $item = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90030-02-S']);
    $soldItem = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90030-02-M']);

    WarehouseItem::create(['warehouse_id' => $virtual->id, 'item_id' => $item->id, 'quantity' => 5]);
    WarehouseItem::create(['warehouse_id' => $destination->id, 'item_id' => $soldItem->id, 'quantity' => 1]);

    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $soldItem->id,
        'month' => now()->month,
        'year' => now()->year,
        'sold_qty' => 3,
        'returned_qty' => 0,
    ]);

    $result = app(WarehouseArrangementService::class)->buildSuggestions($destination->id, 365);

    expect($result['suggestions'])->toBeEmpty();
});

it('does not suggest skus with zero demand at the destination', function () {
    $source = Addrbook::factory()->warehouse()->create(['name' => 'Source WH']);
    $destination = Addrbook::factory()->warehouse()->create([
        'name' => 'Flagship WH',
        'arrangement_enabled' => true,
    ]);

    $group = ItemGroup::factory()->create(['master' => 'CX90032', 'variant' => '02']);
    $soldItem = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90032-02-S']);
    $missingNoDemand = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90032-02-M']);

    WarehouseItem::create(['warehouse_id' => $source->id, 'item_id' => $missingNoDemand->id, 'quantity' => 5]);
    WarehouseItem::create(['warehouse_id' => $destination->id, 'item_id' => $soldItem->id, 'quantity' => 1]);

    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $soldItem->id,
        'month' => now()->month,
        'year' => now()->year,
        'sold_qty' => 8,
        'returned_qty' => 0,
    ]);

    $result = app(WarehouseArrangementService::class)->buildSuggestions(
        $destination->id,
        365,
        mode: WarehouseArrangementService::MODE_HIGH_DEMAND,
    );

    expect($result['families'])->not->toBeEmpty();
    expect($result['suggestions'])->toBeEmpty();
});

it('includes zero-demand missing skus in complete family mode', function () {
    $source = Addrbook::factory()->warehouse()->create(['name' => 'Source WH']);
    $destination = Addrbook::factory()->warehouse()->create([
        'name' => 'Flagship WH',
        'arrangement_enabled' => true,
    ]);

    $group = ItemGroup::factory()->create(['master' => 'CX90032', 'variant' => '02']);
    $soldItem = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90032-02-S']);
    $missingNoDemand = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90032-02-M']);

    WarehouseItem::create(['warehouse_id' => $source->id, 'item_id' => $missingNoDemand->id, 'quantity' => 5]);
    WarehouseItem::create(['warehouse_id' => $destination->id, 'item_id' => $soldItem->id, 'quantity' => 1]);

    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $soldItem->id,
        'month' => now()->month,
        'year' => now()->year,
        'sold_qty' => 8,
        'returned_qty' => 0,
    ]);

    $result = app(WarehouseArrangementService::class)->buildSuggestions(
        $destination->id,
        365,
        mode: WarehouseArrangementService::MODE_COMPLETE_FAMILY,
    );

    expect($result['suggestions'])->toHaveCount(1);
    expect($result['suggestions'][0]['item_id'])->toBe($missingNoDemand->id);
    expect($result['suggestions'][0]['item_demand'])->toBe(0.0);
});

it('filters by strong demand threshold', function () {
    $source = Addrbook::factory()->warehouse()->create();
    $destination = Addrbook::factory()->warehouse()->create(['arrangement_enabled' => true]);

    $group = ItemGroup::factory()->create(['master' => 'CX90033', 'variant' => '02']);
    $anchor = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90033-02-S']);
    $lowDemand = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90033-02-M']);
    $highDemand = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90033-02-L']);

    WarehouseItem::create(['warehouse_id' => $source->id, 'item_id' => $lowDemand->id, 'quantity' => 4]);
    WarehouseItem::create(['warehouse_id' => $source->id, 'item_id' => $highDemand->id, 'quantity' => 6]);
    WarehouseItem::create(['warehouse_id' => $destination->id, 'item_id' => $anchor->id, 'quantity' => 1]);

    $now = now();
    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $anchor->id,
        'month' => $now->month,
        'year' => $now->year,
        'sold_qty' => 10,
        'returned_qty' => 0,
    ]);
    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $lowDemand->id,
        'month' => $now->month,
        'year' => $now->year,
        'sold_qty' => 1,
        'returned_qty' => 0,
    ]);
    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $highDemand->id,
        'month' => $now->month,
        'year' => $now->year,
        'sold_qty' => 5,
        'returned_qty' => 0,
    ]);

    $result = app(WarehouseArrangementService::class)->buildSuggestions(
        $destination->id,
        365,
        mode: WarehouseArrangementService::MODE_STRONG_DEMAND,
        minDemand: 3,
    );

    expect($result['suggestions'])->toHaveCount(1);
    expect($result['suggestions'][0]['item_id'])->toBe($highDemand->id);
});

it('drafts a multi-item move with prefilled form data', function () {
    $source = Addrbook::factory()->warehouse()->create(['name' => 'Source WH']);
    $destination = Addrbook::factory()->warehouse()->create([
        'name' => 'Flagship WH',
        'arrangement_enabled' => true,
    ]);

    $group = ItemGroup::factory()->create(['master' => 'CX90028', 'variant' => '02']);
    $itemA = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90028-02-S']);
    $itemB = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90028-02-M']);

    WarehouseItem::create(['warehouse_id' => $source->id, 'item_id' => $itemA->id, 'quantity' => 3]);
    WarehouseItem::create(['warehouse_id' => $source->id, 'item_id' => $itemB->id, 'quantity' => 4]);

    $this->actingAs($this->user)->post(route('reports.warehouse-arrangement.draft-move'), [
        'items' => [
            ['item_id' => $itemA->id, 'quantity' => 2, 'from_warehouse_id' => $source->id, 'to_warehouse_id' => $destination->id],
            ['item_id' => $itemB->id, 'quantity' => 1, 'from_warehouse_id' => $source->id, 'to_warehouse_id' => $destination->id],
        ],
    ])->assertRedirect(route('transactions.create', ['type' => 'move']));

    $page = $this->actingAs($this->user)->get(route('transactions.create', ['type' => 'move']));
    $page->assertOk()
        ->assertSee('Source WH', false)
        ->assertSee('Flagship WH', false)
        ->assertSee($itemA->code, false)
        ->assertSee($itemB->code, false);
});

it('exports arrangement suggestions as excel', function () {
    $destination = Addrbook::factory()->warehouse()->create(['arrangement_enabled' => true]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.warehouse-arrangement.export', [
            'warehouse_id' => $destination->id,
            'demand_days' => 365,
        ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml.sheet');
});

it('renders the warehouse arrangement report page', function () {
    Addrbook::factory()->warehouse()->create(['arrangement_enabled' => true]);

    $this->actingAs($this->user)
        ->get(route('reports.warehouse-arrangement'))
        ->assertOk()
        ->assertSee('Warehouse Arrangement', false)
        ->assertSee('tabulator-tables', false);
});

it('builds tabulator grid grouped by master pcode', function () {
    $source = Addrbook::factory()->warehouse()->create(['name' => 'Source WH']);
    $destination = Addrbook::factory()->warehouse()->create([
        'name' => 'Flagship WH',
        'arrangement_enabled' => true,
    ]);

    $group = ItemGroup::factory()->create(['master' => 'CX90034', 'variant' => '02', 'name' => 'Grid Shirt']);
    $anchor = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90034-02-S']);
    $missingM = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90034-02-M', 'size' => 2]);
    $missingL = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90034-02-L', 'size' => 3]);

    WarehouseItem::create(['warehouse_id' => $source->id, 'item_id' => $missingM->id, 'quantity' => 4]);
    WarehouseItem::create(['warehouse_id' => $source->id, 'item_id' => $missingL->id, 'quantity' => 2]);
    WarehouseItem::create(['warehouse_id' => $destination->id, 'item_id' => $anchor->id, 'quantity' => 1]);

    $now = now();
    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $anchor->id,
        'month' => $now->month,
        'year' => $now->year,
        'sold_qty' => 6,
        'returned_qty' => 0,
    ]);
    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $missingM->id,
        'month' => $now->month,
        'year' => $now->year,
        'sold_qty' => 2,
        'returned_qty' => 0,
    ]);
    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $missingL->id,
        'month' => $now->month,
        'year' => $now->year,
        'sold_qty' => 1,
        'returned_qty' => 0,
    ]);

    $result = app(WarehouseArrangementService::class)->buildSuggestions($destination->id, 365);
    $grid = app(\App\Services\WarehouseArrangementGridBuilder::class)->build($result['suggestions']);

    expect($grid['parents'])->toHaveCount(1);
    expect($grid['parents'][0]['pcode'])->toBe('CX90034');
    expect($grid['parents'][0]['rows'])->not->toBeEmpty();
});

it('recalculates warehouse item monthly stats from transaction details', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $group = ItemGroup::factory()->create(['master' => 'CX90028', 'variant' => '02']);
    $item = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM]);

    $date = now()->toDateString();

    \App\Models\Transaction::factory()->create([
        'type' => \App\Models\Transaction::TYPE_SELL,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $warehouse->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
        'date' => $date,
        'user_id' => $this->user->id,
    ])->details()->create([
        'item_id' => $item->id,
        'quantity' => 3,
        'price' => 10000,
        'total' => 30000,
        'date' => $date,
        'transaction_type' => \App\Models\Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $this->artisan('app:recalculate-warehouse-item-stats')->assertSuccessful();

    $stat = WarehouseItemMonthlyStat::query()
        ->where('warehouse_id', $warehouse->id)
        ->where('item_id', $item->id)
        ->first();

    expect($stat)->not->toBeNull();
    expect((float) $stat->sold_qty)->toBe(3.0);
});
