<?php

namespace App\Services\WarehouseCompare;

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Tag;
use App\Services\Items\ItemGroupHierarchyService;
use App\Services\Items\ItemIdentityBuilder;
use App\Support\ItemImageResolver;
use Illuminate\Support\Collection;

class WarehouseCompareGridBuilder
{
    private const SIZE_ORDER = ['S', 'M', 'L', 'XL', 'XXL'];

    public function __construct(
        protected ItemIdentityBuilder $identityBuilder,
    ) {}

    /**
     * Flat SKU list for manufactured items (one row per item code).
     *
     * @param  Collection<int, Item>  $items
     * @param  Collection<int, Addrbook>  $warehouses
     * @param  array<int, array<int, int>>  $stockByWarehouse
     * @param  array<int, float>  $soldByItem
     * @param  list<int>  $itemIdOrder
     * @return array{display_mode: string, warehouses: list<array{id: int, name: string}>, blocks: list<array<string, mixed>>}
     */
    public function buildList(
        Collection $items,
        Collection $warehouses,
        array $stockByWarehouse,
        array $soldByItem,
        int $pivotWarehouseId,
        array $itemIdOrder,
    ): array {
        $warehouseMeta = $warehouses
            ->map(fn (Addrbook $w) => ['id' => (int) $w->id, 'name' => $w->name])
            ->values()
            ->all();

        $byId = $items->keyBy('id');
        $ordered = collect($itemIdOrder)
            ->map(fn (int $id) => $byId->get($id))
            ->filter()
            ->values();

        if ($ordered->isEmpty()) {
            $ordered = $items->values();
        }

        $rows = $ordered->map(function (Item $item) use ($warehouses, $stockByWarehouse, $soldByItem, $pivotWarehouseId) {
            $itemId = (int) $item->id;
            $stocks = [];
            $pivotQty = 0;
            foreach ($warehouses as $warehouse) {
                $wid = (int) $warehouse->id;
                $qty = $stockByWarehouse[$itemId][$wid] ?? 0;
                $stocks[$wid] = $qty;
                if ($wid === $pivotWarehouseId) {
                    $pivotQty = $qty;
                }
            }

            $variant = trim((string) ($item->group?->variant ?? ''));
            $size = $this->itemSizeCode($item);

            return [
                '_type' => 'sku',
                'item_id' => $itemId,
                'code' => $item->code,
                'name' => $item->name,
                'item_url' => $item->showUrl(),
                'pcode' => $this->identityBuilder->itemParentLabel($item),
                'color' => $variant !== '' ? strtoupper($variant) : '—',
                'size' => $size !== null ? strtoupper($size) : '—',
                'sold_12m' => $soldByItem[$itemId] ?? 0.0,
                'is_low_stock' => $pivotQty < 2,
                'stocks' => $stocks,
            ];
        })->all();

        return [
            'display_mode' => 'list',
            'warehouses' => $warehouseMeta,
            'blocks' => [
                [
                    'kind' => 'list',
                    'id' => 'sku-list',
                    'title' => null,
                    'rows' => $rows,
                ],
            ],
        ];
    }

