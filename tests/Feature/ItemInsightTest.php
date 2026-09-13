<?php

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemInsightMonth;
use App\Models\ItemInsightRanking;
use App\Models\User;
use App\Models\WarehouseItemMonthlyStat;
use App\Services\ItemInsightSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('report-item-insights', 'web');
});

it('recalculates monthly item insight rankings from warehouse stats', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $group = ItemGroup::factory()->create(['master' => 'CX10001-01', 'name' => 'Shirt']);
    $winner = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'name' => 'Shirt Winner',
        'code' => 'AJD-CX10001-01-M',
        'cost' => 40000,
        'price' => 100000,
    ]);
    $slow = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'name' => 'Shirt Slow',
        'code' => 'AJD-CX10001-01-S',
        'cost' => 50000,
        'price' => 120000,
    ]);
    $loss = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'name' => 'Shirt Loss',
        'code' => 'AJD-CX10001-01-L',
        'cost' => 90000,
        'price' => 80000,
    ]);

    $year = 2026;
    $month = 3;

    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $winner->id,
        'year' => $year,
        'month' => $month,
        'sold_qty' => 100,
        'returned_qty' => 0,
        'sold_value' => 10_000_000,
        'returned_value' => 0,
        'item_type' => ItemType::ITEM->value,
        'group_id' => $group->id,
        'pcode' => 'CX10001-01',
    ]);
    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $slow->id,
        'year' => $year,
        'month' => $month,
        'sold_qty' => 5,
        'returned_qty' => 0,
        'sold_value' => 500_000,
        'returned_value' => 0,
        'item_type' => ItemType::ITEM->value,
        'group_id' => $group->id,
        'pcode' => 'CX10001-01',
    ]);
    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $loss->id,
        'year' => $year,
        'month' => $month,
        'sold_qty' => 20,
        'returned_qty' => 0,
        'sold_value' => 1_000_000,
        'returned_value' => 0,
        'item_type' => ItemType::ITEM->value,
        'group_id' => $group->id,
        'pcode' => 'CX10001-01',
    ]);

    $result = app(ItemInsightSyncService::class)->recalculateMonth($year, $month, 1);
    expect($result['rows'])->toBeGreaterThan(0);

    $best = ItemInsightRanking::query()
        ->where('year', $year)
        ->where('month', $month)
        ->where('category', ItemInsightRanking::CATEGORY_BEST_SELLING)
        ->orderBy('rank')
        ->first();

    expect($best?->item_id)->toBe($winner->id);

    $lossLeader = ItemInsightRanking::query()
        ->where('year', $year)
        ->where('month', $month)
        ->where('category', ItemInsightRanking::CATEGORY_LOSS_LEADER)
        ->orderBy('rank')
        ->first();

    expect($lossLeader?->item_id)->toBe($loss->id);
    expect((float) $lossLeader?->profit)->toBeLessThan(0);

    expect(ItemInsightMonth::query()->where('year', $year)->where('month', $month)->exists())->toBeTrue();
});

it('renders item insights page from stored rankings without recalculating on GET', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('report-item-insights');

    $year = 2026;
    $month = 4;
    ItemInsightMonth::create([
        'year' => $year,
        'month' => $month,
        'row_count' => 2,
        'calculated_at' => now(),
    ]);
    ItemInsightRanking::create([
        'year' => $year,
        'month' => $month,
        'category' => ItemInsightRanking::CATEGORY_BEST_SELLING,
        'rank' => 1,
        'item_id' => 1,
        'item_name' => 'Cached Hero SKU',
        'item_code' => 'SKU-1',
        'net_qty' => 50,
        'net_value' => 5_000_000,
        'cost_total' => 2_000_000,
        'profit' => 3_000_000,
        'margin_pct' => 60,
        'daily_velocity' => 1.67,
    ]);

    $this->actingAs($user)
        ->get(route('reports.item-insights', ['period' => '2026-04', 'tab' => ItemInsightRanking::CATEGORY_BEST_SELLING]))
        ->assertOk()
        ->assertSee('Item Insights', false)
        ->assertSee('Cached Hero SKU', false)
        ->assertSee('2026-04', false)
        ->assertSee('item-insights-month-tracker', false);
});

it('recalculates a month via POST and redirects with status', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('report-item-insights');

    $warehouse = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create(['cost' => 1000, 'price' => 5000]);
    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'year' => 2025,
        'month' => 12,
        'sold_qty' => 3,
        'returned_qty' => 0,
        'sold_value' => 15000,
        'returned_value' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('reports.item-insights.recalculate'), [
            'period' => '2025-12',
            'tab' => ItemInsightRanking::CATEGORY_BEST_SELLING,
        ])
        ->assertRedirect(route('reports.item-insights', [
            'period' => '2025-12',
            'tab' => ItemInsightRanking::CATEGORY_BEST_SELLING,
        ]))
        ->assertSessionHas('status');

    expect(ItemInsightMonth::query()->where('year', 2025)->where('month', 12)->exists())->toBeTrue();
});
