<?php

namespace App\Services;

use App\Enums\AddrbookType;
use App\Models\Addrbook;
use App\Models\WarehouseArrangementCandidate;
use App\Models\WarehouseArrangementPcodeSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WarehouseArrangementService
{
    public const MODE_DEMAND = 'demand';

    public const MODE_FAMILY = 'family';

    public const PER_PAGE = 30;

    public const FAMILY_COMPLETENESS_THRESHOLD = 75.0;

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
     *     sections: list<array<string, mixed>>,
     *     synced_at: ?\Carbon\CarbonInterface,
     *     stale: bool
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
        $demandDays = $this->normalizeDemandDays($demandDays);

        $destination = Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->where('arrangement_enabled', true)
            ->findOrFail($destinationWarehouseId);

        $demandColumn = $this->demandColumn($demandDays);
        $excludeSet = array_flip(array_map('intval', $excludeItemIds));

        $candidateQuery = WarehouseArrangementCandidate::query()
            ->where('destination_warehouse_id', $destinationWarehouseId)
            ->whereHas('sources')
            ->when($excludeItemIds !== [], fn ($q) => $q->whereNotIn('item_id', $excludeItemIds));

        if ($mode === self::MODE_DEMAND) {
            $candidateQuery->where($demandColumn, '>', 0);
        }

        $eligiblePcodes = $candidateQuery
            ->distinct()
            ->pluck('pcode')
            ->filter()
            ->values();

        $snapshots = WarehouseArrangementPcodeSnapshot::query()
            ->where('destination_warehouse_id', $destinationWarehouseId)
            ->whereIn('pcode', $eligiblePcodes)
            ->get()
            ->keyBy('pcode');

        if ($mode === self::MODE_FAMILY) {
            $eligiblePcodes = $eligiblePcodes->filter(function (string $pcode) use ($snapshots) {
                $snap = $snapshots->get($pcode);

                return $snap && (float) $snap->completeness_pct < self::FAMILY_COMPLETENESS_THRESHOLD;
            })->values();
        }

        if ($search !== '') {
            $needle = strtoupper($search);
            $eligiblePcodes = $eligiblePcodes->filter(fn (string $pcode) => str_contains(strtoupper($pcode), $needle))->values();
        }

        $sortedPcodes = $this->sortPcodes($eligiblePcodes, $snapshots, $candidateQuery->clone(), $mode, $demandColumn);

        $totalPcodes = $sortedPcodes->count();
        $pagePcodes = $sortedPcodes->slice(($page - 1) * $perPage, $perPage)->values();

        $candidates = WarehouseArrangementCandidate::query()
            ->with(['sources.sourceWarehouse'])
            ->where('destination_warehouse_id', $destinationWarehouseId)
            ->whereIn('pcode', $pagePcodes->all())
            ->whereHas('sources')
            ->when($excludeItemIds !== [], fn ($q) => $q->whereNotIn('item_id', $excludeItemIds))
            ->when($mode === self::MODE_DEMAND, fn ($q) => $q->where($demandColumn, '>', 0))
            ->get();

        if ($mode === self::MODE_FAMILY) {
            $candidates = $candidates->filter(function (WarehouseArrangementCandidate $c) use ($snapshots) {
                $snap = $snapshots->get($c->pcode);

                return $snap && (float) $snap->completeness_pct < self::FAMILY_COMPLETENESS_THRESHOLD;
            });
        }

        $stockedByPcode = $this->loadStockedSizes($destinationWarehouseId, $pagePcodes->all());

        $suggestions = [];
        $sections = [];

        foreach ($pagePcodes as $pcode) {
            $snap = $snapshots->get($pcode);
            if (! $snap) {
                continue;
            }

            $pcodeCandidates = $candidates->where('pcode', $pcode);
            $stocked = $stockedByPcode[$pcode] ?? [];
            $sizes = $this->resolveSectionSizes($snap, $pcodeCandidates, $stocked);
            $cells = [];

            foreach ($sizes as $sizeCode) {
                $candidate = $pcodeCandidates->first(fn (WarehouseArrangementCandidate $c) => $this->candidateMatchesSize($c, $sizeCode));

                if ($candidate) {
                    $itemDemand = $candidate->demandForDays($demandDays);
                    $sources = $candidate->sources
                        ->sortByDesc('source_stock')
                        ->values()
                        ->map(fn ($src) => [
                            'from_warehouse_id' => (int) $src->source_warehouse_id,
                            'from_warehouse_name' => $src->sourceWarehouse?->name ?? 'Unknown',
                            'source_stock' => (int) $src->source_stock,
                            'suggested_qty' => $this->suggestedQty($mode, $itemDemand, (int) $src->source_stock),
                        ])
                        ->all();

                    $cells[$sizeCode] = [
                        'item_id' => $candidate->item_id,
                        'item_code' => $candidate->item_code,
                        'demand' => $itemDemand,
                        'sources' => $sources,
                        'moveable' => true,
                    ];

                    $suggestions[] = $this->candidateToSuggestion($candidate, $destination, $snap, $itemDemand, $sources);

                    continue;
                }

                $stockedCell = $stocked[$sizeCode] ?? null;
                if ($stockedCell) {
                    $cells[$sizeCode] = [
                        'item_id' => $stockedCell['item_id'],
                        'dest_stock' => $stockedCell['dest_stock'],
                        'moveable' => false,
                    ];
                }
            }

            $sections[] = [
                'pcode' => $pcode,
                'name' => $snap->master_name ?? $pcode,
                'warna' => $snap->warna ?? '—',
                'family_demand_score' => (float) $snap->family_demand_365,
                'completeness_pct' => (float) $snap->completeness_pct,
                'present_count' => (int) $snap->present_count,
                'total_count' => (int) $snap->total_count,
                'sizes' => $sizes,
                'cells' => $cells,
            ];
        }

        $syncedAt = $snapshots->max('synced_at');

        return [
            'destination' => $destination,
            'demand_days' => $demandDays,
            'mode' => $mode,
            'page' => $page,
            'per_page' => $perPage,
            'total_pcodes' => $totalPcodes,
            'search' => $search,
            'suggestions' => $suggestions,
            'sections' => $sections,
            'synced_at' => $syncedAt,
            'stale' => $syncedAt === null || $syncedAt->lt(now()->subDay()),
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
        $allSuggestions = [];
        $page = 1;
        $perPage = 500;
        $lastPage = 1;

        do {
            $result = $this->buildPage(
                $destinationWarehouseId,
                $demandDays,
                $mode,
                $page,
                $perPage,
                '',
                $excludeItemIds,
            );
            $allSuggestions = array_merge($allSuggestions, $result['suggestions']);
            $lastPage = $result['total_pcodes'] > 0 ? (int) ceil($result['total_pcodes'] / $perPage) : 1;
            $destination = $result['destination'];
            $page++;
        } while ($page <= $lastPage);

        return [
            'destination' => $destination,
            'suggestions' => $allSuggestions,
        ];
    }

    private function normalizeDemandDays(int $days): int
    {
        return in_array($days, [30, 90, 180, 365], true) ? $days : 365;
    }

    private function demandColumn(int $demandDays): string
    {
        return match ($demandDays) {
            30 => 'demand_30',
            90 => 'demand_90',
            180 => 'demand_180',
            default => 'demand_365',
        };
    }

    private function candidateMatchesSize(WarehouseArrangementCandidate $candidate, string $sizeCode): bool
    {
        $itemSize = $candidate->size_code ?? '-';

        if ($sizeCode === '—') {
            return $itemSize === '-' || $itemSize === '';
        }

        return strtoupper($itemSize) === strtoupper($sizeCode);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, WarehouseArrangementCandidate>  $pcodeCandidates
     * @param  array<string, array{item_id: int, dest_stock: int}>  $stocked
     * @return list<string>
     */
    private function resolveSectionSizes(
        WarehouseArrangementPcodeSnapshot $snap,
        $pcodeCandidates,
        array $stocked,
    ): array {
        $codes = collect($snap->sizes ?? [])
            ->merge($pcodeCandidates->pluck('size_code'))
            ->merge(array_keys($stocked))
            ->filter(fn ($code) => $code && $code !== '-')
            ->unique();

        if ($codes->isEmpty()) {
            return ['—'];
        }

        return $this->sortSizeCodes($codes);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $codes
     * @return list<string>
     */
    private function sortSizeCodes(Collection $codes): array
    {
        $order = ['S', 'M', 'L', 'XL', '2L', 'XXL'];

        return $codes
            ->sortBy(function (string $code) {
                $order = ['S', 'M', 'L', 'XL', '2L', 'XXL'];
                $upper = strtoupper($code);
                $index = array_search($upper, $order, true);

                if ($index !== false) {
                    return '0_'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'_'.$upper;
                }

                return '1_'.$upper;
            })
            ->values()
            ->all();
    }

    private function suggestedQty(string $mode, float $itemDemand, int $sourceStock): int
    {
        $cap = $mode === self::MODE_DEMAND ? max(1, (int) ceil($itemDemand)) : 1;

        return max(1, min($sourceStock, $cap));
    }

    /**
     * @param  Collection<int, string>  $pcodes
     * @param  Collection<string, WarehouseArrangementPcodeSnapshot>  $snapshots
     */
    private function sortPcodes(
        Collection $pcodes,
        Collection $snapshots,
        \Illuminate\Database\Eloquent\Builder $candidateBase,
        string $mode,
        string $demandColumn,
    ): Collection {
        $demandSums = $candidateBase
            ->selectRaw('pcode, SUM('.$demandColumn.') as demand_sum')
            ->groupBy('pcode')
            ->pluck('demand_sum', 'pcode');

        return $pcodes
            ->map(function (string $pcode) use ($snapshots, $demandSums, $mode) {
                $snap = $snapshots->get($pcode);

                return [
                    'pcode' => $pcode,
                    'demand_sum' => (float) ($demandSums[$pcode] ?? 0),
                    'completeness' => (float) ($snap->completeness_pct ?? 0),
                    'family_demand' => (float) ($snap->family_demand_365 ?? 0),
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

    /**
     * @param  list<string>  $pcodes
     * @return array<string, array<string, array{item_id: int, dest_stock: int}>>
     */
    private function loadStockedSizes(int $destinationWarehouseId, array $pcodes): array
    {
        if ($pcodes === []) {
            return [];
        }

        $rows = DB::table('items as i')
            ->join('warehouse_items as wi', 'wi.item_id', '=', 'i.id')
            ->join('tags as t', 't.id', '=', 'i.size')
            ->where('wi.warehouse_id', $destinationWarehouseId)
            ->where('wi.quantity', '>', 0)
            ->whereIn(DB::raw('UPPER(TRIM(i.pcode))'), $pcodes)
            ->whereNull('i.deleted_at')
            ->select('i.id', 'i.pcode', 't.code as size_code', 'wi.quantity')
            ->get();

        $byPcode = [];
        foreach ($rows as $row) {
            $pcode = strtoupper(trim($row->pcode ?? ''));
            $size = $row->size_code ?? '-';
            if ($pcode === '' || $size === '-') {
                continue;
            }
            $byPcode[$pcode][$size] = [
                'item_id' => (int) $row->id,
                'dest_stock' => (int) $row->quantity,
            ];
        }

        return $byPcode;
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     */
    private function candidateToSuggestion(
        WarehouseArrangementCandidate $candidate,
        Addrbook $destination,
        WarehouseArrangementPcodeSnapshot $snap,
        float $itemDemand,
        array $sources,
    ): array {
        return [
            'master' => $candidate->master ?? $snap->master,
            'pcode' => $candidate->pcode,
            'master_name' => $snap->master_name,
            'family_demand_score' => (float) $snap->family_demand_365,
            'completeness_pct' => (float) $snap->completeness_pct,
            'present_count' => (int) $snap->present_count,
            'total_count' => (int) $snap->total_count,
            'item_id' => $candidate->item_id,
            'item_code' => $candidate->item_code,
            'item_name' => $candidate->item_name,
            'warna' => $candidate->warna,
            'size' => $candidate->size_code,
            'item_demand' => $itemDemand,
            'sources' => $sources,
            'to_warehouse_id' => $destination->id,
            'to_warehouse_name' => $destination->name,
        ];
    }
}
