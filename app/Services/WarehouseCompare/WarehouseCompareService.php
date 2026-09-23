<?php

namespace App\Services\WarehouseCompare;

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\User;
use App\Services\LocationAccessService;
use App\Services\Restock\RestockRollingYearStatsByItem;
use App\Services\WarehouseComparePreferenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WarehouseCompareService
{
    public const SORT_SKU = 'sku';

    public const SORT_ITEM_CODE = 'item_code';

    public const SORT_GROUP = 'group';

    public const SORT_SOLD = 'sold';

    public const SORT_RECOMMENDATION = 'recommendation';

    public const PER_PAGE_DEFAULT = 100;

    public const PER_PAGE_LARGE = 200;

    public function __construct(
        protected WarehouseComparePreferenceService $preferences,
        protected WarehouseCompareGridBuilder $gridBuilder,
        protected WarehouseComparePaginator $paginator,
        protected LocationAccessService $locationAccess,
        protected RestockRollingYearStatsByItem $rollingYearStats,
    ) {}

    /**
     * @return list<string>
     */
    public static function validSorts(): array
    {
        return [
            self::SORT_SKU,
            self::SORT_ITEM_CODE,
            self::SORT_GROUP,
            self::SORT_SOLD,
            self::SORT_RECOMMENDATION,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sortLabels(): array
    {
        return [
            self::SORT_SKU => 'Product pcode',
            self::SORT_ITEM_CODE => 'Item code (SKU)',
            self::SORT_GROUP => 'Product group',
            self::SORT_SOLD => 'Sold qty (12 mo)',
            self::SORT_RECOMMENDATION => 'Low stock (< 2)',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function perPageOptions(): array
    {
        return [
            self::PER_PAGE_DEFAULT => '100 SKUs per page',
            self::PER_PAGE_LARGE => '200 SKUs per page',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function itemTypeLabels(): array
    {
        return [
            (string) ItemType::ASSET_LANCAR->value => 'Asset lancar',
            (string) ItemType::ITEM->value => 'Items (manufactured)',
        ];
    }

    /**
     * @return array{
     *     warehouses: Collection<int, Addrbook>,
     *     selected_warehouse_ids: list<int>,
     *     pivot_warehouse: ?Addrbook,
     *     item_type: ItemType,
     *     sort: string,
     *     grid: array<string, mixed>,
     *     sold_by_item: array<int, float>,
     *     pagination: array{total: int, page: int, per_page: int, from: ?int, to: ?int, last_page: int}
     * }
     */
    public function buildPage(Request $request, User $user, bool $paginate = true): array
    {
        $defaults = $this->preferences->defaults($user);
        $warehouseIds = $this->resolveWarehouseIds($request, $defaults['warehouse_ids'], $user);
        $itemType = ItemType::coerce($request->query('item_type', $defaults['item_type']))
            ?? ItemType::ASSET_LANCAR;
        $sort = $this->preferences->normalizeSort($request->query('sort', $defaults['sort']));
        $perPage = $this->resolvePerPage($request);
        $page = max(1, (int) $request->query('page', 1));

        $warehouses = $this->loadWarehouses($warehouseIds, $user);
        $pivot = $warehouses->first();

        $grid = [
            'blocks' => [],
            'warehouses' => [],
        ];
        $soldByItem = [];
        $pagination = [
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'from' => null,
            'to' => null,
            'last_page' => 1,
        ];

        if ($pivot !== null) {
            $allItems = $this->loadPivotItems($pivot->id, $itemType, $warehouseIds);
            $allItemIds = $allItems->pluck('id')->map(fn ($id) => (int) $id)->all();
            $soldByItem = $this->soldTotalsByItem($allItemIds, $pivot->id);
            $pivotStock = $this->pivotStockByItem($allItemIds, $pivot->id);

            if ($paginate) {
                [$items, $total, $page, $perPage, $parentKeyOrder] = $this->paginator->sliceForPage(
                    $allItems,
                    $sort,
                    $itemType,
                    $page,
                    $perPage,
                    $soldByItem,
                    $pivotStock,
                );
                $pagination = $this->paginationMeta($total, $page, $perPage);
                $this->loadWarehouseStockForItems($items, $warehouseIds);
            } else {
                $items = $allItems;
                $parentKeyOrder = null;
                $pagination['total'] = count($allItemIds);
                $this->loadWarehouseStockForItems($items, $warehouseIds);
            }

            $itemIds = $items->pluck('id')->map(fn ($id) => (int) $id)->all();
            $stockByWarehouse = $this->stockMatrix($itemIds, $warehouseIds);

            if ($itemType === ItemType::ITEM) {
                $itemIdOrder = $paginate
                    ? $items->pluck('id')->map(fn ($id) => (int) $id)->all()
                    : $this->paginator->orderedItemIds($allItems, $sort, $itemType, $soldByItem, $pivotStock);

                $grid = $this->gridBuilder->buildList(
                    $items,
                    $warehouses,
                    $stockByWarehouse,
                    $soldByItem,
                    $pivot->id,
                    $itemIdOrder,
                );
            } else {
                $grid = $this->gridBuilder->build(
                    $items,
                    $warehouses,
                    $stockByWarehouse,
                    $soldByItem,
                    $sort,
                    $itemType,
                    $pivot->id,
                    $parentKeyOrder,
                );
            }
        }

        return [
            'warehouses' => $this->preferences->selectableWarehouses($user),
            'selected_warehouse_ids' => $warehouseIds,
            'pivot_warehouse' => $pivot,
            'item_type' => $itemType,
            'sort' => $sort,
            'per_page' => $perPage,
            'grid' => $grid,
            'sold_by_item' => $soldByItem,
            'pagination' => $pagination,
        ];
    }

    public function resolvePerPage(Request $request): int
    {
        $raw = (int) $request->query('per_page', self::PER_PAGE_DEFAULT);

        return $raw === self::PER_PAGE_LARGE ? self::PER_PAGE_LARGE : self::PER_PAGE_DEFAULT;
    }

    /**
     * @return array{total: int, page: int, per_page: int, from: ?int, to: ?int, last_page: int}
     */
    protected function paginationMeta(int $total, int $page, int $perPage): array
    {
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $from = $total === 0 ? null : (($page - 1) * $perPage) + 1;
        $to = $total === 0 ? null : min($total, $page * $perPage);

        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'from' => $from,
            'to' => $to,
            'last_page' => $lastPage,
        ];
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, int>
     */
    protected function pivotStockByItem(array $itemIds, int $pivotWarehouseId): array
    {
        if ($itemIds === []) {
            return [];
        }

        $rows = DB::table('warehouse_item')
            ->where('warehouse_id', $pivotWarehouseId)
            ->whereIn('item_id', $itemIds)
            ->get(['item_id', 'quantity']);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->item_id] = (int) $row->quantity;
        }

        return $out;
    }

    /**
     * @param  list<int>  $defaults
     * @return list<int>
     */
    protected function resolveWarehouseIds(Request $request, array $defaults, User $user): array
    {
        if ($request->has('warehouse_ids')) {
            $raw = $request->query('warehouse_ids');
            if (! is_array($raw)) {
                $raw = [$raw];
            }

            return $this->preferences->normalizeWarehouseIds($raw, $user);
        }

        if ($request->has('warehouses')) {
            $raw = $request->query('warehouses');
            if (is_string($raw)) {
                $raw = array_filter(explode(',', $raw));
            }

            return $this->preferences->normalizeWarehouseIds(is_array($raw) ? $raw : [], $user);
        }

        return $defaults;
    }

    /**
     * @param  list<int>  $warehouseIds
     * @return Collection<int, Addrbook>
     */
    protected function loadWarehouses(array $warehouseIds, User $user): Collection
    {
        if ($warehouseIds === []) {
            return collect();
        }

        $byId = Addrbook::query()
            ->whereIn('id', $warehouseIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        $ordered = collect();
        foreach ($warehouseIds as $id) {
            $warehouse = $byId->get($id);
            if ($warehouse === null) {
                continue;
            }
            if (! $this->locationAccess->canAccessAddrbook($user, $warehouse)) {
                continue;
            }
            $ordered->push($warehouse);
        }

        return $ordered->values();
    }

    /**
     * @param  list<int>  $warehouseIds
     * @return Collection<int, Item>
     */
    protected function loadPivotItems(int $pivotWarehouseId, ItemType $itemType, array $warehouseIds): Collection
    {
        return Item::query()
            ->where('type', $itemType->value)
            ->whereHas('warehouseItems', fn ($q) => $q->where('warehouse_id', $pivotWarehouseId))
            ->with([
                'group',
                'tags',
            ])
            ->orderBy('code')
            ->get();
    }

    /**
     * @param  Collection<int, Item>  $items
     * @param  list<int>  $warehouseIds
     */
    protected function loadWarehouseStockForItems(Collection $items, array $warehouseIds): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $items->load([
            'warehouseItems' => fn ($q) => $q->whereIn('warehouse_id', $warehouseIds),
        ]);
    }

    /**
     * @param  list<int>  $itemIds
     * @param  list<int>  $warehouseIds
     * @return array<int, array<int, int>>
     */
    protected function stockMatrix(array $itemIds, array $warehouseIds): array
    {
        if ($itemIds === [] || $warehouseIds === []) {
            return [];
        }

        $rows = DB::table('warehouse_item')
            ->whereIn('item_id', $itemIds)
            ->whereIn('warehouse_id', $warehouseIds)
            ->get(['item_id', 'warehouse_id', 'quantity']);

        $matrix = [];
        foreach ($rows as $row) {
            $matrix[(int) $row->item_id][(int) $row->warehouse_id] = (int) $row->quantity;
        }

        return $matrix;
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, float>
     */
    protected function soldTotalsByItem(array $itemIds, int $pivotWarehouseId): array
    {
        if ($itemIds === []) {
            return [];
        }

        $summaries = $this->rollingYearStats->summariesForItems($itemIds);

        $out = [];
        foreach ($itemIds as $itemId) {
            $out[$itemId] = (float) ($summaries[$itemId]['net_12m'] ?? 0.0);
        }

        return $out;
    }
}
