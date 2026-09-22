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

    public const SORT_GROUP = 'group';

    public const SORT_SOLD = 'sold';

    public const SORT_RECOMMENDATION = 'recommendation';

    public function __construct(
        protected WarehouseComparePreferenceService $preferences,
        protected WarehouseCompareGridBuilder $gridBuilder,
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
            self::SORT_SKU => 'SKU (code)',
            self::SORT_GROUP => 'Product group',
            self::SORT_SOLD => 'Sold qty (12 mo)',
            self::SORT_RECOMMENDATION => 'Low stock (< 2)',
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
     *     sold_by_item: array<int, float>
     * }
     */
    public function buildPage(Request $request, User $user): array
    {
        $defaults = $this->preferences->defaults($user);
        $warehouseIds = $this->resolveWarehouseIds($request, $defaults['warehouse_ids'], $user);
        $itemType = ItemType::coerce($request->query('item_type', $defaults['item_type']))
            ?? ItemType::ASSET_LANCAR;
        $sort = $this->preferences->normalizeSort($request->query('sort', $defaults['sort']));

        $warehouses = $this->loadWarehouses($warehouseIds, $user);
        $pivot = $warehouses->first();

        $grid = [
            'blocks' => [],
            'warehouses' => [],
        ];
        $soldByItem = [];

        if ($pivot !== null) {
            $items = $this->loadPivotItems($pivot->id, $itemType, $warehouseIds);
            $itemIds = $items->pluck('id')->map(fn ($id) => (int) $id)->all();
            $soldByItem = $this->soldTotalsByItem($itemIds, $pivot->id);
            $stockByWarehouse = $this->stockMatrix($itemIds, $warehouseIds);

            $grid = $this->gridBuilder->build(
                $items,
                $warehouses,
                $stockByWarehouse,
                $soldByItem,
                $sort,
                $itemType,
                $pivot->id,
            );
        }

        return [
            'warehouses' => $this->preferences->selectableWarehouses($user),
            'selected_warehouse_ids' => $warehouseIds,
            'pivot_warehouse' => $pivot,
            'item_type' => $itemType,
            'sort' => $sort,
            'grid' => $grid,
            'sold_by_item' => $soldByItem,
        ];
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
                'warehouseItems' => fn ($q) => $q->whereIn('warehouse_id', $warehouseIds),
            ])
            ->orderBy('code')
            ->get();
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
