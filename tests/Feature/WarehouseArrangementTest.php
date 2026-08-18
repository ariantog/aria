<?php

use App\Enums\AddrbookType;
use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Models\WarehouseItemMonthlyStat;
use App\Models\WarehouseArrangementCandidate;
use App\Models\WarehouseArrangementRefreshJob;
use App\Jobs\ProcessWarehouseArrangementRefreshBatch;
use App\Services\WarehouseArrangementService;
use Illuminate\Support\Facades\Queue;
use App\Services\WarehouseArrangementSyncService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function arrangementPage(int $destinationId, array $params = []): array
{
    $sourceIds = $params['source_ids'] ?? Addrbook::query()
        ->where('type', AddrbookType::Warehouse)
        ->where('id', '!=', $destinationId)
        ->pluck('id')
        ->all();

    foreach ($sourceIds as $sourceId) {
        DB::table('warehouse_arrangement_sources')->updateOrInsert(
            [
                'destination_warehouse_id' => $destinationId,
                'source_warehouse_id' => (int) $sourceId,
            ],
            ['created_at' => now(), 'updated_at' => now()],
        );
    }

    app(WarehouseArrangementSyncService::class)->syncAll($destinationId);

    return app(WarehouseArrangementService::class)->buildPage(
        $destinationId,
        $params['demand_days'] ?? 365,
        $params['mode'] ?? WarehouseArrangementService::MODE_DEMAND,
        $params['page'] ?? 1,
        $params['per_page'] ?? WarehouseArrangementService::PER_PAGE,
        $params['search'] ?? '',
        $params['exclude'] ?? [],
    );
}

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

    $result = arrangementPage($destination->id);

    expect($result['suggestions'])->toHaveCount(1);
    expect($result['suggestions'][0]['item_id'])->toBe($missingItem->id);
    expect($result['suggestions'][0]['sources'][0]['from_warehouse_id'])->toBe($source->id);
    expect($result['suggestions'][0]['to_warehouse_id'])->toBe($destination->id);
    expect($result['suggestions'][0]['sources'][0]['from_warehouse_name'])->toBe('Source WH');
});

it('lists every configured source warehouse that holds a missing sku on one row', function () {
    $sourceA = Addrbook::factory()->warehouse()->create(['name' => 'WH Alpha']);
    $sourceB = Addrbook::factory()->warehouse()->create(['name' => 'WH Beta']);
    $destination = Addrbook::factory()->warehouse()->create([
        'name' => 'Flagship WH',
        'arrangement_enabled' => true,
    ]);

    $group = ItemGroup::factory()->create(['master' => 'CX90031', 'variant' => '02']);
    $anchor = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90031-02', 'code' => 'AJD-CX90031-02-S']);
    $missing = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90031-02', 'code' => 'AJD-CX90031-02-M']);

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

    $result = arrangementPage($destination->id);

    expect($result['suggestions'])->toHaveCount(1);
    expect($result['suggestions'][0]['sources'])->toHaveCount(2);
    expect(collect($result['suggestions'][0]['sources'])->pluck('from_warehouse_id')->all())
        ->toEqualCanonicalizing([$sourceA->id, $sourceB->id]);
    expect($result['suggestions'][0]['sources'][0]['source_stock'])
        ->toBeGreaterThan($result['suggestions'][0]['sources'][1]['source_stock']);
});

it('ignores warehouses that are not configured as arrangement sources', function () {
    $unlinked = Addrbook::factory()->warehouse()->create(['name' => 'Unlinked WH']);
    $realSource = Addrbook::factory()->warehouse()->create(['name' => 'Real WH']);
    $destination = Addrbook::factory()->warehouse()->create([
        'name' => 'Flagship WH',
        'arrangement_enabled' => true,
    ]);

    $group = ItemGroup::factory()->create(['master' => 'CX90029', 'variant' => '02']);
    $item = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90029-02', 'code' => 'AJD-CX90029-02-S']);
    $soldItem = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90029-02', 'code' => 'AJD-CX90029-02-M']);

    WarehouseItem::create(['warehouse_id' => $unlinked->id, 'item_id' => $item->id, 'quantity' => 99]);
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

    $result = arrangementPage($destination->id, ['source_ids' => [$realSource->id]]);

    expect($result['suggestions'])->toHaveCount(1);
    expect($result['suggestions'][0]['sources'][0]['from_warehouse_id'])->toBe($realSource->id);
    expect($result['suggestions'][0]['sources'][0]['from_warehouse_name'])->toBe('Real WH');
});

