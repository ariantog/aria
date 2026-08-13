<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Transaction;
use App\Models\WarehouseItemMonthlyStat;
use App\Services\Items\ItemDimensionResolver;

class WarehouseItemStatsRecorder
{
    public function __construct(private readonly ItemDimensionResolver $dimensions) {}

    public function recordDetail(Transaction $transaction, object $detail): void
    {
        $type = (int) $transaction->type;
        if (! in_array($type, [Transaction::TYPE_SELL, Transaction::TYPE_RETURN], true)) {
            return;
        }

        $warehouseId = match ($type) {
            Transaction::TYPE_SELL => (int) $transaction->sender_id,
            Transaction::TYPE_RETURN => (int) $transaction->receiver_id,
        };

        if ($warehouseId <= 0) {
            return;
        }

        $date = isset($detail->date) ? \Illuminate\Support\Carbon::parse($detail->date) : $transaction->date;
        $headerDiscount = max(0.0, min(100.0, (float) ($transaction->discount ?? 0)));
        $lineTotal = (float) ($detail->total ?? 0);
        $netValue = $lineTotal * (100 - $headerDiscount) / 100;
        $qty = abs((float) ($detail->quantity ?? 0));

        $item = Item::with(['tags', 'group'])->find($detail->item_id);
        if (! $item) {
            return;
        }

        $dims = $this->dimensions->resolve($item);

        $stat = WarehouseItemMonthlyStat::updateOrCreate(
            [
                'warehouse_id' => $warehouseId,
                'item_id' => (int) $detail->item_id,
                'month' => $date->month,
                'year' => $date->year,
            ],
            $dims,
        );

        if ($type === Transaction::TYPE_SELL) {
            $stat->increment('sold_qty', $qty);
            $stat->increment('sold_value', $netValue);
        } else {
            $stat->increment('returned_qty', $qty);
            $stat->increment('returned_value', $netValue);
        }
    }
}
