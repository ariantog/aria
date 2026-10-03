<?php

namespace App\Services\Shopee;

use App\Models\Item;
use App\Models\Shopeesync;
use Illuminate\Support\Collection;

class WarehouseShopeeStockService
{
    public function __construct(
        private ShopeeStockApiService $stockApi,
    ) {}

    public function syncForWarehouse(int $warehouseId): ?Shopeesync
    {
        return Shopeesync::query()
            ->where('warehouse_id', $warehouseId)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  Collection<int, Item>  $items
     * @return array{
     *     stocks: array<int, array{
     *         linked: bool,
     *         sellable: ?float,
     *         reserved: ?float,
     *         mismatch: bool,
     *     }>,
     *     fetch_failed: bool,
     *     unlinked_count: int,
     * }
     */
    public function stockDataForItems(Shopeesync $sync, Collection $items): array
    {
        $locationId = trim((string) $sync->shopee_location_id);
        $locationFilter = $locationId !== '' ? $locationId : null;

        $stocks = [];
        $unlinkedCount = 0;

        foreach ($items as $item) {
            $linked = (int) $item->shopee_item_id > 0;
            if (! $linked) {
                $unlinkedCount++;
            }

            $stocks[$item->id] = [
                'linked' => $linked,
                'sellable' => null,
                'reserved' => null,
                'mismatch' => false,
            ];
        }

        $itemIds = $items
            ->pluck('shopee_item_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->unique()
            ->values()
            ->all();

        $fetchFailed = false;
        $modelsByItem = [];

        if ($itemIds !== []) {
            if (! $this->stockApi->isReady()) {
                $fetchFailed = true;
            } else {
                $modelsByItem = $this->stockApi->modelsByItemIds($itemIds);
                if ($modelsByItem === [] && $itemIds !== []) {
                    $fetchFailed = true;
                }
            }
        }

        foreach ($items as $item) {
            $shopeeItemId = (int) ($item->shopee_item_id ?? 0);
            if ($shopeeItemId <= 0) {
                continue;
            }

            $models = $modelsByItem[$shopeeItemId] ?? [];
            $model = ShopeeModelStock::pickModel(
                $models,
                (int) ($item->shopee_model_id ?? 0),
                (string) $item->code,
            );

            $sellable = ShopeeModelStock::sellableQuantity($model, $locationFilter);
            $reserved = ShopeeModelStock::reservedQuantity($model);
            $ariaQty = (float) ($item->pivot->quantity ?? 0);

            $stocks[$item->id] = [
                'linked' => true,
                'sellable' => $sellable !== null ? (float) $sellable : null,
                'reserved' => $reserved !== null ? (float) $reserved : null,
                'mismatch' => $sellable !== null && $ariaQty !== (float) $sellable,
            ];
        }

        return [
            'stocks' => $stocks,
            'fetch_failed' => $fetchFailed,
            'unlinked_count' => $unlinkedCount,
        ];
    }
}