it('does not suggest moves when configured sources hold no stock', function () {
    $virtual = Addrbook::factory()->create([
        'name' => 'VWH Only',
        'type' => Addrbook::TYPE_V_WAREHOUSE,
    ]);
    $destination = Addrbook::factory()->warehouse()->create([
        'name' => 'Flagship WH',
        'arrangement_enabled' => true,
    ]);

    $group = ItemGroup::factory()->create(['master' => 'CX90030', 'variant' => '02']);
    $item = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90030-02', 'code' => 'AJD-CX90030-02-S']);
    $soldItem = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90030-02', 'code' => 'AJD-CX90030-02-M']);

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

    $result = arrangementPage($destination->id, ['source_ids' => []]);

    expect($result['suggestions'])->toBeEmpty();
});

it('does not suggest skus with zero demand in demand mode', function () {
    $source = Addrbook::factory()->warehouse()->create(['name' => 'Source WH']);
    $destination = Addrbook::factory()->warehouse()->create([
        'name' => 'Flagship WH',
        'arrangement_enabled' => true,
    ]);

    $group = ItemGroup::factory()->create(['master' => 'CX90032', 'variant' => '02']);
    $soldItem = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90032-02', 'code' => 'AJD-CX90032-02-S']);
    $missingNoDemand = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90032-02', 'code' => 'AJD-CX90032-02-M']);

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

    $result = arrangementPage($destination->id, ['mode' => WarehouseArrangementService::MODE_DEMAND]);

    expect($result['suggestions'])->toBeEmpty();
});

it('includes zero-demand missing skus in family mode when completeness is low', function () {
    $source = Addrbook::factory()->warehouse()->create(['name' => 'Source WH']);
    $destination = Addrbook::factory()->warehouse()->create([
        'name' => 'Flagship WH',
        'arrangement_enabled' => true,
    ]);

    $group = ItemGroup::factory()->create(['master' => 'CX90032', 'variant' => '02']);
    $soldItem = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90032-02', 'code' => 'AJD-CX90032-02-S']);
    $missingNoDemand = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90032-02', 'code' => 'AJD-CX90032-02-M']);

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

    $result = arrangementPage($destination->id, ['mode' => WarehouseArrangementService::MODE_FAMILY]);

    expect($result['suggestions'])->toHaveCount(1);
    expect($result['suggestions'][0]['item_id'])->toBe($missingNoDemand->id);
    expect($result['suggestions'][0]['item_demand'])->toBe(0.0);
});

it('paginates color pcodes', function () {
    $source = Addrbook::factory()->warehouse()->create();
    $destination = Addrbook::factory()->warehouse()->create(['arrangement_enabled' => true]);

    for ($i = 1; $i <= 35; $i++) {
        $master = 'CX9'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        $pcode = $master.'-02';
        $group = ItemGroup::factory()->create(['master' => $master, 'variant' => '02']);
        $anchor = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => $pcode, 'code' => "AJD-{$pcode}-S"]);
        $missing = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => $pcode, 'code' => "AJD-{$pcode}-M"]);

        WarehouseItem::create(['warehouse_id' => $source->id, 'item_id' => $missing->id, 'quantity' => 2]);
        WarehouseItem::create(['warehouse_id' => $destination->id, 'item_id' => $anchor->id, 'quantity' => 1]);

        WarehouseItemMonthlyStat::create([
            'warehouse_id' => $destination->id,
            'item_id' => $anchor->id,
            'month' => now()->month,
            'year' => now()->year,
            'sold_qty' => 10 + $i,
            'returned_qty' => 0,
        ]);
        WarehouseItemMonthlyStat::create([
            'warehouse_id' => $destination->id,
            'item_id' => $missing->id,
            'month' => now()->month,
            'year' => now()->year,
            'sold_qty' => $i,
            'returned_qty' => 0,
        ]);
    }

    $page1 = arrangementPage($destination->id, ['per_page' => 30]);
    $page2 = arrangementPage($destination->id, ['page' => 2, 'per_page' => 30]);

    expect($page1['total_pcodes'])->toBe(35);
    expect($page1['suggestions'])->not->toBeEmpty();
    expect($page2['suggestions'])->not->toBeEmpty();
    expect(count($page1['suggestions']))->not->toBe(count($page2['suggestions']));
});

