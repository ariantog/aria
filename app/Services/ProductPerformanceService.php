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

    public function normalizeItemType(?string $raw): ?int
    {
        if ($raw === null || $raw === '' || $raw === 'all') {
            return null;
        }

        $value = (int) $raw;

        return in_array($value, [ItemType::ITEM->value, ItemType::ASSET_LANCAR->value], true) ? $value : null;
    }
}
