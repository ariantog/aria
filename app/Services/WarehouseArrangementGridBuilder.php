<?php

namespace App\Services;

use App\Enums\ItemType;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

class WarehouseArrangementGridBuilder
{
    private const SIZE_ORDER = ['S', 'M', 'L', 'XL', '2L', 'XXL'];

    /**
     * @param  list<array<string, mixed>>  $suggestions
     * @return array{parents: list<array<string, mixed>>}
     */
    public function build(array $suggestions): array
    {
        if ($suggestions === []) {
            return ['parents' => []];
        }

        $grouped = collect($suggestions)->groupBy('pcode');
        $pcodeList = $grouped->keys()->values()->all();
        $destinationWarehouseId = (int) ($suggestions[0]['to_warehouse_id'] ?? 0);
        $allSizesByPcode = $this->loadAllSizesForPcodes($pcodeList);
        $itemMapByPcode = $this->loadItemMapForPcodes($pcodeList, $destinationWarehouseId);

        $parents = $grouped
            ->map(function ($items, $pcode) use ($allSizesByPcode, $itemMapByPcode) {
                $items = $items->values();
                $first = $items->first();
                $normalized = strtoupper(trim($pcode));
                $sizes = $allSizesByPcode[$normalized] ?? $this->orderedSizesFromSuggestions($items);
                $itemMap = $itemMapByPcode[$normalized] ?? [];
                $row = $this->buildSizeRow($items, $sizes, $itemMap);

                return [
                    'pcode' => $pcode,
                    'name' => $first['master_name'] ?? $pcode,
                    'warna' => $first['warna'] ?? '—',
                    'master' => $first['master'] ?? $pcode,
                    'family_demand_score' => $first['family_demand_score'] ?? 0,
                    'completeness_pct' => $first['completeness_pct'] ?? 0,
                    'present_count' => $first['present_count'] ?? 0,
                    'total_count' => $first['total_count'] ?? 0,
                    'to_warehouse_id' => $first['to_warehouse_id'],
                    'to_warehouse_name' => $first['to_warehouse_name'],
                    'sizes' => $sizes,
                    'rows' => [$row],
                ];
            })
            ->values()
            ->all();

        return ['parents' => $parents];
    }

    /**
     * @param  list<string>  $pcodes
     * @return array<string, list<string>>
     */
    private function loadAllSizesForPcodes(array $pcodes): array
    {
        if ($pcodes === []) {
            return [];
        }

        $normalized = collect($pcodes)
            ->map(fn (string $pcode) => strtoupper(trim($pcode)))
            ->unique()
            ->values()
            ->all();

        $sizeCodes = Tag::query()->where('type', Tag::TYPE_SIZE)->pluck('code', 'id');

        $placeholders = implode(',', array_fill(0, count($normalized), '?'));

        $rows = DB::table('items as i')
            ->where('i.type', ItemType::ITEM->value)
            ->whereNull('i.deleted_at')
            ->whereNotNull('i.pcode')
            ->whereRaw("UPPER(TRIM(i.pcode)) IN ({$placeholders})", $normalized)
            ->select('i.pcode', 'i.size')
            ->get();

        $byPcode = [];
        foreach ($rows as $row) {
            $key = strtoupper(trim($row->pcode ?? ''));
            if ($key === '') {
                continue;
            }

            $code = $sizeCodes[$row->size] ?? null;
            if (! $code || $code === '-') {
                continue;
            }

            $byPcode[$key] ??= [];
            $byPcode[$key][] = $code;
        }

        foreach ($byPcode as $key => $codes) {
            $byPcode[$key] = $this->sortSizeCodes(collect($codes)->unique()->values());
        }

        return $byPcode;
    }