it('hides drafted item ids from the page', function () {
    $source = Addrbook::factory()->warehouse()->create();
    $destination = Addrbook::factory()->warehouse()->create(['arrangement_enabled' => true]);

    $group = ItemGroup::factory()->create(['master' => 'CX90100', 'variant' => '02']);
    $item = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90100-02', 'code' => 'AJD-CX90100-02-M']);
    $anchor = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90100-02', 'code' => 'AJD-CX90100-02-S']);

    WarehouseItem::create(['warehouse_id' => $source->id, 'item_id' => $item->id, 'quantity' => 3]);
    WarehouseItem::create(['warehouse_id' => $destination->id, 'item_id' => $anchor->id, 'quantity' => 1]);

    $now = now();
    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $anchor->id,
        'month' => $now->month,
        'year' => $now->year,
        'sold_qty' => 5,
        'returned_qty' => 0,
    ]);
    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $item->id,
        'month' => $now->month,
        'year' => $now->year,
        'sold_qty' => 2,
        'returned_qty' => 0,
    ]);

    $with = arrangementPage($destination->id);
    expect($with['suggestions'])->toHaveCount(1);

    $hidden = arrangementPage($destination->id, ['exclude' => [$item->id]]);
    expect($hidden['suggestions'])->toBeEmpty();
});

it('drafts a multi-item move with prefilled form data', function () {
    $source = Addrbook::factory()->warehouse()->create(['name' => 'Source WH']);
    $destination = Addrbook::factory()->warehouse()->create([
        'name' => 'Flagship WH',
        'arrangement_enabled' => true,
    ]);

    $group = ItemGroup::factory()->create(['master' => 'CX90028', 'variant' => '02']);
    $itemA = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90028-02', 'code' => 'AJD-CX90028-02-S']);
    $itemB = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90028-02', 'code' => 'AJD-CX90028-02-M']);

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

it('renders the warehouse arrangement report page without tabulator', function () {
    Addrbook::factory()->warehouse()->create(['arrangement_enabled' => true]);

    $this->actingAs($this->user)
        ->get(route('reports.warehouse-arrangement'))
        ->assertOk()
        ->assertSee('Warehouse Arrangement', false)
        ->assertDontSee('tabulator-tables', false)
        ->assertSee('Demand', false);
});

it('groups sections by color pcode', function () {
    $source = Addrbook::factory()->warehouse()->create(['name' => 'Source WH']);
    $destination = Addrbook::factory()->warehouse()->create([
        'name' => 'Flagship WH',
        'arrangement_enabled' => true,
    ]);

    $group = ItemGroup::factory()->create(['master' => 'CX90034', 'variant' => '02', 'name' => 'Grid Shirt']);
    $anchor = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90034-02', 'code' => 'AJD-CX90034-02-S']);
    $missingM = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90034-02', 'code' => 'AJD-CX90034-02-M']);
    $missingL = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90034-02', 'code' => 'AJD-CX90034-02-L']);

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

    $result = arrangementPage($destination->id);

    expect($result['sections'])->toHaveCount(1);
    expect($result['sections'][0]['pcode'])->toBe('CX90034-02');
    expect($result['sections'][0]['cells'])->not->toBeEmpty();
});

it('shows all pcode sizes in the section not only sizes to move', function () {
    $source = Addrbook::factory()->warehouse()->create();
    $destination = Addrbook::factory()->warehouse()->create(['arrangement_enabled' => true]);

    $group = ItemGroup::factory()->create(['master' => 'CX90035', 'variant' => '02']);
    $pcode = 'CX90035-02';

    $sizeS = \App\Models\Tag::factory()->create(['type' => \App\Models\Tag::TYPE_SIZE, 'code' => 'S']);
    $sizeM = \App\Models\Tag::factory()->create(['type' => \App\Models\Tag::TYPE_SIZE, 'code' => 'M']);
    $sizeL = \App\Models\Tag::factory()->create(['type' => \App\Models\Tag::TYPE_SIZE, 'code' => 'L']);
    $sizeXl = \App\Models\Tag::factory()->create(['type' => \App\Models\Tag::TYPE_SIZE, 'code' => 'XL']);

    $anchor = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => $pcode, 'code' => 'AJD-CX90035-02-S', 'size' => $sizeS->id]);
    $missingM = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => $pcode, 'code' => 'AJD-CX90035-02-M', 'size' => $sizeM->id]);
    Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => $pcode, 'code' => 'AJD-CX90035-02-L', 'size' => $sizeL->id]);
    $missingXl = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => $pcode, 'code' => 'AJD-CX90035-02-XL', 'size' => $sizeXl->id]);

    WarehouseItem::create(['warehouse_id' => $source->id, 'item_id' => $missingM->id, 'quantity' => 4]);
    WarehouseItem::create(['warehouse_id' => $source->id, 'item_id' => $missingXl->id, 'quantity' => 2]);
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
        'item_id' => $missingXl->id,
        'month' => $now->month,
        'year' => $now->year,
        'sold_qty' => 1,
        'returned_qty' => 0,
    ]);

    $result = arrangementPage($destination->id);
    $section = $result['sections'][0];

    expect($section['sizes'])->toEqual(['S', 'M', 'L', 'XL']);
    expect($section['cells']['S']['moveable'] ?? true)->toBeFalse();
    expect($section['cells']['M']['moveable'])->toBeTrue();
    expect($section['cells']['XL']['moveable'])->toBeTrue();
    expect(isset($section['cells']['L']))->toBeFalse();
});

