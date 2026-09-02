<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\WarehouseItemMonthlyStat;
use App\Services\Items\ItemDimensionResolver;

class WarehouseItemStatsRecorder
{
    public function __construct(private readonly ItemDimensionResolver $dimensions) {}

    public function recordDetail(Transaction $transaction, object $detail): void
    {
        $this->applyDetail($transaction, $detail, revert: false);
    }

    public function revertDetail(Transaction $transaction, object $detail): void
    {
        $this->applyDetail($transaction, $detail, revert: true);
    }

    public function revertTransaction(Transaction $transaction): void
    {
        $transaction->loadMissing('details');

        foreach ($transaction->details as $detail) {
            $this->revertDetail($transaction, $detail);
        }
    }

    protected function applyDetail(Transaction $transaction, object $detail, bool $revert): void
    {
        $type = (int) $transaction->type;
        if (! in_array($type, [Transaction::TYPE_SELL, Transaction::TYPE_RETURN], true)) {
            return;
        }

        $warehouseId = match ($type) {
            Transaction::TYPE_SELL => (int) ($detail->sender_id ?: $transaction->sender_id),
            Transaction::TYPE_RETURN => (int) ($detail->receiver_id ?: $transaction->receiver_id),
        };

        if ($warehouseId <= 0) {
            return;
        }

        $date = isset($detail->date) ? \Illuminate\Support\Carbon::parse($detail->date) : $transaction->date;
        $headerDiscount = max(0.0, min(100.0, (float) ($transaction->discount ?? 0)));
        $lineTotal = (float) ($detail->total ?? 0);
        $netValue = $lineTotal * (100 - $headerDiscount) / 100;
        $qty = abs((float) ($detail->quantity ?? 0));

        // Loaded through the resolver so legacy rows whose items.type is no longer a
        // valid ItemType (e.g. 4) do not blow up the queued summary job.
        $item = $this->dimensions->findItem((int) $detail->item_id);
        if (! $item) {
            return;
        }

        $dims = $this->dimensions->resolve($item);

        $keys = [
            'warehouse_id' => $warehouseId,
            'item_id' => (int) $detail->item_id,
            'month' => $date->month,
            'year' => $date->year,
        ];

        if ($revert) {
            $stat = WarehouseItemMonthlyStat::query()->where($keys)->first();
            if (! $stat) {
                return;
            }
        } else {
            $stat = WarehouseItemMonthlyStat::updateOrCreate($keys, $dims);
        }

        $qtyColumn = $type === Transaction::TYPE_SELL ? 'sold_qty' : 'returned_qty';
        $valueColumn = $type === Transaction::TYPE_SELL ? 'sold_value' : 'returned_value';
        $direction = $revert ? -1.0 : 1.0;

        $stat->{$qtyColumn} = max(0, (float) ($stat->{$qtyColumn} ?? 0) + ($direction * $qty));
        $stat->{$valueColumn} = max(0, (float) ($stat->{$valueColumn} ?? 0) + ($direction * $netValue));
        $stat->save();
    }
}