    /**
     * @param  list<string>  $pcodes
     * @return array<string, array<string, array{item_id: int, dest_stock: int}>>
     */
    private function loadItemMapForPcodes(array $pcodes, int $destinationWarehouseId): array
    {
        if ($pcodes === [] || $destinationWarehouseId <= 0) {
            return [];
        }

        $normalized = collect($pcodes)
            ->map(fn (string $pcode) => strtoupper(trim($pcode)))
            ->unique()
            ->values()
            ->all();

        $sizeCodes = Tag::query()->where('type', Tag::TYPE_SIZE)->pluck('code', 'id');
        $placeholders = implode(',', array_fill(0, count($normalized), '?'));

        $rows = DB::table('items as i')
            ->leftJoin('warehouse_items as wi', function ($join) use ($destinationWarehouseId) {
                $join->on('wi.item_id', '=', 'i.id')
                    ->where('wi.warehouse_id', '=', $destinationWarehouseId);
            })
            ->where('i.type', ItemType::ITEM->value)
            ->whereNull('i.deleted_at')
            ->whereNotNull('i.pcode')
            ->whereRaw("UPPER(TRIM(i.pcode)) IN ({$placeholders})", $normalized)
            ->select('i.id', 'i.pcode', 'i.size', 'wi.quantity as dest_stock')
            ->get();

        $byPcode = [];
        foreach ($rows as $row) {
            $key = strtoupper(trim($row->pcode ?? ''));
            $code = $sizeCodes[$row->size] ?? null;
            if ($key === '' || ! $code || $code === '-') {
                continue;
            }

            $byPcode[$key][$code] = [
                'item_id' => (int) $row->id,
                'dest_stock' => (int) ($row->dest_stock ?? 0),
            ];
        }

        return $byPcode;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $items
     * @return list<string>
     */
    private function orderedSizesFromSuggestions($items): array
    {
        $codes = $items
            ->pluck('size')
            ->filter(fn ($size) => $size && $size !== '-')
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            return ['—'];
        }

        return $this->sortSizeCodes($codes);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $codes
     * @return list<string>
     */
    private function sortSizeCodes($codes): array
    {
        return $codes
            ->sortBy(fn (string $code) => $this->sizeSortKey($code))
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $items
     * @param  list<string>  $sizes
     * @param  array<string, array{item_id: int, dest_stock: int}>  $itemMap
     * @return array<string, mixed>
     */
    private function buildSizeRow($items, array $sizes, array $itemMap = []): array
    {
        $row = [
            'label' => 'Sizes',
            '_cells' => [],
        ];

        foreach ($sizes as $sizeCode) {
            $prefix = $this->fieldPrefix($sizeCode);
            $sku = $items->first(fn (array $item) => $this->matchesSize($item, $sizeCode));

            if ($sku) {
                $topSource = $sku['sources'][0] ?? null;

                $row['_cells'][$prefix] = [
                    'item_id' => $sku['item_id'],
                    'item_code' => $sku['item_code'],
                    'size_label' => $sizeCode,
                    'demand' => (float) ($sku['item_demand'] ?? 0),
                    'sources' => $sku['sources'],
                    'chosen_source_index' => 0,
                    'selected' => false,
                    'to_warehouse_id' => $sku['to_warehouse_id'],
                    'to_warehouse_name' => $sku['to_warehouse_name'],
                    'source_stock' => $topSource['source_stock'] ?? 0,
                    'move_qty' => $topSource['suggested_qty'] ?? 1,
                ];

                continue;
            }

            $meta = $itemMap[$sizeCode] ?? null;
            if (! $meta) {
                continue;
            }

            $row['_cells'][$prefix] = [
                'item_id' => $meta['item_id'],
                'size_label' => $sizeCode,
                'inactive' => true,
                'dest_stock' => $meta['dest_stock'],
            ];
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matchesSize(array $row, string $sizeCode): bool
    {
        $size = $row['size'] ?? '-';

        if ($sizeCode === '—') {
            return $size === '-' || $size === '';
        }

        return strtoupper($size) === strtoupper($sizeCode);
    }

    private function fieldPrefix(string $sizeCode): string
    {
        if ($sizeCode === '—') {
            return '';
        }

        return str_replace(['.', ' '], '_', strtolower($sizeCode)).'_';
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
}
