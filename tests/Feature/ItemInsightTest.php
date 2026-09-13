<?php

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemInsightMonth;
use App\Models\ItemInsightRanking;
use App\Models\User;
use App\Models\WarehouseItemMonthlyStat;
use App\Services\ItemInsightQueryService;
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
            'grain' => ItemInsightQueryService::GRAIN_MONTH,
            'period' => '2025-12',
            'tab' => ItemInsightRanking::CATEGORY_BEST_SELLING,
        ])
        ->assertRedirect(route('reports.item-insights', [
            'grain' => ItemInsightQueryService::GRAIN_MONTH,
            'period' => '2025-12',
            'tab' => ItemInsightRanking::CATEGORY_BEST_SELLING,
        ]))
        ->assertSessionHas('status');

    expect(ItemInsightMonth::query()->where('year', 2025)->where('month', 12)->exists())->toBeTrue();
});

it('recalculates yearly insights from calculated months only when partial year', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create(['name' => 'Year Hero', 'cost' => 1000, 'price' => 5000]);
    $year = 2026;

    foreach ([1, 3] as $month) {
        WarehouseItemMonthlyStat::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'year' => $year,
            'month' => $month,
            'sold_qty' => 10,
            'returned_qty' => 0,
            'sold_value' => 50_000,
            'returned_value' => 0,
        ]);
        app(ItemInsightSyncService::class)->recalculateMonth($year, $month);
    }

    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'year' => $year,
        'month' => 6,
        'sold_qty' => 99,
        'returned_qty' => 0,
        'sold_value' => 500_000,
        'returned_value' => 0,
    ]);

    $result = app(ItemInsightSyncService::class)->recalculateYear($year);
    expect($result['months_included'])->toBe([1, 3]);

    $yearRow = ItemInsightMonth::query()
        ->where('year', $year)
        ->where('month', ItemInsightMonth::MONTH_YEARLY)
        ->first();

    expect($yearRow)->not->toBeNull();
    expect($yearRow->monthsIncludedList())->toBe([1, 3]);

    $best = ItemInsightRanking::query()
        ->where('year', $year)
        ->where('month', ItemInsightMonth::MONTH_YEARLY)
        ->where('category', ItemInsightRanking::CATEGORY_BEST_SELLING)
        ->orderBy('rank')
        ->first();

    expect((float) $best?->net_qty)->toBe(20.0);
});

it('falls back to warehouse stats months for yearly when no monthly insights exist', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create(['cost' => 500, 'price' => 2000]);
    $year = 2024;

    WarehouseItemMonthlyStat::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'year' => $year,
        'month' => 7,
        'sold_qty' => 4,
        'returned_qty' => 0,
        'sold_value' => 8000,
        'returned_value' => 0,
    ]);

    $result = app(ItemInsightSyncService::class)->recalculateYear($year);
    expect($result['months_included'])->toBe([7]);
    expect(ItemInsightRanking::query()->where('year', $year)->where('month', ItemInsightMonth::MONTH_YEARLY)->exists())->toBeTrue();
});

it('renders yearly item insights from stored rankings', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('report-item-insights');

    ItemInsightMonth::create([
        'year' => 2025,
        'month' => ItemInsightMonth::MONTH_YEARLY,
        'row_count' => 1,
        'months_included' => [1, 2, 3],
        'calculated_at' => now(),
    ]);
    ItemInsightRanking::create([
        'year' => 2025,
        'month' => ItemInsightMonth::MONTH_YEARLY,
        'category' => ItemInsightRanking::CATEGORY_BEST_SELLING,
        'rank' => 1,
        'item_id' => 9,
        'item_name' => 'Yearly Top SKU',
        'item_code' => 'Y-1',
        'net_qty' => 120,
        'net_value' => 1_200_000,
        'cost_total' => 400_000,
        'profit' => 800_000,
        'margin_pct' => 66.6667,
        'daily_velocity' => 1.32,
    ]);

    $this->actingAs($user)
        ->get(route('reports.item-insights', [
            'grain' => 'year',
            'period' => '2025',
            'tab' => ItemInsightRanking::CATEGORY_BEST_SELLING,
        ]))
        ->assertOk()
        ->assertSee('Yearly Top SKU', false)
        ->assertSee('partial year', false)
        ->assertSee('item-insights-year-tracker', false);
});
