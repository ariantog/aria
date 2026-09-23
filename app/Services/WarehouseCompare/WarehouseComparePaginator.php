<?php

namespace App\Services\WarehouseCompare;

use App\Enums\ItemType;
use App\Models\Item;
use App\Services\Items\ItemIdentityBuilder;
use Illuminate\Support\Collection;

/**
 * Orders pivot-warehouse SKUs and slices pages (100/200 items) for the compare grid.
 */
class WarehouseComparePaginator
{
    public function __construct(
        protected ItemIdentityBuilder $identityBuilder,
    ) {}

    /**
     * @param  Collection<int, Item>  $items  All pivot SKUs (group/tags loaded; warehouseItems optional)
     * @param  array<int, float>  $soldByItem
     * @param  array<int, int>  $pivotStockByItem  item_id => qty at pivot
     * @return array{0: Collection<int, Item>, 1: int, 2: int, 3: int, 4: list<string>}
     */
    public function sliceForPage(
        Collection $items,
        string $sort,
        ItemType $itemType,
        int $page,
        int $perPage,
        array $soldByItem,
        array $pivotStockByItem,
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));

        if ($items->isEmpty()) {
            return [collect(), 0, $page, $perPage, []];
        }

        $orderedIds = $this->orderedItemIds($items, $sort, $itemType, $soldByItem, $pivotStockByItem);
        $total = count($orderedIds);
        $offset = ($page - 1) * $perPage;
        $pageIds = array_slice($orderedIds, $offset, $perPage);

        if ($pageIds === []) {
            return [collect(), $total, $page, $perPage, []];
        }

        $pageIdSet = array_fill_keys($pageIds, true);

        if ($itemType === ItemType::ITEM) {
            $order = array_flip($pageIds);
            $pageItems = $items
                ->filter(fn (Item $item) => isset($pageIdSet[(int) $item->id]))
                ->sortBy(fn (Item $item) => $order[(int) $item->id] ?? PHP_INT_MAX)
                ->values();

            return [$pageItems, $total, $page, $perPage, []];
        }

        $parentKeysOnPage = [];
        foreach ($items as $item) {
            if (isset($pageIdSet[(int) $item->id])) {
                $parentKeysOnPage[$this->parentKey($item, $itemType)] = true;
            }
        }

        $parentKeyOrder = [];
        foreach ($orderedIds as $id) {
            $item = $items->firstWhere('id', $id);
            if ($item === null) {
                continue;
            }
            $key = $this->parentKey($item, $itemType);
            if (isset($parentKeysOnPage[$key]) && ! in_array($key, $parentKeyOrder, true)) {
                $parentKeyOrder[] = $key;
            }
        }

        $pageItems = $items->filter(
            fn (Item $item) => isset($parentKeysOnPage[$this->parentKey($item, $itemType)])
        )->values();

        return [$pageItems, $total, $page, $perPage, $parentKeyOrder];
    }

    /**
     * @param  Collection<int, Item>  $items
     * @param  array<int, float>  $soldByItem
     * @param  array<int, int>  $pivotStockByItem
     * @return list<int>
     */
    public function orderedItemIds(
        Collection $items,
        string $sort,
        ItemType $itemType,
        array $soldByItem,
        array $pivotStockByItem,
    ): array {
        $rows = $items->map(function (Item $item) use ($itemType, $soldByItem, $pivotStockByItem) {
            $id = (int) $item->id;
            $parentKey = $this->parentKey($item, $itemType);

            return [
                'id' => $id,
                'code' => strtoupper((string) $item->code),
                'pcode' => strtoupper($this->parentPcodeLabel($item, $itemType)),
                'group_name' => strtoupper((string) ($item->group?->name ?? '')),
                'color' => strtoupper($this->colorLabel($item, $itemType)),
                'size' => strtoupper((string) ($this->identityBuilder->itemSizeCode($item) ?? '')),
                'parent_key' => $parentKey,
                'sold' => $soldByItem[$id] ?? 0.0,
                'pivot_stock' => $pivotStockByItem[$id] ?? 0,
            ];
        });

        $sorted = match ($sort) {
            WarehouseCompareService::SORT_ITEM_CODE => $rows->sortBy([
                ['code', 'asc'],
                ['id', 'asc'],
            ]),
            WarehouseCompareService::SORT_GROUP => $rows->sortBy([
                ['group_name', 'asc'],
                ['pcode', 'asc'],
                ['code', 'asc'],
            ]),
            WarehouseCompareService::SORT_SOLD => $rows
                ->sort(function (array $a, array $b): int {
                    $bySold = $b['sold'] <=> $a['sold'];
                    if ($bySold !== 0) {
                        return $bySold;
                    }

                    return $a['code'] <=> $b['code'];
                })
                ->values(),
            WarehouseCompareService::SORT_RECOMMENDATION => $rows->sortBy([
                ['pivot_stock', 'asc'],
                ['code', 'asc'],
            ]),
            default => $rows->sortBy([
                ['pcode', 'asc'],
                ['color', 'asc'],
                ['size', 'asc'],
                ['code', 'asc'],
            ]),
        };

        return $sorted->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    protected function parentKey(Item $item, ItemType $itemType): string
    {
        if ($itemType === ItemType::ASSET_LANCAR) {
            return '2:'.$this->identityBuilder->assetLancarParentPcode($item);
        }

        return $this->identityBuilder->itemParentKey($item);
    }

    protected function parentPcodeLabel(Item $item, ItemType $itemType): string
    {
        if ($itemType === ItemType::ASSET_LANCAR) {
            return $this->identityBuilder->assetLancarParentPcode($item);
        }

        return $this->identityBuilder->itemParentLabel($item);
    }

    protected function colorLabel(Item $item, ItemType $itemType): string
    {
        if ($itemType === ItemType::ASSET_LANCAR) {
            return $this->identityBuilder->assetLancarColorLabel($item);
        }

        $variant = trim((string) ($item->group?->variant ?? ''));

        return $variant !== '' ? strtoupper($variant) : '—';
    }
}
