<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Jubeliosync;
use Illuminate\Support\Collection;

class WarehouseJubelioStockService
{
    public function __construct(
        private JubelioStockCheckService $stockCheckService,
        private JubelioService $jubelioService,
    ) {}

    public function syncForWarehouse(int $warehouseId): ?Jubeliosync
    {
        return Jubeliosync::query()
            ->where('warehouse_id', $warehouseId)
            ->where('jubelio_store_id', '>', 0)
            ->whereMappedJubelioLocation()
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  Collection<int, Item>  $items
     * @return array{
     *     stocks: array<int, array{
     *         linked: bool,
     *         on_hand: ?float,
     *         on_order: ?float,
     *         reserved: ?float,
     *         available: ?float,
     *         mismatch: bool,
     *     }>,
     *     fetch_failed: bool,
     *     unlinked_count: int,
     * }
     */
    public function stockDataForItems(Jubeliosync $sync, Collection $items): array
    {
        $base = $this->jubelioQuantitiesForItems($sync, $items);
        $stocks = $base['stocks'];

        foreach ($items as $item) {
            $row = $stocks[$item->id] ?? null;
            if (! $row || ! $row['linked']) {
                continue;
            }

            $ariaQty = (float) ($item->pivot->quantity ?? 0);
            $available = $row['available'];

            $stocks[$item->id]['mismatch'] = $available !== null && $ariaQty !== $available;
        }

        return [
            'stocks' => $stocks,
            'fetch_failed' => $base['fetch_failed'],
            'unlinked_count' => $base['unlinked_count'],
        ];
    }

    /**
     * Live Jubelio location quantities for linked items (no Aria qty / mismatch).
     *
     * @param  Collection<int, Item>  $items
     * @return array{
     *     stocks: array<int, array{
     *         linked: bool,
     *         on_hand: ?float,
     *         on_order: ?float,
     *         reserved: ?float,
     *         available: ?float,
     *         mismatch: bool,
     *     }>,
     *     fetch_failed: bool,
     *     unlinked_count: int,
     * }
     */
    public function jubelioQuantitiesForItems(Jubeliosync $sync, Collection $items): array
    {
        $locationId = (int) $sync->jubelio_location_id;
        $stocks = [];
        $unlinkedCount = 0;

        foreach ($items as $item) {
            $linked = (int) $item->jubelio_item_id > 0;
            if (! $linked) {
                $unlinkedCount++;
            }

            $stocks[$item->id] = [
                'linked' => $linked,
                'on_hand' => null,
                'on_order' => null,
                'reserved' => null,
                'available' => null,
                'mismatch' => false,
            ];
        }

        $jubelioIds = $items
            ->pluck('jubelio_item_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->unique()
            ->values()
            ->all();

        $fetchFailed = false;
        $indexed = [];

        if ($jubelioIds !== []) {
            $response = $this->jubelioService->fetchItemsAllStocks($jubelioIds);
            if ($response === null) {
                $fetchFailed = true;
            } else {
                foreach ($response['data'] ?? [] as $row) {
                    if (isset($row['item_id'])) {
                        $indexed[$row['item_id']] = $row;
                    }
                }
            }
        }

        foreach ($items as $item) {
            if (! (int) $item->jubelio_item_id) {
                continue;
            }

            $locationStock = $this->stockCheckService->locationStockFor(
                $indexed[$item->jubelio_item_id] ?? null,
                $locationId,
            );

            $quantities = $locationStock !== null
                ? $this->stockCheckService->resolveLocationQuantities($locationStock)
                : null;

            $stocks[$item->id] = [
                'linked' => true,
                'on_hand' => $quantities['on_hand'] ?? null,
                'on_order' => $quantities['on_order'] ?? null,
                'reserved' => $quantities['reserved'] ?? null,
                'available' => $quantities['available'] ?? null,
                'mismatch' => false,
            ];
        }

        return [
            'stocks' => $stocks,
            'fetch_failed' => $fetchFailed,
            'unlinked_count' => $unlinkedCount,
        ];
    }
}
