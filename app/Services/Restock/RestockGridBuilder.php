<?php

namespace App\Services\Restock;

use App\Models\Item;
use App\Models\RestockCell;
use App\Models\RestockSheet;
use App\Services\Items\ItemIdentityBuilder;
use App\Services\Restock\RestockSettingsService;
use Illuminate\Support\Collection;

class RestockGridBuilder
{
    private const SIZE_ORDER = ['S', 'M', 'L', 'XL', 'XXL'];

    public function __construct(
        protected ItemIdentityBuilder $identityBuilder,
        protected RestockSettingsService $settingsService,
    ) {}

    /**
     * @return array{sheet_id: int, parents: list<array{pcode: string, name: string, sizes: list<string>, rows: list<array<string, mixed>>}>}
     */
    public function build(RestockSheet $sheet): array
    {
        $sheet->loadMissing([
            'cells.color',
            'cells.size',
            'cells.item.group',
            'cells.item.tags',
            'cells.item.warehouseItems',
        ]);

        $parents = $this->cellsGroupedByParent($sheet)
            ->map(function (Collection $cells, string $parentPcode) {
                $sizes = $this->orderedSizeCodes($cells);
                $rows = $this->buildColorRows($cells, $sizes);

                return [
                    'pcode' => $parentPcode,
                    'name' => $this->parentDisplayName($cells, $parentPcode),
                    'sizes' => $sizes,
                    'rows' => $rows,
                ];
            })
            ->sortBy('pcode')
            ->values()
            ->all();

        return [
            'sheet_id' => $sheet->id,
            'parents' => $parents,
        ];
    }

    /**
     * @return Collection<string, Collection<int, RestockCell>>
     */
    protected function cellsGroupedByParent(RestockSheet $sheet): Collection
    {
        return $sheet->cells
            ->filter(fn (RestockCell $cell) => $cell->item !== null)
            ->groupBy(fn (RestockCell $cell) => $this->identityBuilder->assetLancarParentPcode($cell->item));
    }

    /**
     * @param  Collection<int, RestockCell>  $cells
     */
    protected function parentDisplayName(Collection $cells, string $parentPcode): string
    {
        $names = $cells
            ->map(fn (RestockCell $cell) => $cell->item?->group?->name)
            ->filter()
            ->unique()
            ->values();

        $preferred = $names->first(
            fn (?string $name) => $name && strtoupper(trim($name)) !== strtoupper($parentPcode)
        );

        return $preferred ?? $names->first() ?? $parentPcode;
    }

    /**
     * @param  Collection<int, RestockCell>  $cells
     * @return list<string>
     */
    protected function orderedSizeCodes(Collection $cells): array
    {
        $codes = $cells
            ->map(fn (RestockCell $cell) => $this->cellSizeCode($cell))
            ->filter()
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
     * @param  Collection<int, RestockCell>  $cells
     * @param  list<string>  $sizes
     * @return list<array<string, mixed>>
     */
    protected function buildColorRows(Collection $cells, array $sizes): array
    {
        return $cells
            ->groupBy(fn (RestockCell $cell) => $this->colorGroupKey($cell))
            ->map(function (Collection $colorCells) use ($sizes) {
                $first = $colorCells->first();
                $item = $first->item;
                $row = [
                    'color_id' => $first->color_id,
                    'color_name' => $first->color?->name
                        ?? ($item ? $this->identityBuilder->assetLancarColorLabel($item) : '—'),
                    'is_urgent' => $colorCells->contains(fn (RestockCell $c) => $c->is_urgent),
                    '_meta' => [],
                ];

                $restockTotal = 0;
                $productionTotal = 0;
                $shippedTotal = 0;
                $stockTotal = 0;

                foreach ($sizes as $sizeCode) {
                    $cell = $colorCells->first(fn (RestockCell $c) => $this->cellMatchesSize($c, $sizeCode));

                    if (! $cell) {
                        continue;
                    }

                    $stock = $this->settingsService->stockQuantityForItem($cell->item);
                    $prefix = $this->fieldPrefix($sizeCode);

                    $row["{$prefix}restock"] = $cell->qty_restock;
                    $row["{$prefix}production"] = $cell->qty_production;
                    $row["{$prefix}shipped"] = $cell->qty_shipped;
                    $row["{$prefix}stock"] = $stock;
                    $row['_meta'][$prefix] = [
                        'cell_id' => $cell->id,
                        'is_urgent' => $cell->is_urgent,
                        'item_code' => $cell->item?->code,
                        'size_label' => $sizeCode,
                    ];

                    $restockTotal += (int) $cell->qty_restock;
                    $productionTotal += (int) $cell->qty_production;
                    $shippedTotal += (int) $cell->qty_shipped;
                    $stockTotal += $stock;
                }

                if (count($sizes) > 1) {
                    $row['restock_total'] = $restockTotal;
                    $row['production_total'] = $productionTotal;
                    $row['shipped_total'] = $shippedTotal;
                    $row['stock_total'] = $stockTotal;
                }

                return $row;
            })
            ->sortBy('color_name')
            ->values()
            ->all();
    }

    protected function colorGroupKey(RestockCell $cell): string
    {
        if ($cell->color_id) {
            return 'tag:'.$cell->color_id;
        }

        if ($cell->item) {
            return $this->identityBuilder->assetLancarColorGroupKey($cell->item);
        }

        return 'none';
    }

    protected function cellSizeCode(RestockCell $cell): ?string
    {
        if ($cell->size?->code) {
            return strtoupper($cell->size->code);
        }

        if ($cell->item) {
            return $this->identityBuilder->assetLancarSizeCode($cell->item);
        }

        return null;
    }

    protected function cellMatchesSize(RestockCell $cell, string $sizeCode): bool
    {
        if ($sizeCode === '—') {
            return $this->cellSizeCode($cell) === null;
        }

        return strtoupper($sizeCode) === ($this->cellSizeCode($cell) ?? '');
    }

    protected function fieldPrefix(string $sizeCode): string
    {
        if ($sizeCode === '—') {
            return '';
        }

        return str_replace(['.', ' '], '_', strtolower($sizeCode)).'_';
    }

    protected function sizeSortKey(string $code): string
    {
        $upper = strtoupper($code);
        $index = array_search($upper, self::SIZE_ORDER, true);

        if ($index !== false) {
            return '0_'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'_'.$upper;
        }

        return '1_'.$upper;
    }
}
