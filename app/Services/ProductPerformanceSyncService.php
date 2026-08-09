<?php

namespace App\Services;

use App\Enums\AddrbookType;
use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\ProductPerformanceRollup;
use App\Services\Items\ItemDimensionResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductPerformanceSyncService
{
    public const LENS_COMPANY = 'company';

    public const LENS_WAREHOUSE = 'warehouse';

    public const TOP_LIMIT = 100;

    public function __construct(private readonly ItemDimensionResolver $dimensions) {}

    /**
     * @return array{periods: int, rollups: int}
     */
    public function syncAll(): array
    {
        $rollupCount = 0;

        foreach (ItemDimensionResolver::validPeriods() as $periodDays) {
            $rollupCount += $this->syncPeriod($periodDays);
        }

        return ['periods' => count(ItemDimensionResolver::validPeriods()), 'rollups' => $rollupCount];
    }

    public function syncPeriod(int $periodDays): int
    {
        $startKey = ItemDimensionResolver::periodStartKey($periodDays);
        $rows = DB::table('warehouse_item_monthly_stats as w')
            ->join('items as i', 'i.id', '=', 'w.item_id')
            ->whereNull('i.deleted_at')
            ->whereRaw('(w.year * 12 + w.month) >= ?', [$startKey])
            ->select([
                'w.warehouse_id',
                'w.item_id',
                'w.year',
                'w.month',
                'w.sold_qty',
                'w.returned_qty',
                'w.sold_value',
                'w.returned_value',
                'w.item_type',
                'w.pcode',
                'w.type_code',
                'w.warna_code',
                'w.size_code',
                'i.name as item_name',
            ])
            ->get();

        ProductPerformanceRollup::query()->where('period_days', $periodDays)->delete();

        $count = 0;
        $count += $this->buildLensRollups($periodDays, self::LENS_COMPANY, 0, $rows);
        foreach ($this->warehouseIds($rows) as $warehouseId) {
            $count += $this->buildLensRollups(
                $periodDays,
                self::LENS_WAREHOUSE,
                $warehouseId,
                $rows->where('warehouse_id', $warehouseId)->values(),
            );
        }

        return $count;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<int>
     */
    private function warehouseIds(Collection $rows): array
    {
        return $rows->pluck('warehouse_id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private function buildLensRollups(int $periodDays, string $lens, int $warehouseId, Collection $rows): int
    {
        $count = 0;
        $now = now();

        foreach (ItemDimensionResolver::validGrains() as $grain) {
            foreach ([null, ItemType::ITEM->value, ItemType::ASSET_LANCAR->value] as $itemTypeFilter) {
                $aggregates = $this->aggregateGrain($rows, $grain, $itemTypeFilter, $periodDays);
                if ($aggregates === []) {
                    continue;
                }

                $metric = $lens === self::LENS_COMPANY ? 'net_value' : 'net_qty';
                usort($aggregates, fn (array $a, array $b) => $b[$metric] <=> $a[$metric]);
                $totalMetric = array_sum(array_column($aggregates, $metric));

                $rank = 0;
                foreach (array_slice($aggregates, 0, self::TOP_LIMIT) as $row) {
                    $rank++;
                    ProductPerformanceRollup::query()->create([
                        'period_days' => $periodDays,
                        'lens' => $lens,
                        'warehouse_id' => $warehouseId,
                        'grain' => $grain,
                        'dimension_key' => $row['dimension_key'],
                        'item_type' => $itemTypeFilter,
                        'label' => $row['label'],
                        'net_qty' => $row['net_qty'],
                        'net_value' => $row['net_value'],
                        'pct_of_total' => $totalMetric > 0 ? round($row[$metric] / $totalMetric * 100, 4) : 0,
                        'rank' => $rank,
                        'synced_at' => $now,
                    ]);
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array<string, mixed>>
     */
    private function aggregateGrain(Collection $rows, string $grain, ?int $itemTypeFilter, int $periodDays): array
    {
        $startKey = ItemDimensionResolver::periodStartKey($periodDays);
        $buckets = [];

        foreach ($rows as $row) {
            $itemType = (int) ($row->item_type ?? ItemType::ITEM->value);
            if ($itemTypeFilter !== null && $itemType !== $itemTypeFilter) {
                continue;
            }

            $period = (int) $row->year * 12 + (int) $row->month;
            if ($period < $startKey) {
                continue;
            }

            $dims = [
                'item_id' => (int) $row->item_id,
                'pcode' => $row->pcode ?: '-',
                'type_code' => $row->type_code ?: '-',
                'warna_code' => $row->warna_code ?: '-',
                'size_code' => $row->size_code ?: '-',
            ];

            $key = $this->dimensions->grainKey($grain, $dims);
            if ($key === '' || $key === '-') {
                continue;
            }

            $buckets[$key] ??= [
                'dimension_key' => $key,
                'label' => $this->dimensions->grainLabel($grain, $dims, $row->item_name ?? null),
                'net_qty' => 0.0,
                'net_value' => 0.0,
            ];

            $buckets[$key]['net_qty'] += max(0.0, (float) $row->sold_qty - (float) $row->returned_qty);
            $buckets[$key]['net_value'] += max(0.0, (float) $row->sold_value - (float) $row->returned_value);
        }

        return array_values(array_filter($buckets, fn (array $b) => $b['net_qty'] > 0 || $b['net_value'] > 0));
    }
}
