<?php

namespace App\Services;

use App\Enums\AddrbookType;
use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WarehouseArrangementService
{
    public const MODE_DEMAND = 'demand';

    public const MODE_FAMILY = 'family';

    public const PER_PAGE = 30;

    public const FAMILY_COMPLETENESS_THRESHOLD = 75.0;

    public const UI_MAX_FAMILIES_SCAN = 60;

    public const UI_MAX_SUGGESTIONS_SCAN = 1500;

    public const EXPORT_MAX_FAMILIES = 200;

    public const EXPORT_MAX_SUGGESTIONS = 5000;

    public const MAX_SOURCE_SLOTS = 3;

    /**
     * @return list<string>
     */
    public static function validModes(): array
    {
        return [self::MODE_DEMAND, self::MODE_FAMILY];
    }

    /**
     * @return Collection<int, Addrbook>
     */
    public function destinationWarehouses(): Collection
    {
        return Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->where('arrangement_enabled', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @param  list<int>  $excludeItemIds
     * @return array{
     *     destination: Addrbook,
     *     demand_days: int,
     *     mode: string,
     *     page: int,
     *     per_page: int,
     *     total_pcodes: int,
     *     search: string,
     *     suggestions: list<array<string, mixed>>,
     *     truncated: bool
     * }
     */
    public function buildPage(
        int $destinationWarehouseId,
        int $demandDays = 365,
        string $mode = self::MODE_DEMAND,
        int $page = 1,
        int $perPage = self::PER_PAGE,
        string $search = '',
        array $excludeItemIds = [],
    ): array {
        if (! in_array($mode, self::validModes(), true)) {
            $mode = self::MODE_DEMAND;
        }

        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $search = trim($search);

        $all = $this->collectSuggestions(
            $destinationWarehouseId,
            $demandDays,
            $mode,
            self::UI_MAX_FAMILIES_SCAN,
            self::UI_MAX_SUGGESTIONS_SCAN,
            $excludeItemIds,
        );

        $grouped = collect($all['suggestions'])->groupBy('pcode');

        if ($search !== '') {
            $needle = strtoupper($search);
            $grouped = $grouped->filter(fn ($items, $pcode) => str_contains(strtoupper($pcode), $needle));
        }

        $sortedPcodes = $this->sortPcodes($grouped, $mode);

        $totalPcodes = $sortedPcodes->count();
        $pagePcodes = $sortedPcodes->slice(($page - 1) * $perPage, $perPage)->values();

        $suggestions = $pagePcodes
            ->flatMap(fn ($pcode) => $grouped->get($pcode, collect()))
            ->values()
            ->all();

        return [
            'destination' => $all['destination'],
            'demand_days' => $demandDays,
            'mode' => $mode,
            'page' => $page,
            'per_page' => $perPage,
            'total_pcodes' => $totalPcodes,
            'search' => $search,
            'suggestions' => $suggestions,
            'truncated' => $all['truncated'],
        ];
    }

    /**
     * @param  list<int>  $excludeItemIds
     * @return array{destination: Addrbook, suggestions: list<array<string, mixed>>}
     */
    public function buildSuggestionsForExport(
        int $destinationWarehouseId,
        int $demandDays = 365,
        string $mode = self::MODE_DEMAND,
        array $excludeItemIds = [],
    ): array {
        $result = $this->collectSuggestions(
            $destinationWarehouseId,
            $demandDays,
            $mode,
            self::EXPORT_MAX_FAMILIES,
            self::EXPORT_MAX_SUGGESTIONS,
            $excludeItemIds,
        );

        return [
            'destination' => $result['destination'],
            'suggestions' => $result['suggestions'],
        ];
    }

    /**
     * @param  list<int>  $excludeItemIds
     * @return array{
     *     destination: Addrbook,
     *     suggestions: list<array<string, mixed>>,
     *     truncated: bool
     * }
     */
    private function collectSuggestions(
        int $destinationWarehouseId,
        int $demandDays,
        string $mode,
        int $maxFamilies,
        int $maxSuggestions,
        array $excludeItemIds = [],
    ): array {
        $destination = Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->where('arrangement_enabled', true)
            ->findOrFail($destinationWarehouseId);

        $startKey = $this->periodKey(now()->subDays($demandDays));

        $familyRows = $mode === self::MODE_FAMILY
            ? $this->loadFamiliesForCompletionMode($destinationWarehouseId, $startKey, $maxFamilies)
            : $this->loadTopFamiliesByDemand($destinationWarehouseId, $startKey, $maxFamilies);

        if ($familyRows->isEmpty()) {
            return [
                'destination' => $destination,
                'suggestions' => [],
                'truncated' => false,
            ];
        }

        $masters = $familyRows->pluck('master')->all();
        $completenessByMaster = $this->familyCompletenessForMasters($masters, $destinationWarehouseId);
        $familyMetaByMaster = $familyRows->keyBy('master');

        $eligibleMasters = [];
        foreach ($familyRows as $familyRow) {
            $master = $familyRow->master;
            $completeness = $completenessByMaster[$master] ?? ['total' => 0, 'present' => 0, 'pct' => 0.0];

            if ($mode === self::MODE_FAMILY && $completeness['pct'] >= self::FAMILY_COMPLETENESS_THRESHOLD) {
                continue;
            }

            $eligibleMasters[] = $master;
        }

        if ($eligibleMasters === []) {
            return [
                'destination' => $destination,
                'suggestions' => [],
                'truncated' => false,
            ];
        }

        $sizeCodes = Tag::query()->where('type', Tag::TYPE_SIZE)->pluck('code', 'id');
        $warnaByItem = $this->loadWarnaCodesForMasters($eligibleMasters);
        $excludeSet = array_flip(array_map('intval', $excludeItemIds));

        $items = DB::table('items as i')
            ->join('item_groups as ig', 'i.group_id', '=', 'ig.id')
            ->where('i.type', ItemType::ITEM->value)
            ->whereIn('ig.master', $eligibleMasters)
            ->whereNull('i.deleted_at')
            ->select('i.id', 'i.code', 'i.name', 'i.size', 'i.pcode', 'ig.master', 'ig.name as group_name')
            ->get();

        if ($items->isEmpty()) {
            return [
                'destination' => $destination,
                'suggestions' => [],
                'truncated' => false,
            ];
        }

        $itemIds = $items->pluck('id')->map(fn ($id) => (int) $id)->all();

        $destStock = DB::table('warehouse_items')
            ->where('warehouse_id', $destinationWarehouseId)
            ->whereIn('item_id', $itemIds)
            ->where('quantity', '>', 0)
            ->pluck('quantity', 'item_id');

        $sourceStock = DB::table('warehouse_items as wi')
            ->join('addrbooks as a', 'a.id', '=', 'wi.warehouse_id')
            ->whereIn('wi.item_id', $itemIds)
            ->where('wi.warehouse_id', '!=', $destinationWarehouseId)
            ->where('wi.quantity', '>', 0)
            ->where('a.type', AddrbookType::Warehouse->value)
            ->whereNull('a.deleted_at')
            ->orderByDesc('wi.quantity')
            ->get(['wi.warehouse_id', 'wi.item_id', 'wi.quantity', 'a.name as warehouse_name']);

        $sourcesByItem = $sourceStock->groupBy(fn ($row) => (int) $row->item_id);

        $itemDemand = DB::table('warehouse_item_monthly_stats')
            ->where('warehouse_id', $destinationWarehouseId)
            ->whereIn('item_id', $itemIds)
            ->whereRaw('(year * 12 + month) >= ?', [$startKey])
            ->selectRaw('item_id, SUM(sold_qty - returned_qty) as demand')
            ->groupBy('item_id')
            ->pluck('demand', 'item_id');

        $suggestions = [];
        $truncated = false;

        foreach ($items as $item) {
            $itemId = (int) $item->id;
            $master = $item->master;

            if (isset($excludeSet[$itemId])) {
                continue;
            }

            if (($destStock[$itemId] ?? 0) > 0) {
                continue;
            }

            $sources = $sourcesByItem->get($itemId, collect());
            if ($sources->isEmpty()) {
                continue;
            }

            $itemDemandVal = max(0.0, (float) ($itemDemand[$itemId] ?? 0));

            if ($mode === self::MODE_DEMAND && $itemDemandVal <= 0) {
                continue;
            }

            $sourceRows = $sources
                ->sortByDesc(fn ($source) => (float) $source->quantity)
                ->values()
                ->take(self::MAX_SOURCE_SLOTS)
                ->map(function ($source) use ($itemDemandVal, $mode) {
                    $sourceStockQty = (float) $source->quantity;
                    $qtyCap = $mode === self::MODE_DEMAND
                        ? max(1, (int) ceil($itemDemandVal))
                        : 1;

                    return [
                        'from_warehouse_id' => (int) $source->warehouse_id,
                        'from_warehouse_name' => $source->warehouse_name ?? 'Unknown',
                        'source_stock' => (int) $sourceStockQty,
                        'suggested_qty' => max(1, min((int) $sourceStockQty, $qtyCap)),
                    ];
                })
                ->values()
                ->all();

            if ($sourceRows === []) {
                continue;
            }

            if (count($suggestions) >= $maxSuggestions) {
                $truncated = true;
                break;
            }

            $familyRow = $familyMetaByMaster->get($master);
            $completeness = $completenessByMaster[$master] ?? ['total' => 0, 'present' => 0, 'pct' => 0.0];
            $pcode = strtoupper(trim($item->pcode ?? ''));

            $suggestions[] = [
                'master' => $master,
                'pcode' => $pcode !== '' ? $pcode : $master,
                'master_name' => $familyRow->name ?? $item->group_name ?? $master,
                'family_demand_score' => (float) ($familyRow->demand_score ?? 0),
                'completeness_pct' => $completeness['pct'],
                'present_count' => $completeness['present'],
                'total_count' => $completeness['total'],
                'item_id' => $itemId,
                'item_code' => $item->code,
                'item_name' => $item->name,
                'warna' => $warnaByItem[$itemId] ?? '-',
                'size' => $sizeCodes[$item->size] ?? '-',
                'item_demand' => $itemDemandVal,
                'sources' => $sourceRows,
                'to_warehouse_id' => $destinationWarehouseId,
                'to_warehouse_name' => $destination->name,
            ];
        }

        return [
            'destination' => $destination,
            'suggestions' => $suggestions,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  list<string>  $masters
     * @return array<string, array{total: int, present: int, pct: float}>
     */
    private function familyCompletenessForMasters(array $masters, int $warehouseId): array
    {
        if ($masters === []) {
            return [];
        }

        $totals = DB::table('items as i')
            ->join('item_groups as ig', 'i.group_id', '=', 'ig.id')
            ->where('i.type', ItemType::ITEM->value)
            ->whereIn('ig.master', $masters)
            ->whereNull('i.deleted_at')
            ->groupBy('ig.master')
            ->selectRaw('ig.master, COUNT(*) as total')
            ->pluck('total', 'master');

        $present = DB::table('items as i')
            ->join('item_groups as ig', 'i.group_id', '=', 'ig.id')
            ->join('warehouse_items as wi', 'wi.item_id', '=', 'i.id')
            ->where('i.type', ItemType::ITEM->value)
            ->whereIn('ig.master', $masters)
            ->where('wi.warehouse_id', $warehouseId)
            ->where('wi.quantity', '>', 0)
            ->whereNull('i.deleted_at')
            ->groupBy('ig.master')
            ->selectRaw('ig.master, COUNT(DISTINCT i.id) as present')
            ->pluck('present', 'master');

        $result = [];
        foreach ($masters as $master) {
            $total = (int) ($totals[$master] ?? 0);
            $presentCount = (int) ($present[$master] ?? 0);

            $result[$master] = [
                'total' => $total,
                'present' => $presentCount,
                'pct' => $total > 0 ? round($presentCount / $total * 100, 1) : 0.0,
            ];
        }

        return $result;
    }

    /**
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $grouped
     * @return Collection<int, string>
     */
    private function sortPcodes(Collection $grouped, string $mode): Collection
    {
        return $grouped
            ->map(function ($items, $pcode) use ($mode) {
                $demandSum = $items->sum(fn (array $row) => (float) ($row['item_demand'] ?? 0));
                $completeness = (float) ($items->first()['completeness_pct'] ?? 0);

                return [
                    'pcode' => $pcode,
                    'demand_sum' => $demandSum,
                    'completeness' => $completeness,
                    'family_demand' => (float) ($items->first()['family_demand_score'] ?? 0),
                ];
            })
            ->sort(function ($a, $b) use ($mode) {
                if ($mode === self::MODE_FAMILY) {
                    return $a['completeness'] <=> $b['completeness']
                        ?: $b['family_demand'] <=> $a['family_demand'];
                }

                return $b['demand_sum'] <=> $a['demand_sum']
                    ?: $b['family_demand'] <=> $a['family_demand'];
            })
            ->pluck('pcode')
            ->values();
    }

    private function periodKey(\DateTimeInterface $date): int
    {
        return (int) $date->format('Y') * 12 + (int) $date->format('n');
    }

    /**
     * @return Collection<int, object{master: string, name: string, demand_score: string|float}>
     */
    private function loadTopFamiliesByDemand(int $warehouseId, int $startKey, int $limit): Collection
    {
        return DB::table('warehouse_item_monthly_stats as w')
            ->join('items as i', 'w.item_id', '=', 'i.id')
            ->join('item_groups as ig', 'i.group_id', '=', 'ig.id')
            ->where('w.warehouse_id', $warehouseId)
            ->where('i.type', ItemType::ITEM->value)
            ->whereNull('i.deleted_at')
            ->whereNotNull('ig.master')
            ->whereRaw('(w.year * 12 + w.month) >= ?', [$startKey])
            ->groupBy('ig.master', 'ig.name')
            ->selectRaw('ig.master, ig.name, SUM(w.sold_qty - w.returned_qty) as demand_score')
            ->havingRaw('SUM(w.sold_qty - w.returned_qty) > 0')
            ->orderByDesc('demand_score')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, object{master: string, name: string, demand_score: string|float}>
     */
    private function loadFamiliesForCompletionMode(int $warehouseId, int $startKey, int $limit): Collection
    {
        return DB::table('warehouse_item_monthly_stats as w')
            ->join('items as i', 'w.item_id', '=', 'i.id')
            ->join('item_groups as ig', 'i.group_id', '=', 'ig.id')
            ->where('w.warehouse_id', $warehouseId)
            ->where('i.type', ItemType::ITEM->value)
            ->whereNull('i.deleted_at')
            ->whereNotNull('ig.master')
            ->whereRaw('(w.year * 12 + w.month) >= ?', [$startKey])
            ->groupBy('ig.master', 'ig.name')
            ->selectRaw('ig.master, ig.name, SUM(w.sold_qty - w.returned_qty) as demand_score')
            ->havingRaw('SUM(w.sold_qty - w.returned_qty) > 0')
            ->orderByDesc('demand_score')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  list<string>  $masters
     * @return array<int, string>
     */
    private function loadWarnaCodesForMasters(array $masters): array
    {
        if ($masters === []) {
            return [];
        }

        return DB::table('item_tag')
            ->join('tags', 'tags.id', '=', 'item_tag.tag_id')
            ->join('items as i', 'i.id', '=', 'item_tag.item_id')
            ->join('item_groups as ig', 'ig.id', '=', 'i.group_id')
            ->where('tags.type', Tag::TYPE_WARNA)
            ->whereIn('ig.master', $masters)
            ->whereNull('i.deleted_at')
            ->pluck('tags.code', 'i.id')
            ->mapWithKeys(fn ($code, $id) => [(int) $id => $code])
            ->all();
    }
}
