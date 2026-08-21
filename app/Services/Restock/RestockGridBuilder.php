<?php

namespace App\Services\Restock;

use App\Models\RestockCell;
use App\Models\RestockSheet;
use App\Services\Items\ItemIdentityBuilder;
use Illuminate\Support\Collection;

class RestockGridBuilder
{
    private const SIZE_ORDER = ['S', 'M', 'L', 'XL', 'XXL'];

    public function __construct(
        protected ItemIdentityBuilder $identityBuilder,
        protected RestockSettingsService $settingsService,
    ) {}

    /**
     * @return array{
     *     sheet_id: int,
     *     parents: list<array{pcode: string, name: string, image_url: string, sizes: list<string>, rows: list<array<string, mixed>>}>,
     *     blocks: list<array{
     *         kind: 'matrix'|'flat',
     *         id: string,
     *         title: string,
     *         sizes?: list<string>,
     *         rows: list<array<string, mixed>>
     *     }>
     * }
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
                    'image_url' => $this->parentImageUrl($cells),
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
            'blocks' => $this->buildBlocks($parents),
        ];
    }

    /**
     * @param  list<array{pcode: string, name: string, image_url: string, sizes: list<string>, rows: list<array<string, mixed>>}>  $parents
     * @return list<array{kind: 'matrix'|'flat', id: string, title: string, sizes?: list<string>, rows: list<array<string, mixed>>}>
     */
    protected function buildBlocks(array $parents): array
    {
        $flatParents = [];
        $alphaParents = [];
        $otherParents = [];

        foreach ($parents as $parent) {
            if ($parent['sizes'] === ['—']) {
                $flatParents[] = $parent;

                continue;
            }

            if ($this->isAlphaSizes($parent['sizes'])) {
                $alphaParents[] = $parent;

                continue;
            }

            $otherParents[] = $parent;
        }

        $blocks = [];

        if ($alphaParents !== []) {
            $blocks[] = $this->buildMatrixBlock($alphaParents, 'alpha', 'Letter sizes');
        }

        if ($otherParents !== []) {
            $blocks[] = $this->buildMatrixBlock($otherParents, 'other', 'Other sizes');
        }

        if ($flatParents !== []) {
            $blocks[] = $this->buildFlatBlock($flatParents);
        }

        return $blocks;
    }

    /**
     * @param  list<array{pcode: string, name: string, image_url: string, sizes: list<string>, rows: list<array<string, mixed>>}>  $parents
     * @return array{kind: 'matrix', id: string, title: string, sizes: list<string>, rows: list<array<string, mixed>>}
     */
    protected function buildMatrixBlock(array $parents, string $kind, string $title): array
    {
        $sizes = $this->unionSizeCodes(collect($parents)->pluck('sizes'));

        return [
            'kind' => 'matrix',
            'id' => 'matrix-'.$kind,
            'title' => $title,
            'sizes' => $sizes,
            'rows' => $this->buildBlockRows($parents),
        ];
    }

    /**
     * @param  list<array{pcode: string, name: string, image_url: string, sizes: list<string>, rows: list<array<string, mixed>>}>  $parents
     * @return array{kind: 'flat', id: string, title: string, rows: list<array<string, mixed>>}
     */
    protected function buildFlatBlock(array $parents): array
    {
        return [
            'kind' => 'flat',
            'id' => 'flat',
            'title' => 'No size',
            'rows' => $this->buildBlockRows($parents),
        ];
    }

    /**
     * @param  list<array{pcode: string, name: string, image_url: string, sizes: list<string>, rows: list<array<string, mixed>>}>  $parents
     * @return list<array<string, mixed>>
     */
    protected function buildBlockRows(array $parents): array
    {
        $rows = [];

        foreach ($parents as $index => $parent) {
            $rows[] = [
                '_type' => 'section',
                '_rowKey' => 'section:'.$parent['pcode'],
                '_section_divider' => $index > 0,
                'pcode' => $parent['pcode'],
                'name' => $parent['name'],
                'image_url' => $parent['image_url'],
                'sizes' => $parent['sizes'],
            ];

            foreach ($parent['rows'] as $colorRow) {
                $rows[] = array_merge([
                    '_type' => 'data',
                    '_rowKey' => 'data:'.$parent['pcode'].':'.($colorRow['color_id'] ?? $colorRow['color_name']),
                    'pcode' => $parent['pcode'],
                    'parent_sizes' => $parent['sizes'],
                ], $colorRow);
            }
        }

        return $rows;
    }

    /**
     * @param  Collection<int, list<string>>  $sizeLists
     * @return list<string>
     */
    protected function unionSizeCodes(Collection $sizeLists): array
    {
        return $sizeLists
            ->flatten()
            ->filter(fn (string $code) => $code !== '—')
            ->unique()
            ->sortBy(fn (string $code) => $this->sizeSortKey($code))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $sizes
     */
    protected function isAlphaSizes(array $sizes): bool
    {
        foreach ($sizes as $size) {
            if (! in_array(strtoupper($size), self::SIZE_ORDER, true)) {
                return false;
            }
        }

        return true;
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
     */
    protected function parentImageUrl(Collection $cells): string
    {
        $item = $cells->first(fn (RestockCell $cell) => $cell->item !== null)?->item;

        return $item?->image_url ?? asset('images/default-item.svg');
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
