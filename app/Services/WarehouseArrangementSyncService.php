<?php

namespace App\Services;

use App\Enums\AddrbookType;
use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Tag;
use App\Models\WarehouseArrangementCandidate;
use App\Models\WarehouseArrangementCandidateSource;
use App\Models\WarehouseArrangementPcodeSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WarehouseArrangementSyncService
{
    public const MAX_FAMILIES = 200;

    public const MAX_SOURCE_SLOTS = 3;

    private const SIZE_ORDER = ['S', 'M', 'L', 'XL', '2L', 'XXL'];

    public function arrangementTablesExist(): bool
    {
        foreach ([
            'warehouse_arrangement_sources',
            'warehouse_arrangement_pcode_snapshots',
            'warehouse_arrangement_candidates',
            'warehouse_arrangement_candidate_sources',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{destinations: int, candidates: int, sources: int}
     */
    public function syncAll(?int $destinationId = null): array
    {
        $destCount = $this->syncDestinations($destinationId);
        $sourceCount = $this->syncSources($destinationId);

        return [
            'destinations' => $destCount,
            'candidates' => WarehouseArrangementCandidate::query()
                ->when($destinationId, fn ($q) => $q->where('destination_warehouse_id', $destinationId))
                ->count(),
            'sources' => $sourceCount,
        ];
    }

    public function syncDestinations(?int $destinationId = null): int
    {
        if ($destinationId === null) {
            $this->purgeStaleDestinationCaches();
        }

        $destinations = Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->where('arrangement_enabled', true)
            ->when($destinationId, fn ($q) => $q->where('id', $destinationId))
            ->get(['id']);

        $synced = 0;

        foreach ($destinations as $destination) {
            $synced += $this->syncDestination((int) $destination->id);
        }

        return $synced;
    }

    public function syncSources(?int $destinationId = null): int
    {
        $destinations = Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->where('arrangement_enabled', true)
            ->when($destinationId, fn ($q) => $q->where('id', $destinationId))
            ->get(['id']);

        $attached = 0;

        foreach ($destinations as $destination) {
            $attached += $this->syncSourcesForDestination((int) $destination->id);
        }

        return $attached;
    }

    /**
     * @return list<int>
     */
    private function activeSourceWarehouseIdsForDestination(int $destinationWarehouseId): array
    {
        return DB::table('warehouse_arrangement_sources as was')
            ->join('customers as a', 'a.id', '=', 'was.source_warehouse_id')
            ->where('was.destination_warehouse_id', $destinationWarehouseId)
            ->where('a.type', AddrbookType::Warehouse->value)
            ->whereNull('a.deleted_at')
            ->pluck('was.source_warehouse_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function purgeStaleDestinationCaches(): void
    {
        $activeIds = Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->where('arrangement_enabled', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $staleQuery = WarehouseArrangementPcodeSnapshot::query()->distinct();

        if ($activeIds !== []) {
            $staleQuery->whereNotIn('destination_warehouse_id', $activeIds);
        }

        foreach ($staleQuery->pluck('destination_warehouse_id')->map(fn ($id) => (int) $id)->all() as $staleId) {
            $this->clearDestinationCache($staleId);
        }
    }

    private function syncDestination(int $destinationWarehouseId): int
    {
        $now = now();
        $startKey365 = $this->periodKey(now()->subDays(365));

        $familyRows = DB::table('warehouse_item_monthly_stats as w')
            ->join('items as i', 'w.item_id', '=', 'i.id')
            ->join('item_group as ig', 'i.group_id', '=', 'ig.id')
            ->where('w.warehouse_id', $destinationWarehouseId)
            ->where(function ($query) {
                $query->where('w.item_type', ItemType::ITEM->value)
                    ->orWhereNull('w.item_type');
            })
            ->whereNull('i.deleted_at')
            ->whereNotNull('ig.master')
            ->whereRaw('(w.year * 12 + w.month) >= ?', [$startKey365])
            ->groupBy('ig.master', 'ig.name')
            ->selectRaw('ig.master, ig.name, SUM(w.sold_qty - w.returned_qty) as demand_score')
            ->havingRaw('SUM(w.sold_qty - w.returned_qty) > 0')
            ->orderByDesc('demand_score')
            ->limit(self::MAX_FAMILIES)
            ->get();

        if ($familyRows->isEmpty()) {
            $this->clearDestinationCache($destinationWarehouseId);

            return 0;
        }

        $masters = $familyRows->pluck('master')->all();
        $familyMeta = $familyRows->keyBy('master');
        $sizeCodes = Tag::query()->where('type', Tag::TYPE_SIZE)->pluck('code', 'id');
        $warnaByItem = $this->loadWarnaCodesForMasters($masters);

        $items = DB::table('items as i')
            ->join('item_group as ig', 'i.group_id', '=', 'ig.id')
            ->whereIn('ig.master', $masters)
            ->whereNull('i.deleted_at')
            ->select('i.id', 'i.code', 'i.name', 'i.size', 'i.pcode', 'ig.master', 'ig.name as group_name')
            ->get();

        if ($items->isEmpty()) {
            $this->clearDestinationCache($destinationWarehouseId);

            return 0;
        }

        $itemIds = $items->pluck('id')->map(fn ($id) => (int) $id)->all();

        $destStock = DB::table('warehouse_item')
            ->where('warehouse_id', $destinationWarehouseId)
            ->whereIn('item_id', $itemIds)
            ->where('quantity', '>', 0)
            ->pluck('quantity', 'item_id');

        $demandByItem = $this->loadDemandWindows($destinationWarehouseId, $itemIds);

        $missingItemIds = [];
        $pcodeMeta = [];
        $candidateCount = 0;

        foreach ($items as $item) {
            $itemId = (int) $item->id;
            $pcode = strtoupper(trim($item->pcode ?? ''));
            if ($pcode === '') {
                $pcode = strtoupper(trim($item->master ?? ''));
            }

            $sizeCode = $sizeCodes[$item->size] ?? '-';
            $familyRow = $familyMeta->get($item->master);

            $pcodeMeta[$pcode] ??= [
                'master' => $item->master,
                'master_name' => $familyRow->name ?? $item->group_name ?? $pcode,
                'warna' => '-',
                'sizes' => [],
                'present' => 0,
                'total' => 0,
            ];

            if ($sizeCode && $sizeCode !== '-') {
                $pcodeMeta[$pcode]['sizes'][] = $sizeCode;
            }
            $pcodeMeta[$pcode]['total']++;
            if (($destStock[$itemId] ?? 0) > 0) {
                $pcodeMeta[$pcode]['present']++;
            }

            if (($destStock[$itemId] ?? 0) > 0) {
                continue;
            }

            $missingItemIds[] = $itemId;
            $demands = $demandByItem[$itemId] ?? ['30' => 0, '90' => 0, '180' => 0, '365' => 0];
            $warna = $warnaByItem[$itemId] ?? '-';
            $pcodeMeta[$pcode]['warna'] = $warna;

            WarehouseArrangementCandidate::query()->updateOrCreate(
                [
                    'destination_warehouse_id' => $destinationWarehouseId,
                    'item_id' => $itemId,
                ],
                [
                    'pcode' => $pcode,
                    'master' => $item->master,
                    'item_code' => $item->code,
                    'item_name' => $item->name,
                    'size_code' => $sizeCode,
                    'warna' => $warna,
                    'demand_30' => $demands['30'],
                    'demand_90' => $demands['90'],
                    'demand_180' => $demands['180'],
                    'demand_365' => $demands['365'],
                    'synced_at' => $now,
                ],
            );
            $candidateCount++;
        }

        WarehouseArrangementCandidate::query()
            ->where('destination_warehouse_id', $destinationWarehouseId)
            ->whereNotIn('item_id', $missingItemIds)
            ->delete();

        $snapshotPcodes = array_keys($pcodeMeta);
        WarehouseArrangementPcodeSnapshot::query()
            ->where('destination_warehouse_id', $destinationWarehouseId)
            ->when($snapshotPcodes !== [], fn ($q) => $q->whereNotIn('pcode', $snapshotPcodes))
            ->delete();

        foreach ($pcodeMeta as $pcode => $meta) {
            $sizes = $this->sortSizeCodes(collect($meta['sizes'])->unique()->values());
            $total = (int) $meta['total'];
            $present = (int) $meta['present'];
            $pct = $total > 0 ? round($present / $total * 100, 1) : 0.0;
            $master = $meta['master'];
            $familyDemand = (float) ($familyMeta->get($master)->demand_score ?? 0);

            WarehouseArrangementPcodeSnapshot::query()->updateOrCreate(
                [
                    'destination_warehouse_id' => $destinationWarehouseId,
                    'pcode' => $pcode,
                ],
                [
                    'master' => $master,
                    'master_name' => $meta['master_name'],
                    'warna' => $meta['warna'],
                    'present_count' => $present,
                    'total_count' => $total,
                    'completeness_pct' => $pct,
                    'family_demand_365' => $familyDemand,
                    'sizes' => $sizes,
                    'synced_at' => $now,
                ],
            );
        }

        return $candidateCount;
    }

    private function syncSourcesForDestination(int $destinationWarehouseId): int
    {
        $sourceIds = $this->activeSourceWarehouseIdsForDestination($destinationWarehouseId);

        if ($sourceIds === []) {
            WarehouseArrangementCandidateSource::query()
                ->whereIn('candidate_id', WarehouseArrangementCandidate::query()
                    ->where('destination_warehouse_id', $destinationWarehouseId)
                    ->pluck('id'))
                ->delete();

            return 0;
        }

        $candidates = WarehouseArrangementCandidate::query()
            ->where('destination_warehouse_id', $destinationWarehouseId)
            ->get(['id', 'item_id', 'demand_365']);

        if ($candidates->isEmpty()) {
            return 0;
        }

        $itemIds = $candidates->pluck('item_id')->all();

        $stockRows = DB::table('warehouse_item as wi')
            ->join('customers as a', 'a.id', '=', 'wi.warehouse_id')
            ->whereIn('wi.item_id', $itemIds)
            ->whereIn('wi.warehouse_id', $sourceIds)
            ->where('wi.quantity', '>', 0)
            ->where('a.type', AddrbookType::Warehouse->value)
            ->whereNull('a.deleted_at')
            ->orderByDesc('wi.quantity')
            ->get(['wi.warehouse_id', 'wi.item_id', 'wi.quantity', 'a.name as warehouse_name']);

        $byItem = $stockRows->groupBy(fn ($row) => (int) $row->item_id);
        $attached = 0;
        $candidateIdsWithSources = [];

        foreach ($candidates as $candidate) {
            $sources = $byItem->get((int) $candidate->item_id, collect())
                ->take(self::MAX_SOURCE_SLOTS);

            $existingSourceIds = WarehouseArrangementCandidateSource::query()
                ->where('candidate_id', $candidate->id)
                ->pluck('source_warehouse_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $newSourceIds = $sources->pluck('warehouse_id')->map(fn ($id) => (int) $id)->all();

            WarehouseArrangementCandidateSource::query()
                ->where('candidate_id', $candidate->id)
                ->whereNotIn('source_warehouse_id', $newSourceIds)
                ->delete();

            if ($sources->isEmpty()) {
                continue;
            }

            $candidateIdsWithSources[] = $candidate->id;
            $demand365 = (float) $candidate->demand_365;

            foreach ($sources as $source) {
                $sourceStock = (int) $source->quantity;
                $qtyCap = max(1, (int) ceil($demand365));

                WarehouseArrangementCandidateSource::query()->updateOrCreate(
                    [
                        'candidate_id' => $candidate->id,
                        'source_warehouse_id' => (int) $source->warehouse_id,
                    ],
                    [
                        'source_stock' => $sourceStock,
                        'suggested_qty' => max(1, min($sourceStock, $qtyCap)),
                    ],
                );
                $attached++;
            }
        }

        $staleCandidates = WarehouseArrangementCandidate::query()
            ->where('destination_warehouse_id', $destinationWarehouseId);

        if ($candidateIdsWithSources === []) {
            $staleCandidates->delete();
        } else {
            $staleCandidates->whereNotIn('id', $candidateIdsWithSources)->delete();
        }

        return $attached;
    }

    private function clearDestinationCache(int $destinationWarehouseId): void
    {
        WarehouseArrangementCandidate::query()
            ->where('destination_warehouse_id', $destinationWarehouseId)
            ->delete();
        WarehouseArrangementPcodeSnapshot::query()
            ->where('destination_warehouse_id', $destinationWarehouseId)
            ->delete();
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, array{30: float, 90: float, 180: float, 365: float}>
     */
    private function loadDemandWindows(int $warehouseId, array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $windows = [
            30 => $this->periodKey(now()->subDays(30)),
            90 => $this->periodKey(now()->subDays(90)),
            180 => $this->periodKey(now()->subDays(180)),
            365 => $this->periodKey(now()->subDays(365)),
        ];

        $rows = DB::table('warehouse_item_monthly_stats')
            ->where('warehouse_id', $warehouseId)
            ->whereIn('item_id', $itemIds)
            ->whereRaw('(year * 12 + month) >= ?', [$windows[365]])
            ->selectRaw('item_id, year, month, sold_qty, returned_qty')
            ->get();

        $result = [];
        foreach ($itemIds as $itemId) {
            $result[$itemId] = ['30' => 0.0, '90' => 0.0, '180' => 0.0, '365' => 0.0];
        }

        foreach ($rows as $row) {
            $itemId = (int) $row->item_id;
            $period = (int) $row->year * 12 + (int) $row->month;
            $net = max(0.0, (float) $row->sold_qty - (float) $row->returned_qty);

            foreach ($windows as $days => $startKey) {
                if ($period >= $startKey) {
                    $result[$itemId][$days] += $net;
                }
            }
        }

        return $result;
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
            ->join('item_group as ig', 'ig.id', '=', 'i.group_id')
            ->where('tags.type', Tag::TYPE_WARNA)
            ->whereIn('ig.master', $masters)
            ->whereNull('i.deleted_at')
            ->pluck('tags.code', 'i.id')
            ->mapWithKeys(fn ($code, $id) => [(int) $id => $code])
            ->all();
    }

  /**
     * @param  \Illuminate\Support\Collection<int, string>  $codes
     * @return list<string>
     */
    private function sortSizeCodes(Collection $codes): array
    {
        return $codes
            ->sortBy(fn (string $code) => $this->sizeSortKey($code))
            ->values()
            ->all();
    }

    private function sizeSortKey(string $code): string
    {
        $upper = strtoupper($code);
        $index = array_search($upper, self::SIZE_ORDER, true);

        if ($index !== false) {
            return '0_'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'_'.$upper;
        }

        return '1_'.$upper;
    }

    private function periodKey(\DateTimeInterface $date): int
    {
        return (int) $date->format('Y') * 12 + (int) $date->format('n');
    }
}
