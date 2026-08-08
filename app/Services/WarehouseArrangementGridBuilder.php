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
            ->groupBy('master')
            ->map(function ($items, $master) {
                $items = $items->values();
                $first = $items->first();
                $sizes = $this->orderedSizes($items);

                return [
                    'pcode' => $master,
                    'name' => $first['master_name'] ?? $master,
                    'family_demand_score' => $first['family_demand_score'] ?? 0,
                    'completeness_pct' => $first['completeness_pct'] ?? 0,
                    'to_warehouse_id' => $first['to_warehouse_id'],
                    'to_warehouse_name' => $first['to_warehouse_name'],
                    'sizes' => $sizes,
                    'rows' => $this->buildColorRows($items, $sizes),
                ];
            })
            ->sortBy('pcode')
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
     * @return list<array<string, mixed>>
     */
    private function buildColorRows($items, array $sizes): array
    {
        return $items
            ->groupBy(fn (array $row) => $this->colorGroupKey($row))
            ->map(function ($colorItems, $colorKey) use ($sizes) {
                $first = $colorItems->first();
                $row = [
                    'color_key' => $colorKey,
                    'color_name' => $first['warna'] ?? '—',
                    '_meta' => [],
                ];

                $demandTotal = 0;
                $sourceTotal = 0;
                $moveTotal = 0;

                foreach ($sizes as $sizeCode) {
                    $sku = $colorItems->first(fn (array $item) => $this->matchesSize($item, $sizeCode));

                    if (! $sku) {
                        continue;
                    }

                    $prefix = $this->fieldPrefix($sizeCode);
                    $topSource = $sku['sources'][0] ?? null;

                    $row["{$prefix}demand"] = (float) ($sku['item_demand'] ?? 0);
                    $row["{$prefix}source_stock"] = $topSource['source_stock'] ?? 0;
                    $row["{$prefix}move_qty"] = $topSource['suggested_qty'] ?? 0;

                    $row['_meta'][$prefix] = [
                        'item_id' => $sku['item_id'],
                        'item_code' => $sku['item_code'],
                        'size_label' => $sizeCode,
                        'sources' => $sku['sources'],
                        'chosen_source_index' => 0,
                        'to_warehouse_id' => $sku['to_warehouse_id'],
                        'to_warehouse_name' => $sku['to_warehouse_name'],
                    ];

                    $demandTotal += (int) $row["{$prefix}demand"];
                    $sourceTotal += (int) $row["{$prefix}source_stock"];
                    $moveTotal += (int) $row["{$prefix}move_qty"];
                }

                if (count($sizes) > 1) {
                    $row['demand_total'] = $demandTotal;
                    $row['source_stock_total'] = $sourceTotal;
                    $row['move_qty_total'] = $moveTotal;
                }

                return $row;
            })
            ->sortBy('color_name')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function colorGroupKey(array $row): string
    {
        $warna = $row['warna'] ?? '—';

        return $warna !== '-' && $warna !== '' ? strtoupper($warna) : 'none';
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
