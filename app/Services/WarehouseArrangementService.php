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
    public const UI_MAX_FAMILIES = 50;

    public const UI_MAX_SUGGESTIONS = 300;

    public const EXPORT_MAX_FAMILIES = 200;

    public const EXPORT_MAX_SUGGESTIONS = 5000;

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
     * @return array{
     *     destination: Addrbook,
     *     demand_days: int,
     *     families: list<array<string, mixed>>,
     *     suggestions: list<array<string, mixed>>,
     *     truncated: bool,
     *     total_suggestion_count: int
     * }
     */
    public function buildSuggestions(
        int $destinationWarehouseId,
        int $demandDays = 365,
        int $maxFamilies = self::UI_MAX_FAMILIES,
        int $maxSuggestions = self::UI_MAX_SUGGESTIONS,
    ): array {
        $destination = Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->where('arrangement_enabled', true)
            ->findOrFail($destinationWarehouseId);

        $startKey = $this->periodKey(now()->subDays($demandDays));

        $familyRows = $this->loadTopFamiliesByDemand($destinationWarehouseId, $startKey, $maxFamilies);

        if ($familyRows->isEmpty()) {
            return [
                'destination' => $destination,
                'demand_days' => $demandDays,
                'families' => [],
                'suggestions' => [],
                'truncated' => false,
                'total_suggestion_count' => 0,
            ];
        }

        $sizeCodes = Tag::query()->where('type', Tag::TYPE_SIZE)->pluck('code', 'id');
        $warnaByItem = $this->loadWarnaCodesForMasters($familyRows->pluck('master')->all());

        $families = [];
        $suggestions = [];
        $truncated = false;
        $totalSuggestionCount = 0;

        foreach ($familyRows as $familyRow) {
            $master = $familyRow->master;
            $completeness = $this->familyCompleteness($master, $destinationWarehouseId);

            $families[] = [
                'master' => $master,
                'name' => $familyRow->name,
                'demand_score' => (float) $familyRow->demand_score,
                'completeness_pct' => $completeness['pct'],
                'present_count' => $completeness['present'],
                'total_count' => $completeness['total'],
            ];

            if (count($suggestions) >= $maxSuggestions) {
                $truncated = true;
                continue;
            }

            $items = DB::table('items as i')
                ->join('item_groups as ig', 'i.group_id', '=', 'ig.id')
                ->where('i.type', ItemType::ITEM->value)
                ->where('ig.master', $master)
                ->whereNull('i.deleted_at')
                ->select('i.id', 'i.code', 'i.name', 'i.size')
                ->get();

            if ($items->isEmpty()) {
                continue;
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

            $familyMeta = end($families);

            foreach ($items as $item) {
                $itemId = (int) $item->id;
                if (($destStock[$itemId] ?? 0) > 0) {
                    continue;
                }

                $sources = $sourcesByItem->get($itemId, collect());
                if ($sources->isEmpty()) {
                    continue;
                }

                $itemDemandVal = max(0.0, (float) ($itemDemand[$itemId] ?? 0));

                foreach ($sources as $source) {
                    $totalSuggestionCount++;

                    if (count($suggestions) >= $maxSuggestions) {
                        $truncated = true;
                        continue;
                    }

                    $sourceStockQty = (float) $source->quantity;
                    $fromId = (int) $source->warehouse_id;
                    $fromName = $source->warehouse_name ?? 'Unknown';

                    $suggestions[] = [
                        'master' => $master,
                        'master_name' => $familyMeta['name'],
                        'family_demand_score' => $familyMeta['demand_score'],
                        'completeness_pct' => $familyMeta['completeness_pct'],
                        'item_id' => $itemId,
                        'item_code' => $item->code,
                        'item_name' => $item->name,
                        'warna' => $warnaByItem[$itemId] ?? '-',
                        'size' => $sizeCodes[$item->size] ?? '-',
                        'item_demand' => $itemDemandVal,
                        'from_warehouse_id' => $fromId,
                        'from_warehouse_name' => $fromName,
                        'to_warehouse_id' => $destinationWarehouseId,
                        'to_warehouse_name' => $destination->name,
                        'source_stock' => (int) $sourceStockQty,
                        'suggested_qty' => max(1, min((int) $sourceStockQty, (int) ceil($itemDemandVal) ?: 1)),
                    ];
                }
            }
        }

        return [
            'destination' => $destination,
            'demand_days' => $demandDays,
            'families' => $families,
            'suggestions' => $suggestions,
            'truncated' => $truncated,
            'total_suggestion_count' => $totalSuggestionCount,
        ];
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
     * @return array{total: int, present: int, pct: float}
     */
    private function familyCompleteness(string $master, int $warehouseId): array
    {
        $total = DB::table('items as i')
            ->join('item_groups as ig', 'i.group_id', '=', 'ig.id')
            ->where('i.type', ItemType::ITEM->value)
            ->where('ig.master', $master)
            ->whereNull('i.deleted_at')
            ->count();

        $present = DB::table('items as i')
            ->join('item_groups as ig', 'i.group_id', '=', 'ig.id')
            ->join('warehouse_items as wi', 'wi.item_id', '=', 'i.id')
            ->where('i.type', ItemType::ITEM->value)
            ->where('ig.master', $master)
            ->where('wi.warehouse_id', $warehouseId)
            ->where('wi.quantity', '>', 0)
            ->whereNull('i.deleted_at')
            ->distinct()
            ->count('i.id');

        return [
            'total' => $total,
            'present' => $present,
            'pct' => $total > 0 ? round($present / $total * 100, 1) : 0.0,
        ];
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
