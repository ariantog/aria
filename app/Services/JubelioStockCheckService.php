<?php

namespace App\Services;

use App\Enums\ItemType;
use App\Enums\TransactionType;
use App\Models\Item;
use App\Models\JubelioStockCheck;
use App\Models\JubelioStockDiscrepancy;
use App\Models\Jubeliosync;
use App\Models\TransactionDetail;
use App\Models\WarehouseItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JubelioStockCheckService
{
    public function __construct(
        private JubelioService $jubelioService,
    ) {}

    /**
     * @return list<Jubeliosync>
     */
    public function syncedWarehouses(): array
    {
        return Jubeliosync::query()
            ->where('warehouse_id', '>', 0)
            ->where('jubelio_location_id', '>', 0)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * Process the next synced warehouse for a stock-check job.
     *
     * @return array{done: bool, warehouse: ?string, checked: int, discrepancies: int}
     */
    public function processNextWarehouse(JubelioStockCheck $job): array
    {
        $syncs = $this->syncedWarehouses();

        if ($job->sync_cursor >= count($syncs)) {
            $job->update(['status' => 'completed']);

            return ['done' => true, 'warehouse' => null, 'checked' => 0, 'discrepancies' => 0];
        }

        /** @var Jubeliosync $sync */
        $sync = $syncs[$job->sync_cursor];
        $items = $this->selectItemsForWarehouse($sync, $job->per_type_limit, $job->demand_days);

        $discrepancyCount = 0;
        if ($items->isNotEmpty()) {
            $discrepancyCount = $this->compareItemsAtWarehouse($job, $sync, $items);
        }

        $job->update([
            'sync_cursor' => $job->sync_cursor + 1,
            'status' => $job->sync_cursor + 1 >= count($syncs) ? 'completed' : 'processing',
        ]);

        return [
            'done' => $job->status === 'completed',
            'warehouse' => $sync->jubelio_location_name,
            'checked' => $items->count(),
            'discrepancies' => $discrepancyCount,
        ];
    }

    /**
     * @return Collection<int, Item>
     */
    public function selectItemsForWarehouse(Jubeliosync $sync, int $perTypeLimit, int $demandDays): Collection
    {
        $selected = collect();

        foreach ([ItemType::ITEM, ItemType::ASSET_LANCAR] as $type) {
            $ids = $this->topDemandItemIds($sync->warehouse_id, $type, $perTypeLimit, $demandDays);

            if ($ids->count() < $perTypeLimit) {
                $ids = $ids->merge(
                    $this->fallbackItemIds($sync->warehouse_id, $type, $perTypeLimit - $ids->count(), $ids->all()),
                );
            }

            $selected = $selected->merge(
                Item::query()->whereIn('id', $ids->unique()->values())->get(),
            );
        }

        return $selected->unique('id')->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function topDemandItemIds(int $warehouseId, ItemType $type, int $limit, int $demandDays): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        $since = now()->subDays($demandDays)->toDateString();

        return TransactionDetail::query()
            ->select('transaction_details.item_id', DB::raw('SUM(ABS(transaction_details.quantity)) as demand_qty'))
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->join('items', 'items.id', '=', 'transaction_details.item_id')
            ->where('transactions.type', TransactionType::Sell->value)
            ->where('transactions.sender_id', $warehouseId)
            ->whereDate('transactions.date', '>=', $since)
            ->where('items.type', $type->value)
            ->where('items.jubelio_item_id', '>', 0)
            ->whereNull('transaction_details.deleted_at')
            ->groupBy('transaction_details.item_id')
            ->orderByDesc('demand_qty')
            ->limit($limit)
            ->pluck('transaction_details.item_id');
    }

    /**
     * @param  list<int>  $excludeIds
     * @return Collection<int, int>
     */
    public function fallbackItemIds(int $warehouseId, ItemType $type, int $limit, array $excludeIds = []): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        return WarehouseItem::query()
            ->select('warehouse_items.item_id')
            ->join('items', 'items.id', '=', 'warehouse_items.item_id')
            ->where('warehouse_items.warehouse_id', $warehouseId)
            ->where('warehouse_items.quantity', '>', 0)
            ->where('items.type', $type->value)
            ->where('items.jubelio_item_id', '>', 0)
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('warehouse_items.item_id', $excludeIds))
            ->orderByDesc('warehouse_items.quantity')
            ->limit($limit)
            ->pluck('warehouse_items.item_id');
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    public function compareItemsAtWarehouse(JubelioStockCheck $job, Jubeliosync $sync, Collection $items): int
    {
        $jubelioIds = $items->pluck('jubelio_item_id')->filter()->unique()->values()->all();
        if ($jubelioIds === []) {
            return 0;
        }

        $stocksByJubelioId = $this->fetchStocksIndexed($jubelioIds);
        $ariaQtyByItemId = WarehouseItem::query()
            ->where('warehouse_id', $sync->warehouse_id)
            ->whereIn('item_id', $items->pluck('id'))
            ->pluck('quantity', 'item_id');

        $discrepancies = 0;

        foreach ($items as $item) {
            $locationStock = $this->locationStockFor(
                $stocksByJubelioId[$item->jubelio_item_id] ?? null,
                (int) $sync->jubelio_location_id,
            );

            if ($locationStock === null) {
                continue;
            }

            $onHand = (float) ($locationStock['on_hand'] ?? 0);
            $onOrder = (float) ($locationStock['on_order'] ?? 0);
            $jubelioQty = $onHand + $onOrder;
            $ariaQty = (float) ($ariaQtyByItemId[$item->id] ?? 0);

            if ($ariaQty === $jubelioQty) {
                continue;
            }

            JubelioStockDiscrepancy::create([
                'jubelio_stock_check_id' => $job->id,
                'item_id' => $item->id,
                'jubelio_item_id' => $item->jubelio_item_id,
                'jubelio_location_id' => $sync->jubelio_location_id,
                'jubelio_location_name' => $sync->jubelio_location_name,
                'warehouse_id' => $sync->warehouse_id,
                'aria_qty' => $ariaQty,
                'jubelio_qty' => $jubelioQty,
                'jubelio_on_hand' => $onHand,
                'jubelio_on_order' => $onOrder,
            ]);

            $discrepancies++;
        }

        return $discrepancies;
    }

    /**
     * @param  list<int|string>  $jubelioItemIds
     * @return array<int|string, array<string, mixed>>
     */
    public function fetchStocksIndexed(array $jubelioItemIds): array
    {
        $response = $this->jubelioService->fetchItemsAllStocks($jubelioItemIds);
        if (! $response || ! isset($response['data'])) {
            return [];
        }

        $indexed = [];
        foreach ($response['data'] as $row) {
            if (isset($row['item_id'])) {
                $indexed[$row['item_id']] = $row;
            }
        }

        return $indexed;
    }

    /**
     * @param  array<string, mixed>|null  $stockRow
     * @return array<string, mixed>|null
     */
    public function locationStockFor(?array $stockRow, int $locationId): ?array
    {
        if ($stockRow === null) {
            return null;
        }

        foreach ($stockRow['location_stocks'] ?? [] as $locStock) {
            if ((int) ($locStock['location_id'] ?? 0) === $locationId) {
                return $locStock;
            }
        }

        return null;
    }
}