it('excludes soft deleted warehouses from destination list and source sync', function () {
    $activeSource = Addrbook::factory()->warehouse()->create(['name' => 'Active Source']);
    $deletedSource = Addrbook::factory()->warehouse()->create(['name' => 'Deleted Source']);
    $deletedSource->delete();

    $activeDestination = Addrbook::factory()->warehouse()->create([
        'name' => 'Active Destination',
        'arrangement_enabled' => true,
    ]);
    $deletedDestination = Addrbook::factory()->warehouse()->create([
        'name' => 'Deleted Destination',
        'arrangement_enabled' => true,
    ]);
    $deletedDestination->delete();

    DB::table('warehouse_arrangement_sources')->insert([
        'destination_warehouse_id' => $activeDestination->id,
        'source_warehouse_id' => $activeSource->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('warehouse_arrangement_sources')->insert([
        'destination_warehouse_id' => $activeDestination->id,
        'source_warehouse_id' => $deletedSource->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $destinations = app(WarehouseArrangementService::class)->destinationWarehouses();
    expect($destinations->pluck('id')->all())->toContain($activeDestination->id);
    expect($destinations->pluck('id')->all())->not->toContain($deletedDestination->id);

    $group = ItemGroup::factory()->create(['master' => 'CX90102', 'variant' => '02']);
    $anchor = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90102-02', 'code' => 'AJD-CX90102-02-S']);
    $missing = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90102-02', 'code' => 'AJD-CX90102-02-M']);

    WarehouseItem::create(['warehouse_id' => $deletedSource->id, 'item_id' => $missing->id, 'quantity' => 9]);
    WarehouseItem::create(['warehouse_id' => $activeSource->id, 'item_id' => $missing->id, 'quantity' => 2]);
    WarehouseItem::create(['warehouse_id' => $activeDestination->id, 'item_id' => $anchor->id, 'quantity' => 1]);

    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $activeDestination->id,
        'item_id' => $anchor->id,
        'month' => now()->month,
        'year' => now()->year,
        'sold_qty' => 4,
        'returned_qty' => 0,
    ]);
    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $activeDestination->id,
        'item_id' => $missing->id,
        'month' => now()->month,
        'year' => now()->year,
        'sold_qty' => 2,
        'returned_qty' => 0,
    ]);

    app(WarehouseArrangementSyncService::class)->syncAll($activeDestination->id);

    $result = app(WarehouseArrangementService::class)->buildPage($activeDestination->id);
    expect($result['suggestions'])->toHaveCount(1);
    expect($result['suggestions'][0]['sources'])->toHaveCount(1);
    expect($result['suggestions'][0]['sources'][0]['from_warehouse_id'])->toBe($activeSource->id);
});

