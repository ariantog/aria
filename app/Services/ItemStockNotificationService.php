<?php

namespace App\Services;

use App\Enums\AddrbookType;
use App\Enums\ItemStockSourceStatus;
use App\Models\Addrbook;
use App\Models\ItemStockNotification;
use App\Models\Transaction;
use App\Models\WarehouseItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ItemStockNotificationService
{
    /**
     * @return list<int> Notification ids created or refreshed
     */
    public function checkAfterSell(Transaction $transaction): array
    {
        if ((int) $transaction->type !== Transaction::TYPE_SELL) {
            return [];
        }

        if ((int) $transaction->status !== Transaction::STATUS_COMPLETED) {
            return [];
        }

        $soldOutWarehouseId = (int) $transaction->sender_id;
        if ($soldOutWarehouseId <= 0) {
            return [];
        }

        $soldOutWarehouse = Addrbook::query()->find($soldOutWarehouseId);
        if (! $soldOutWarehouse
            || (int) $soldOutWarehouse->type !== AddrbookType::Warehouse->value
            || ! $soldOutWarehouse->arrangement_enabled) {
            return [];
        }

        $createdIds = [];

        foreach ($transaction->details as $detail) {
            $itemId = (int) $detail->item_id;
            if ($itemId <= 0) {
                continue;
            }

            $stockAtSoldOut = WarehouseItem::query()
                ->where('warehouse_id', $soldOutWarehouseId)
                ->where('item_id', $itemId)
                ->value('quantity');

            if ($stockAtSoldOut !== null && (float) $stockAtSoldOut > 0) {
                continue;
            }

            $sourceWarehouses = WarehouseItem::query()
                ->where('item_id', $itemId)
                ->where('warehouse_id', '!=', $soldOutWarehouseId)
                ->where('quantity', '>', 0)
                ->whereHas('warehouse', function ($query) {
                    $query->where('type', AddrbookType::Warehouse->value)->whereNull('deleted_at');
                })
                ->with('warehouse:id,name')
                ->get();

            foreach ($sourceWarehouses as $sourceRow) {
                $sourceWarehouseId = (int) $sourceRow->warehouse_id;
                $sourceStock = (float) $sourceRow->quantity;
                $status = $this->classifySourceStatus($sourceWarehouseId, $itemId, $sourceStock);

                $notification = $this->upsertNotification(
                    $itemId,
                    $soldOutWarehouseId,
                    $sourceWarehouseId,
                    $sourceStock,
                    $status,
                    (int) $transaction->id,
                );

                $createdIds[] = $notification->id;
            }
        }

        return $createdIds;
    }

    public function classifySourceStatus(int $warehouseId, int $itemId, float $stock): ItemStockSourceStatus
    {
        if ($stock <= 0) {
            return ItemStockSourceStatus::Available;
        }

        $sold30 = $this->soldQuantityForDays($warehouseId, $itemId, 30);
        $sold90 = $this->soldQuantityForDays($warehouseId, $itemId, 90);
        $lastSoldAt = $this->lastSoldDate($warehouseId, $itemId);

        $sold30 = abs((float) $sold30);
        $sold90 = abs((float) $sold90);

        if ($sold90 === 0.0) {
            return ItemStockSourceStatus::DeadStock;
        }

        $daysSinceSale = $lastSoldAt
            ? Carbon::parse($lastSoldAt)->diffInDays(now())
            : 999;

        if ($sold30 > 0) {
            return ItemStockSourceStatus::Available;
        }

        if ($daysSinceSale > 30) {
            return ItemStockSourceStatus::SlowMoving;
        }

        return ItemStockSourceStatus::Available;
    }

    public function unreadCount(): int
    {
        return ItemStockNotification::query()->unread()->count();
    }

    private function soldQuantityForDays(int $warehouseId, int $itemId, int $days): float
    {
        $date = now()->subDays($days)->toDateString();

        return (float) DB::table('transaction_details')
            ->where('item_id', $itemId)
            ->where('transaction_type', Transaction::TYPE_SELL)
            ->where('sender_id', $warehouseId)
            ->where('date', '>=', $date)
            ->sum('quantity');
    }

    private function lastSoldDate(int $warehouseId, int $itemId): ?string
    {
        return DB::table('transaction_details')
            ->where('item_id', $itemId)
            ->where('transaction_type', Transaction::TYPE_SELL)
            ->where('sender_id', $warehouseId)
            ->max('date');
    }

    private function upsertNotification(
        int $itemId,
        int $soldOutWarehouseId,
        int $sourceWarehouseId,
        float $sourceStock,
        ItemStockSourceStatus $status,
        int $triggerTransactionId,
    ): ItemStockNotification {
        $existing = ItemStockNotification::query()
            ->where('item_id', $itemId)
            ->where('sold_out_warehouse_id', $soldOutWarehouseId)
            ->where('source_warehouse_id', $sourceWarehouseId)
            ->first();

        if ($existing && $existing->dismissed_at !== null) {
            $existing->update([
                'source_stock' => $sourceStock,
                'source_status' => $status,
                'trigger_transaction_id' => $triggerTransactionId,
                'read_at' => null,
                'dismissed_at' => null,
            ]);

            return $existing->fresh();
        }

        return ItemStockNotification::query()->updateOrCreate(
            [
                'item_id' => $itemId,
                'sold_out_warehouse_id' => $soldOutWarehouseId,
                'source_warehouse_id' => $sourceWarehouseId,
            ],
            [
                'source_stock' => $sourceStock,
                'source_status' => $status,
                'trigger_transaction_id' => $triggerTransactionId,
                'read_at' => null,
                'dismissed_at' => null,
            ],
        );
    }
}