    /**
     * @param  Collection<int, Item>  $items
     * @param  Collection<int, Addrbook>  $warehouses
     * @param  array<int, array<int, int>>  $stockByWarehouse
     * @param  array<int, float>  $soldByItem
     * @return array{warehouses: list<array{id: int, name: string}>, blocks: list<array<string, mixed>>}
     */
    public function build(
        Collection $items,
        Collection $warehouses,
        array $stockByWarehouse,
        array $soldByItem,
        string $sort,
        ItemType $itemType,
        int $pivotWarehouseId,
        ?array $parentKeyOrder = null,
    ): array {
        $warehouseMeta = $warehouses
            ->map(fn (Addrbook $w) => ['id' => (int) $w->id, 'name' => $w->name])
            ->values()
            ->all();

        $parents = $this->groupItemsByParent($items, $itemType)
            ->map(function (Collection $parentItems, string $parentKey) use ($stockByWarehouse, $soldByItem, $warehouses, $pivotWarehouseId, $itemType) {
                $first = $parentItems->first();
                $pcode = $this->parentPcodeLabel($first, $itemType);
                $sizes = $this->orderedSizeCodes($parentItems);
                $rows = $this->buildColorRows(
                    $parentItems,
                    $sizes,
                    $warehouses,
                    $stockByWarehouse,
                    $soldByItem,
                    $pivotWarehouseId,
                    $itemType,
                );

                return [
                    'parent_key' => $parentKey,
                    'pcode' => $pcode,
                    'name' => $this->parentDisplayName($parentItems, $pcode),
                    'image_url' => $this->parentImageUrl($parentItems),
                    'group_url' => $this->parentGroupUrl($parentItems),
                    'sizes' => $sizes,
                    'rows' => $rows,
                    'sort_sold' => $this->parentSoldTotal($parentItems, $soldByItem),
                    'sort_pivot_stock' => $this->parentMinPivotStock($parentItems, $stockByWarehouse, $pivotWarehouseId),
                    'sort_min_code' => $this->parentMinItemCode($parentItems),
                ];
            })
            ->values();

        $parents = $this->sortParents($parents, $sort, $parentKeyOrder);

        $parentsPayload = $parents
            ->map(fn (array $parent) => collect($parent)->except(['parent_key', 'sort_sold', 'sort_pivot_stock', 'sort_min_code'])->all())
            ->all();

        return [
            'warehouses' => $warehouseMeta,
            'blocks' => $this->buildBlocks($parentsPayload),
        ];
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    protected function groupItemsByParent(Collection $items, ItemType $itemType): Collection
    {
        return $items->groupBy(function (Item $item) use ($itemType) {
            if ($itemType === ItemType::ASSET_LANCAR) {
                return '2:'.$this->identityBuilder->assetLancarParentPcode($item);
            }

            return $this->identityBuilder->itemParentKey($item);
        });
    }

    /**
     * @param  Collection<int, \Illuminate\Support\Collection<int, array<string, mixed>>>  $parents
     */
    protected function sortParents(Collection $parents, string $sort, ?array $parentKeyOrder = null): Collection
    {
        if ($parentKeyOrder !== null && $parentKeyOrder !== []) {
            $rank = array_flip($parentKeyOrder);

            return $parents
                ->sortBy(fn (array $p) => $rank[$p['parent_key']] ?? PHP_INT_MAX)
                ->values();
        }

        return match ($sort) {
            WarehouseCompareService::SORT_GROUP => $parents->sortBy(fn (array $p) => strtoupper($p['name']).':'.$p['pcode'])->values(),
            WarehouseCompareService::SORT_ITEM_CODE => $parents->sortBy(fn (array $p) => $p['sort_min_code'] ?? $p['pcode'])->values(),
            WarehouseCompareService::SORT_SOLD => $parents->sortByDesc('sort_sold')->values(),
            WarehouseCompareService::SORT_RECOMMENDATION => $parents
                ->sortBy(fn (array $p) => sprintf('%09d:%s', $p['sort_pivot_stock'], $p['pcode']))
                ->values(),
            default => $parents->sortBy('pcode')->values(),
        };
    }

    /**
     * @param  list<array{pcode: string, name: string, image_url: string, group_url: ?string, sizes: list<string>, rows: list<array<string, mixed>>}>  $parents
     * @return list<array<string, mixed>>
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
     * @param  list<array<string, mixed>>  $parents
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
     * @param  list<array<string, mixed>>  $parents
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
     * @param  list<array<string, mixed>>  $parents
     * @return list<array<string, mixed>>
     */
    protected function buildBlockRows(array $parents): array
    {
        $rows = [];

        foreach ($parents as $index => $parent) {
            $rows[] = [
                '_type' => 'section',
                '_section_divider' => $index > 0,
                'pcode' => $parent['pcode'],
                'name' => $parent['name'],
                'image_url' => $parent['image_url'],
                'group_url' => $parent['group_url'] ?? null,
                'sizes' => $parent['sizes'],
            ];

            foreach ($parent['rows'] as $colorRow) {
                $rows[] = array_merge([
                    '_type' => 'data',
                    'pcode' => $parent['pcode'],
                ], $colorRow);
            }
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Item>  $parentItems
     * @param  list<string>  $sizes
     * @param  Collection<int, Addrbook>  $warehouses
     * @param  array<int, array<int, int>>  $stockByWarehouse
     * @param  array<int, float>  $soldByItem
     * @return list<array<string, mixed>>
     */
    protected function buildColorRows(
        Collection $parentItems,
        array $sizes,
        Collection $warehouses,
        array $stockByWarehouse,
        array $soldByItem,
        int $pivotWarehouseId,
        ItemType $itemType,
    ): array {
        return $parentItems
            ->groupBy(fn (Item $item) => $this->colorGroupKey($item, $itemType))
            ->map(function (Collection $colorItems) use ($sizes, $warehouses, $stockByWarehouse, $soldByItem, $pivotWarehouseId, $itemType) {
                $first = $colorItems->first();
                $row = [
                    'color_name' => $this->colorLabel($first, $itemType),
                    'color_url' => $this->colorGroupUrl($colorItems, $itemType),
                    'is_low_stock' => false,
                    'warehouses' => [],
                ];

                foreach ($sizes as $sizeCode) {
                    $item = $colorItems->first(fn (Item $i) => $this->itemMatchesSize($i, $sizeCode));
                    if ($item === null) {
                        continue;
                    }

                    $stockCells = [];
                    foreach ($warehouses as $warehouse) {
                        $wid = (int) $warehouse->id;
                        $qty = $stockByWarehouse[(int) $item->id][$wid] ?? 0;
                        $stockCells[$wid] = $qty;
                        if ($wid === $pivotWarehouseId && $qty < 2) {
                            $row['is_low_stock'] = true;
                        }
                    }

                    $prefix = $this->fieldPrefix($sizeCode);
                    $row['warehouses'][$prefix] = $stockCells;
                    $row['_meta'][$prefix] = [
                        'item_id' => (int) $item->id,
                        'item_code' => $item->code,
                        'size_label' => $sizeCode,
                        'sold_12m' => $soldByItem[(int) $item->id] ?? 0.0,
                        'pivot_stock' => $stockCells[$pivotWarehouseId] ?? 0,
                    ];
                }

                if (count($sizes) > 1) {
                    $totals = [];
                    foreach ($warehouses as $warehouse) {
                        $wid = (int) $warehouse->id;
                        $totals[$wid] = $colorItems->sum(
                            fn (Item $item) => $stockByWarehouse[(int) $item->id][$wid] ?? 0
                        );
                    }
                    $row['warehouses']['total_'] = $totals;
                }

                return $row;
            })
            ->sortBy('color_name')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Item>  $items
     * @return list<string>
     */
    protected function orderedSizeCodes(Collection $items): array
    {
        $codes = $items
            ->map(fn (Item $item) => $this->itemSizeCode($item))
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

    protected function itemSizeCode(Item $item): ?string
    {
        return $this->identityBuilder->itemSizeCode($item);
    }

    protected function itemMatchesSize(Item $item, string $sizeCode): bool
    {
        if ($sizeCode === '—') {
            return $this->itemSizeCode($item) === null;
        }

        return strtoupper($sizeCode) === ($this->itemSizeCode($item) ?? '');
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

    protected function colorGroupKey(Item $item, ItemType $itemType): string
    {
        $label = $this->colorLabel($item, $itemType);

        return $label !== '—' ? 'color:'.$label : 'item:'.$item->id;
    }

    protected function colorLabel(Item $item, ItemType $itemType): string
    {
        if ($itemType === ItemType::ASSET_LANCAR) {
            return $this->identityBuilder->assetLancarColorLabel($item);
        }

        $variant = trim((string) ($item->group?->variant ?? ''));

        return $variant !== '' ? strtoupper($variant) : '—';
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    protected function parentDisplayName(Collection $items, string $parentPcode): string
    {
        $names = $items
            ->map(fn (Item $item) => $item->group?->name)
            ->filter()
            ->unique()
            ->values();

        $preferred = $names->first(
            fn (?string $name) => $name && strtoupper(trim($name)) !== strtoupper($parentPcode)
        );

        return $preferred ?? $names->first() ?? $parentPcode;
    }

    protected function parentPcodeLabel(Item $item, ItemType $itemType): string
    {
        if ($itemType === ItemType::ASSET_LANCAR) {
            return $this->identityBuilder->assetLancarParentPcode($item);
        }

        return $this->identityBuilder->itemParentLabel($item);
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    protected function parentImageUrl(Collection $items): string
    {
        $item = $items->first();

        return $item?->image_url ?? asset('images/default-item.svg');
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    protected function parentGroupUrl(Collection $items): ?string
    {
        $item = $items->first();
        if ($item === null) {
            return null;
        }

        $anchorId = app(ItemGroupHierarchyService::class)
            ->anchorGroupIdForParentKey($this->identityBuilder->itemParentKey($item));

        return $anchorId !== null ? route('items.group-parent-detail', $anchorId) : null;
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    protected function colorGroupUrl(Collection $items, ItemType $itemType): ?string
    {
        $label = $this->colorLabel($items->first(), $itemType);
        if ($label === '—') {
            return null;
        }

        $parentUrl = $this->parentGroupUrl($items);
        if ($parentUrl === null) {
            return null;
        }

        return $parentUrl.'#'.ItemGroupHierarchyService::colorAnchorId($label);
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    protected function parentSoldTotal(Collection $items, array $soldByItem): float
    {
        return (float) $items->sum(fn (Item $item) => $soldByItem[(int) $item->id] ?? 0.0);
    }

    /**
     * @param  Collection<int, Item>  $items
     * @param  array<int, array<int, int>>  $stockByWarehouse
     */
    protected function parentMinPivotStock(Collection $items, array $stockByWarehouse, int $pivotWarehouseId): int
    {
        if ($items->isEmpty()) {
            return 0;
        }

        return (int) $items->min(fn (Item $item) => $stockByWarehouse[(int) $item->id][$pivotWarehouseId] ?? 0);
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    protected function parentMinItemCode(Collection $items): string
    {
        $codes = $items
            ->map(fn (Item $item) => strtoupper((string) $item->code))
            ->filter(fn (string $code) => $code !== '')
            ->sort()
            ->values();

        return $codes->first() ?? '';
    }
}
