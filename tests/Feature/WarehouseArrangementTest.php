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

    $result = app(WarehouseArrangementService::class)->buildSuggestions($destination->id, 365);

    expect($result['families'])->not->toBeEmpty();
    expect($result['suggestions'])->toHaveCount(1);
    expect($result['suggestions'][0]['item_id'])->toBe($missingItem->id);
    expect($result['suggestions'][0]['from_warehouse_id'])->toBe($source->id);
    expect($result['suggestions'][0]['to_warehouse_id'])->toBe($destination->id);
    expect($result['suggestions'][0]['from_warehouse_name'])->toBe('Source WH');
});

it('resolves source warehouse names including virtual warehouses', function () {
    $source = Addrbook::factory()->create([
        'name' => 'VWH Source',
        'type' => Addrbook::TYPE_V_WAREHOUSE,
    ]);
    $destination = Addrbook::factory()->warehouse()->create([
        'name' => 'Flagship WH',
        'arrangement_enabled' => true,
    ]);

    $group = ItemGroup::factory()->create(['master' => 'CX90029', 'variant' => '02']);
    $item = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90029-02-S']);
    $soldItem = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'code' => 'AJD-CX90029-02-M']);

    WarehouseItem::create(['warehouse_id' => $source->id, 'item_id' => $item->id, 'quantity' => 2]);
    WarehouseItem::create(['warehouse_id' => $destination->id, 'item_id' => $soldItem->id, 'quantity' => 1]);

    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $soldItem->id,
        'month' => now()->month,
        'year' => now()->year,
        'sold_qty' => 5,
        'returned_qty' => 0,
    ]);

    $result = app(WarehouseArrangementService::class)->buildSuggestions($destination->id, 365);

    expect($result['suggestions'])->toHaveCount(1);
    expect($result['suggestions'][0]['from_warehouse_name'])->toBe('VWH Source');
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
        ->assertSee('Warehouse Arrangement', false);
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
