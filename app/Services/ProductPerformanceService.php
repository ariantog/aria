<?php

namespace App\Services;

use App\Enums\AddrbookType;
use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\ProductPerformanceRollup;
use App\Services\Items\ItemDimensionResolver;
use Illuminate\Support\Collection;

class ProductPerformanceService
{
    public const TAB_SALES = 'sales';

    public const TAB_DEMAND = 'demand';

    public const TAB_ATTRIBUTES = 'attributes';

    /**
     * @return list<string>
     */
    public static function validTabs(): array
    {
        return [self::TAB_SALES, self::TAB_DEMAND, self::TAB_ATTRIBUTES];
    }

    /**
     * Attribute grains ordered by typical retail / apparel usage.
     *
     * @return array<string, string>
     */
    public static function attributeGrainOptions(): array
    {
        return [
            'type' => 'Type',
            'type_size' => 'Type + Size',
            'type_warna' => 'Type + Color',
            'type_warna_size' => 'Type + Color + Size',
            'warna_size' => 'Color + Size',
            'pcode' => 'Product family (pcode)',
        ];
    }

    public function warehouses(): Collection
    {
        return Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function latestSyncedAt(): ?\Carbon\CarbonInterface
    {
        $value = ProductPerformanceRollup::query()->max('synced_at');

        return $value ? \Illuminate\Support\Carbon::parse($value) : null;
    }

    public function hasData(): bool
    {
        return ProductPerformanceRollup::query()->exists();
    }

    /**
     * @return array{
     *     rows: Collection<int, ProductPerformanceRollup>,
     *     synced_at: ?\Carbon\CarbonInterface,
     *     stale: bool
     * }
     */
    public function fetch(
        string $tab,
        int $periodDays = 90,
        ?int $warehouseId = null,
        ?int $itemType = null,
        string $grain = 'type_size',
    ): array {
        $periodDays = in_array($periodDays, ItemDimensionResolver::validPeriods(), true) ? $periodDays : 90;
        $lens = $tab === self::TAB_DEMAND ? ProductPerformanceSyncService::LENS_WAREHOUSE : ProductPerformanceSyncService::LENS_COMPANY;
        $rollupGrain = $tab === self::TAB_ATTRIBUTES ? $grain : ($tab === self::TAB_SALES ? 'item' : 'item');

        if ($tab === self::TAB_DEMAND && ! $warehouseId) {
            return ['rows' => collect(), 'synced_at' => $this->latestSyncedAt(), 'stale' => false];
        }

        $query = ProductPerformanceRollup::query()
            ->where('period_days', $periodDays)
            ->where('lens', $lens)
            ->where('grain', $rollupGrain)
            ->when($tab === self::TAB_DEMAND, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($tab !== self::TAB_DEMAND, fn ($q) => $q->where('warehouse_id', 0))
            ->when($itemType, fn ($q) => $q->where('item_type', $itemType))
            ->when(! $itemType, fn ($q) => $q->whereNull('item_type'))
            ->orderBy('rank');

        $syncedAt = $this->latestSyncedAt();
        $stale = $syncedAt ? $syncedAt->lt(now()->subDay()) : true;

        return [
            'rows' => $query->limit(ProductPerformanceSyncService::TOP_LIMIT)->get(),
            'synced_at' => $syncedAt,
            'stale' => $stale,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function periodOptions(): array
    {
        return [
            30 => '1 month',
            90 => '3 months',
            180 => '6 months',
            365 => '1 year',
        ];
    }

    public function normalizePeriodDays(int|string|null $raw): int
    {
        $period = (int) $raw;

        return in_array($period, ItemDimensionResolver::validPeriods(), true) ? $period : 90;
    }

    /**
     * Monthly sell/return breakdown for a single SKU from cached warehouse stats.
     *
     * @return array{
     *     months: list<array{
     *         label: string,
     *         year: int,
     *         month: int,
     *         sold_qty: float,
     *         returned_qty: float,
     *         net_qty: float,
     *         sold_value: float,
     *         returned_value: float,
     *         net_value: float
     *     }>,
     *     totals: array<string, float>,
     *     synced_at: ?\Carbon\CarbonInterface,
     *     stale: bool,
     *     has_data: bool
     * }
     */
    public function itemMonthlyBreakdown(int $itemId, int $periodDays = 90, ?int $warehouseId = null): array
    {
        $periodDays = in_array($periodDays, ItemDimensionResolver::validPeriods(), true) ? $periodDays : 90;
        $startKey = ItemDimensionResolver::periodStartKey($periodDays);

        $rows = \App\Models\WarehouseItemMonthlyStat::query()
            ->where('item_id', $itemId)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->whereRaw('(year * 12 + month) >= ?', [$startKey])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        $months = [];
        $totals = [
            'sold_qty' => 0.0,
            'returned_qty' => 0.0,
            'net_qty' => 0.0,
            'sold_value' => 0.0,
            'returned_value' => 0.0,
            'net_value' => 0.0,
        ];

        $grouped = $rows->groupBy(fn ($row) => $row->year.'-'.str_pad((string) $row->month, 2, '0', STR_PAD_LEFT));

        foreach ($grouped->sortKeysDesc() as $periodKey => $periodRows) {
            $soldQty = (float) $periodRows->sum('sold_qty');
            $returnedQty = (float) $periodRows->sum('returned_qty');
            $soldValue = (float) $periodRows->sum('sold_value');
            $returnedValue = (float) $periodRows->sum('returned_value');
            $netQty = max(0.0, $soldQty - $returnedQty);
            $netValue = max(0.0, $soldValue - $returnedValue);

            [$year, $month] = array_map('intval', explode('-', (string) $periodKey));
            $label = \Illuminate\Support\Carbon::createFromDate($year, $month, 1)->format('F Y');

            $months[] = [
                'label' => $label,
                'year' => $year,
                'month' => $month,
                'sold_qty' => $soldQty,
                'returned_qty' => $returnedQty,
                'net_qty' => $netQty,
                'sold_value' => $soldValue,
                'returned_value' => $returnedValue,
                'net_value' => $netValue,
            ];

            $totals['sold_qty'] += $soldQty;
            $totals['returned_qty'] += $returnedQty;
            $totals['net_qty'] += $netQty;
            $totals['sold_value'] += $soldValue;
            $totals['returned_value'] += $returnedValue;
            $totals['net_value'] += $netValue;
        }

        $syncedAt = $rows->max('updated_at');
        $syncedAt = $syncedAt ? \Illuminate\Support\Carbon::parse($syncedAt) : $this->latestSyncedAt();
        $stale = $syncedAt ? $syncedAt->lt(now()->subDay()) : true;

        return [
            'months' => $months,
            'totals' => $totals,
            'synced_at' => $syncedAt,
            'stale' => $stale,
            'has_data' => $months !== [],
        ];
    }

    public function normalizeItemType(?string $raw): ?int
    {
        if ($raw === null || $raw === '' || $raw === 'all') {
            return null;
        }

        $value = (int) $raw;

        return in_array($value, [ItemType::ITEM->value, ItemType::ASSET_LANCAR->value], true) ? $value : null;
    }
}