it('sync command rebuilds cached arrangement candidates', function () {
    $source = Addrbook::factory()->warehouse()->create();
    $destination = Addrbook::factory()->warehouse()->create(['arrangement_enabled' => true]);
    $destination->arrangementSources()->sync([$source->id]);

    $group = ItemGroup::factory()->create(['master' => 'CX90101', 'variant' => '02']);
    $anchor = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90101-02', 'code' => 'AJD-CX90101-02-S']);
    $missing = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90101-02', 'code' => 'AJD-CX90101-02-M']);

    WarehouseItem::create(['warehouse_id' => $source->id, 'item_id' => $missing->id, 'quantity' => 2]);
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

    $this->artisan('app:sync-warehouse-arrangement', ['--destination' => $destination->id])->assertSuccessful();

    $result = app(WarehouseArrangementService::class)->buildPage($destination->id);
    expect($result['suggestions'])->toHaveCount(1);
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

it('recalculates stats for items with legacy item type values', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $group = ItemGroup::factory()->create(['master' => 'CX90033', 'variant' => '02']);
    $item = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM]);
    DB::table('items')->where('id', $item->id)->update(['type' => 4]);

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
        'quantity' => 5,
        'price' => 10000,
        'total' => 50000,
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
    expect((float) $stat->sold_qty)->toBe(5.0);
    expect($stat->item_type)->toBe(ItemType::ITEM->value);
});

it('skips legacy transaction details with zero or orphaned warehouse ids when recalculating stats', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $group = ItemGroup::factory()->create(['master' => 'CX90029', 'variant' => '02']);
    $item = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM]);

    $date = now()->toDateString();

    $makeSell = function (int $senderId) use ($warehouse, $customer, $item, $date) {
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
            'quantity' => 2,
            'price' => 10000,
            'total' => 20000,
            'date' => $date,
            'transaction_type' => \App\Models\Transaction::TYPE_SELL,
            'sender_id' => $senderId,
            'receiver_id' => $customer->id,
        ]);
    };

    $makeSell($warehouse->id);
    $makeSell(0);
    $makeSell(999999);

    $this->artisan('app:recalculate-warehouse-item-stats')->assertSuccessful();

    $stats = WarehouseItemMonthlyStat::query()->where('item_id', $item->id)->get();

    expect($stats)->toHaveCount(1);
    expect($stats->first()->warehouse_id)->toBe($warehouse->id);
    expect((float) $stats->first()->sold_qty)->toBe(2.0);
});

it('queues a background refresh job from the report page', function () {
    Queue::fake();

    $source = Addrbook::factory()->warehouse()->create(['name' => 'Source WH']);
    $destination = Addrbook::factory()->warehouse()->create([
        'name' => 'Flagship WH',
        'arrangement_enabled' => true,
    ]);

    $destination->arrangementSources()->sync([$source->id]);

    $group = ItemGroup::factory()->create(['master' => 'CX90036', 'variant' => '02']);
    $anchor = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90036-02', 'code' => 'AJD-CX90036-02-S']);
    $missing = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90036-02', 'code' => 'AJD-CX90036-02-M']);

    WarehouseItem::create(['warehouse_id' => $source->id, 'item_id' => $missing->id, 'quantity' => 4]);
    WarehouseItem::create(['warehouse_id' => $destination->id, 'item_id' => $anchor->id, 'quantity' => 1]);

    $customer = Addrbook::factory()->customer()->create();
    $date = now()->toDateString();

    \App\Models\Transaction::factory()->create([
        'type' => \App\Models\Transaction::TYPE_SELL,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $destination->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
        'date' => $date,
        'user_id' => $this->user->id,
    ])->details()->create([
        'item_id' => $anchor->id,
        'quantity' => 5,
        'price' => 10000,
        'total' => 50000,
        'date' => $date,
        'transaction_type' => \App\Models\Transaction::TYPE_SELL,
        'sender_id' => $destination->id,
        'receiver_id' => $customer->id,
    ]);

    $this->actingAs($this->user)
        ->post(route('reports.warehouse-arrangement.refresh'), [
            'warehouse_id' => $destination->id,
            'demand_days' => 365,
            'mode' => WarehouseArrangementService::MODE_DEMAND,
        ])
        ->assertRedirect(route('reports.warehouse-arrangement', [
            'warehouse_id' => $destination->id,
            'demand_days' => 365,
            'mode' => WarehouseArrangementService::MODE_DEMAND,
        ]))
        ->assertSessionHas('success');

    $job = WarehouseArrangementRefreshJob::query()
        ->where('destination_warehouse_id', $destination->id)
        ->first();

    expect($job)->not->toBeNull();
    expect($job->user_id)->toBe($this->user->id);
    expect($job->status)->toBe(WarehouseArrangementRefreshJob::STATUS_CREATED);

    Queue::assertPushed(ProcessWarehouseArrangementRefreshBatch::class, fn ($queued) => $queued->refreshJobId === $job->id);

    Queue::fake(false);

    $this->artisan('app:process-warehouse-arrangement-refresh')->assertSuccessful();

    $job->refresh();
    expect($job->status)->toBe(WarehouseArrangementRefreshJob::STATUS_COMPLETED);
    expect(WarehouseArrangementCandidate::query()->where('destination_warehouse_id', $destination->id)->count())->toBeGreaterThan(0);
});

