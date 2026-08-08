<?php

namespace App\Services;

class WarehouseArrangementGridBuilder
{
    private const SIZE_ORDER = ['S', 'M', 'L', 'XL', 'XXL'];

    /**
     * @param  list<array<string, mixed>>  $suggestions
     * @return array{parents: list<array<string, mixed>>}
     */
    public function build(array $suggestions): array
    {
        if ($suggestions === []) {
            return ['parents' => []];
        }

        $parents = collect($suggestions)
            ->groupBy('pcode')
            ->map(function ($items, $pcode) {
                $items = $items->values();
                $first = $items->first();
                $sizes = $this->orderedSizes($items);
                $row = $this->buildSizeRow($items, $sizes);

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
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $items
     * @return list<string>
     */
    private function orderedSizes($items): array
    {
        $codes = $items
            ->pluck('size')
            ->filter(fn ($size) => $size && $size !== '-')
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            return ['—'];
        }

        return $codes
            ->sortBy(fn (string $code) => $this->sizeSortKey($code))
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $items
     * @param  list<string>  $sizes
     * @return array<string, mixed>
     */
    private function buildSizeRow($items, array $sizes): array
    {
        $row = [
            'label' => 'Sizes',
            '_cells' => [],
        ];

        foreach ($sizes as $sizeCode) {
            $sku = $items->first(fn (array $item) => $this->matchesSize($item, $sizeCode));

            if (! $sku) {
                continue;
            }

            $prefix = $this->fieldPrefix($sizeCode);
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
