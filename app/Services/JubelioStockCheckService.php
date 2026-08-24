<?php

namespace App\Services;

use App\Models\Item;
use App\Models\JubelioStockCheck;
use App\Models\JubelioStockDiscrepancy;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\WarehouseItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JubelioStockCheckService
{
    public const DEFAULT_TARGET_DISCREPANCIES = 50;

    public const MAX_SCAN_ROUNDS = 20;

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

    public function ensureDailyJob(): ?JubelioStockCheck
    {
        $activeJob = JubelioStockCheck::query()
            ->whereIn('status', ['created', 'processing'])
            ->orderByDesc('created_at')
            ->first();

        if ($activeJob) {
            return $activeJob;
        }

        $hasJobToday = JubelioStockCheck::query()
            ->whereDate('created_at', today())
            ->exists();

        if ($hasJobToday) {
            return null;
        }

        return JubelioStockCheck::create([
            'sync_cursor' => 0,
            'per_type_limit' => 100,
            'demand_days' => 30,
            'target_discrepancies' => self::DEFAULT_TARGET_DISCREPANCIES,
            'scan_round' => 0,
            'status' => 'created',
        ]);
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
            if ($this->shouldContinueScanning($job)) {
                $this->startNextScanRound($job);
                $job->refresh();
            } else {
                $job->update(['status' => 'completed']);
            }

            return ['done' => $job->status === 'completed', 'warehouse' => null, 'checked' => 0, 'discrepancies' => 0];
        }

        /** @var Jubeliosync $sync */
        $sync = $syncs[$job->sync_cursor];
        $items = $this->selectItemsForWarehouse($sync, $job->per_type_limit, $job->demand_days, $job->scan_round);

        $discrepancyCount = 0;
        if ($items->isNotEmpty()) {
            $discrepancyCount = $this->compareItemsAtWarehouse($job, $sync, $items);
        }

        $job->update([
            'sync_cursor' => $job->sync_cursor + 1,
            'status' => $job->sync_cursor + 1 >= count($syncs) && ! $this->shouldContinueScanning($job)
                ? 'completed'
                : 'processing',
        ]);

        return [
            'done' => $job->status === 'completed',
            'warehouse' => $sync->jubelio_location_name,
            'checked' => $items->count(),
            'discrepancies' => $discrepancyCount,
        ];
    }

    public function shouldContinueScanning(JubelioStockCheck $job): bool
    {
        $target = max(1, (int) ($job->target_discrepancies ?: self::DEFAULT_TARGET_DISCREPANCIES));

        if ($job->discrepancies()->count() >= $target) {
            return false;
        }

        if ($job->scan_round >= self::MAX_SCAN_ROUNDS) {
            return false;
        }

        return $this->hasMoreItemsToScan($job);
    }

    public function startNextScanRound(JubelioStockCheck $job): void
    {
        $job->update([
            'sync_cursor' => 0,
            'scan_round' => $job->scan_round + 1,
            'status' => 'processing',
        ]);
    }

    public function hasMoreItemsToScan(JubelioStockCheck $job): bool
    {
        foreach ($this->syncedWarehouses() as $sync) {
            foreach ([Item::TYPE_ITEM, Item::TYPE_ASSET_LANCAR] as $type) {
                $offset = ($job->scan_round + 1) * $job->per_type_limit;
                if ($this->linkedItemIds($sync->warehouse_id, $type, 1, $offset)->isNotEmpty()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return Collection<int, Item>
     */
    public function selectItemsForWarehouse(Jubeliosync $sync, int $perTypeLimit, int $demandDays, int $scanRound = 0): Collection
    {
        $selected = collect();

        foreach ([Item::TYPE_ITEM, Item::TYPE_ASSET_LANCAR] as $type) {
            if ($scanRound === 0) {
                $ids = $this->topDemandItemIds($sync->warehouse_id, $type, $perTypeLimit, $demandDays);

                if ($ids->count() < $perTypeLimit) {
                    $ids = $ids->merge(
                        $this->fallbackItemIds($sync->warehouse_id, $type, $perTypeLimit - $ids->count(), $ids->all()),
                    );
                }
            } else {
                $offset = $scanRound * $perTypeLimit;
                $ids = $this->linkedItemIds($sync->warehouse_id, $type, $perTypeLimit, $offset);
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
    public function topDemandItemIds(int $warehouseId, int $type, int $limit, int $demandDays): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        $since = now()->subDays($demandDays)->toDateString();

        return TransactionDetail::query()
            ->select('transaction_details.item_id', DB::raw('SUM(ABS(transaction_details.quantity)) as demand_qty'))
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->join('items', 'items.id', '=', 'transaction_details.item_id')
            ->where('transactions.type', Transaction::TYPE_SELL)
            ->where('transactions.sender_id', $warehouseId)
            ->whereDate('transactions.date', '>=', $since)
            ->where('items.type', $type)
            ->where('items.jubelio_item_id', '>', 0)
            ->groupBy('transaction_details.item_id')
            ->orderByDesc('demand_qty')
            ->limit($limit)
            ->pluck('transaction_details.item_id');
    }

    /**
     * @param  list<int>  $excludeIds
     * @return Collection<int, int>
     */
    public function fallbackItemIds(int $warehouseId, int $type, int $limit, array $excludeIds = []): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        return WarehouseItem::query()
            ->select('warehouse_item.item_id')
            ->join('items', 'items.id', '=', 'warehouse_item.item_id')
            ->where('warehouse_item.warehouse_id', $warehouseId)
            ->where('warehouse_item.quantity', '>', 0)
            ->where('items.type', $type)
            ->where('items.jubelio_item_id', '>', 0)
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('warehouse_item.item_id', $excludeIds))
            ->orderByDesc('warehouse_item.quantity')
            ->limit($limit)
            ->pluck('warehouse_item.item_id');
    }

    /**
     * @return Collection<int, int>
     */
    public function linkedItemIds(int $warehouseId, int $type, int $limit, int $offset = 0): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        return WarehouseItem::query()
            ->select('warehouse_item.item_id')
            ->join('items', 'items.id', '=', 'warehouse_item.item_id')
            ->where('warehouse_item.warehouse_id', $warehouseId)
            ->where('items.type', $type)
            ->where('items.jubelio_item_id', '>', 0)
            ->orderBy('warehouse_item.item_id')
            ->offset($offset)
            ->limit($limit)
            ->pluck('warehouse_item.item_id');
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

            $quantities = $this->resolveLocationQuantities($locationStock);
            $jubelioQty = $quantities['comparable'];
            $ariaQty = (float) ($ariaQtyByItemId[$item->id] ?? 0);

            $identity = [
                'jubelio_stock_check_id' => $job->id,
                'item_id' => $item->id,
                'warehouse_id' => $sync->warehouse_id,
            ];

            if ($ariaQty === $jubelioQty) {
                JubelioStockDiscrepancy::query()->where($identity)->delete();

                continue;
            }

            JubelioStockDiscrepancy::updateOrCreate($identity, [
                'jubelio_item_id' => $item->jubelio_item_id,
                'jubelio_location_id' => $sync->jubelio_location_id,
                'jubelio_location_name' => $sync->jubelio_location_name,
                'aria_qty' => $ariaQty,
                'jubelio_qty' => $jubelioQty,
                'jubelio_on_hand' => $quantities['on_hand'],
                'jubelio_on_order' => $quantities['on_order'],
                'jubelio_available' => $quantities['available'],
                'jubelio_reserved' => $quantities['reserved'],
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

    /**
     * Jubelio drops available when an order is placed (before Aria webhook). Compare
     * against available so webhook-lag rows like Aria 10 vs Jubelio 9 are caught.
     * On-hand may still read 10 while available is already 9.
     *
     * @param  array<string, mixed>  $locationStock
     * @return array{on_hand: float, on_order: float, reserved: float, available: float, comparable: float}
     */
    public function resolveLocationQuantities(array $locationStock): array
    {
        $onHand = (float) ($locationStock['on_hand'] ?? 0);
        $onOrder = (float) ($locationStock['on_order'] ?? 0);
        $reserved = (float) ($locationStock['reserved'] ?? 0);
        $available = array_key_exists('available', $locationStock)
            ? (float) $locationStock['available']
            : $onHand - $reserved;

        return [
            'on_hand' => $onHand,
            'on_order' => $onOrder,
            'reserved' => $reserved,
            'available' => $available,
            'comparable' => $available,
        ];
    }
}