it('blocks a second refresh job for the same warehouse while one is active', function () {
    $destination = Addrbook::factory()->warehouse()->create(['arrangement_enabled' => true]);

    WarehouseArrangementRefreshJob::create([
        'destination_warehouse_id' => $destination->id,
        'user_id' => $this->user->id,
        'status' => WarehouseArrangementRefreshJob::STATUS_PROCESSING,
        'phase' => WarehouseArrangementRefreshJob::PHASE_STATS,
        'total_items' => 10,
    ]);

    $this->actingAs($this->user)
        ->post(route('reports.warehouse-arrangement.refresh'), [
            'warehouse_id' => $destination->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(WarehouseArrangementRefreshJob::query()->where('destination_warehouse_id', $destination->id)->count())->toBe(1);
});

it('marks refresh jobs failed so the button can be used again', function () {
    $destination = Addrbook::factory()->warehouse()->create(['arrangement_enabled' => true]);

    $job = WarehouseArrangementRefreshJob::create([
        'destination_warehouse_id' => $destination->id,
        'user_id' => $this->user->id,
        'status' => WarehouseArrangementRefreshJob::STATUS_PROCESSING,
        'phase' => WarehouseArrangementRefreshJob::PHASE_SYNC,
        'total_items' => 0,
        'started_at' => now(),
    ]);

    $this->mock(WarehouseArrangementSyncService::class, function ($mock) {
        $mock->shouldReceive('arrangementTablesExist')->andReturn(true);
        $mock->shouldReceive('syncAll')->andThrow(new \RuntimeException('sync boom'));
    });

    $this->artisan('app:process-warehouse-arrangement-refresh')->assertSuccessful();

    $job->refresh();
    expect($job->status)->toBe(WarehouseArrangementRefreshJob::STATUS_FAILED);
    expect($job->error_message)->toBe('sync boom');

    $this->actingAs($this->user)
        ->get(route('reports.warehouse-arrangement', ['warehouse_id' => $destination->id]))
        ->assertOk()
        ->assertSee('Last rebuild failed', false)
        ->assertSee('sync boom', false)
        ->assertSee('Rebuild stats &amp; refresh', false);
});

it('advances refresh jobs via the tick endpoint', function () {
    Queue::fake();

    $source = Addrbook::factory()->warehouse()->create();
    $destination = Addrbook::factory()->warehouse()->create(['arrangement_enabled' => true]);
    $destination->arrangementSources()->sync([$source->id]);

    $group = ItemGroup::factory()->create(['master' => 'CX90120', 'variant' => '02']);
    $anchor = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90120-02', 'code' => 'AJD-CX90120-02-S']);
    $missing = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90120-02', 'code' => 'AJD-CX90120-02-M']);

    WarehouseItem::create(['warehouse_id' => $source->id, 'item_id' => $missing->id, 'quantity' => 3]);
    WarehouseItem::create(['warehouse_id' => $destination->id, 'item_id' => $anchor->id, 'quantity' => 1]);

    $customer = Addrbook::factory()->customer()->create();
    $date = now()->toDateString();

    \App\Models\Transaction::factory()->create([
        'type' => \App\Models\Transaction::TYPE_SELL,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'sender_id' => $destination->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
        'date' => $date,
        'user_id' => $this->user->id,
    ])->details()->create([
        'item_id' => $anchor->id,
        'quantity' => 4,
        'price' => 10000,
        'total' => 40000,
        'date' => $date,
        'transaction_type' => \App\Models\Transaction::TYPE_SELL,
        'sender_id' => $destination->id,
        'receiver_id' => $customer->id,
    ]);

    $job = app(\App\Services\WarehouseArrangementRefreshService::class)->createJob($destination->id, $this->user->id);
    expect($job->status)->toBe(WarehouseArrangementRefreshJob::STATUS_CREATED);

    $this->actingAs($this->user)
        ->postJson(route('reports.warehouse-arrangement.tick-refresh'), [
            'warehouse_id' => $destination->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('status', WarehouseArrangementRefreshJob::STATUS_COMPLETED)
        ->assertJsonPath('done', true);

    expect(WarehouseArrangementCandidate::query()->where('destination_warehouse_id', $destination->id)->count())->toBeGreaterThan(0);
});

it('allows cancelling a stuck refresh job', function () {
    $destination = Addrbook::factory()->warehouse()->create(['arrangement_enabled' => true]);

    WarehouseArrangementRefreshJob::create([
        'destination_warehouse_id' => $destination->id,
        'user_id' => $this->user->id,
        'status' => WarehouseArrangementRefreshJob::STATUS_CREATED,
        'phase' => WarehouseArrangementRefreshJob::PHASE_STATS,
        'total_items' => 100,
    ]);

    $this->actingAs($this->user)
        ->post(route('reports.warehouse-arrangement.cancel-refresh'), [
            'warehouse_id' => $destination->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(WarehouseArrangementRefreshJob::query()->where('destination_warehouse_id', $destination->id)->first()->status)
        ->toBe(WarehouseArrangementRefreshJob::STATUS_FAILED);
});

it('processes refresh jobs in sku batches', function () {
    Queue::fake();

    $source = Addrbook::factory()->warehouse()->create();
    $destination = Addrbook::factory()->warehouse()->create(['arrangement_enabled' => true]);
    $destination->arrangementSources()->sync([$source->id]);

    $customer = Addrbook::factory()->customer()->create();
    $date = now()->toDateString();
    $group = ItemGroup::factory()->create(['master' => 'CX90110', 'variant' => '02']);

    for ($i = 1; $i <= 5; $i++) {
        $item = Item::factory()->create([
            'group_id' => $group->id,
            'type' => ItemType::ITEM,
            'pcode' => 'CX90110-02',
            'code' => "AJD-CX90110-02-{$i}",
        ]);

        \App\Models\Transaction::factory()->create([
            'type' => \App\Models\Transaction::TYPE_SELL,
            'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
            'sender_id' => $destination->id,
            'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
            'receiver_id' => $customer->id,
            'date' => $date,
            'user_id' => $this->user->id,
        ])->details()->create([
            'item_id' => $item->id,
            'quantity' => 1,
            'price' => 1000,
            'total' => 1000,
            'date' => $date,
            'transaction_type' => \App\Models\Transaction::TYPE_SELL,
            'sender_id' => $destination->id,
            'receiver_id' => $customer->id,
        ]);
    }

    $job = app(\App\Services\WarehouseArrangementRefreshService::class)->createJob($destination->id, null);
    expect($job->total_items)->toBe(5);

    $this->artisan('app:process-warehouse-arrangement-refresh', ['--batch' => 2])->assertSuccessful();

    $job->refresh();
    expect($job->item_cursor)->toBe(2);
    expect($job->phase)->toBe(WarehouseArrangementRefreshJob::PHASE_STATS);
    expect($job->initiatedByLabel())->toBe('System');
});

it('syncs arrangement for items with legacy type column values', function () {
    $source = Addrbook::factory()->warehouse()->create();
    $destination = Addrbook::factory()->warehouse()->create(['arrangement_enabled' => true]);
    $destination->arrangementSources()->sync([$source->id]);

    $group = ItemGroup::factory()->create(['master' => 'CX90037', 'variant' => '02']);
    $anchor = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90037-02', 'code' => 'AJD-CX90037-02-S']);
    $missing = Item::factory()->create(['group_id' => $group->id, 'type' => ItemType::ITEM, 'pcode' => 'CX90037-02', 'code' => 'AJD-CX90037-02-M']);
    DB::table('items')->where('id', $missing->id)->update(['type' => 4]);

    WarehouseItem::create(['warehouse_id' => $source->id, 'item_id' => $missing->id, 'quantity' => 3]);
    WarehouseItem::create(['warehouse_id' => $destination->id, 'item_id' => $anchor->id, 'quantity' => 1]);

    $now = now();
    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $destination->id,
        'item_id' => $anchor->id,
        'month' => $now->month,
        'year' => $now->year,
        'sold_qty' => 6,
        'returned_qty' => 0,
        'item_type' => ItemType::ITEM->value,
    ]);

    app(WarehouseArrangementSyncService::class)->syncAll($destination->id);

    $candidate = WarehouseArrangementCandidate::query()
        ->where('destination_warehouse_id', $destination->id)
        ->where('item_id', $missing->id)
        ->first();

    expect($candidate)->not->toBeNull();
});
