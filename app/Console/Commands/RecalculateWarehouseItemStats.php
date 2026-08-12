<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\Transaction;
use App\Models\WarehouseItemMonthlyStat;
use App\Services\Items\ItemDimensionResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateWarehouseItemStats extends Command
{
    protected $signature = 'app:recalculate-warehouse-item-stats';

    protected $description = 'Rebuild per-warehouse per-SKU monthly sell/return statistics';

    public function handle(ItemDimensionResolver $dimensions): int
    {
        $this->info('Clearing warehouse item monthly stats...');
        WarehouseItemMonthlyStat::query()->delete();

        $sellType = Transaction::TYPE_SELL;
        $returnType = Transaction::TYPE_RETURN;
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $monthExpr = 'CAST(strftime(\'%m\', transaction_details.date) AS INTEGER)';
            $yearExpr = 'CAST(strftime(\'%Y\', transaction_details.date) AS INTEGER)';
        } else {
            $monthExpr = 'MONTH(transaction_details.date)';
            $yearExpr = 'YEAR(transaction_details.date)';
        }

        $this->info('Aggregating sell transactions...');
        $sellRows = DB::table('transaction_details')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->where('transaction_details.transaction_type', $sellType)
            ->whereNotNull('transaction_details.sender_id')
            ->selectRaw("transaction_details.sender_id as warehouse_id, transaction_details.item_id, {$monthExpr} as month, {$yearExpr} as year, SUM(ABS(transaction_details.quantity)) as qty, SUM(transaction_details.total * (100 - COALESCE(transactions.discount, 0)) / 100) as value")
            ->groupBy('transaction_details.sender_id', 'transaction_details.item_id', DB::raw($monthExpr), DB::raw($yearExpr))
            ->get();

        foreach ($sellRows as $row) {
            $this->upsertStat($dimensions, (int) $row->warehouse_id, (int) $row->item_id, (int) $row->month, (int) $row->year, 'sold_qty', 'sold_value', (float) $row->qty, (float) $row->value);
        }

        $this->info('Aggregating return transactions...');
        $returnRows = DB::table('transaction_details')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->where('transaction_details.transaction_type', $returnType)
            ->whereNotNull('transaction_details.receiver_id')
            ->selectRaw("transaction_details.receiver_id as warehouse_id, transaction_details.item_id, {$monthExpr} as month, {$yearExpr} as year, SUM(ABS(transaction_details.quantity)) as qty, SUM(transaction_details.total * (100 - COALESCE(transactions.discount, 0)) / 100) as value")
            ->groupBy('transaction_details.receiver_id', 'transaction_details.item_id', DB::raw($monthExpr), DB::raw($yearExpr))
            ->get();

        foreach ($returnRows as $row) {
            $this->upsertStat($dimensions, (int) $row->warehouse_id, (int) $row->item_id, (int) $row->month, (int) $row->year, 'returned_qty', 'returned_value', (float) $row->qty, (float) $row->value);
        }

        $total = WarehouseItemMonthlyStat::count();
        $this->info("Done. {$total} monthly stat rows stored.");

        return self::SUCCESS;
    }

    private function upsertStat(
        ItemDimensionResolver $dimensions,
        int $warehouseId,
        int $itemId,
        int $month,
        int $year,
        string $qtyColumn,
        string $valueColumn,
        float $qty,
        float $value,
    ): void {
        if ($qty <= 0 && $value <= 0) {
            return;
        }

        $item = Item::with(['tags', 'group'])->find($itemId);
        if (! $item) {
            return;
        }

        $dims = $dimensions->resolve($item);

        $stat = WarehouseItemMonthlyStat::firstOrCreate([
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
            'month' => $month,
            'year' => $year,
        ], $dims);

        if (! $stat->wasRecentlyCreated) {
            $stat->fill($dims);
            $stat->save();
        }

        if ($qty > 0) {
            $stat->increment($qtyColumn, $qty);
        }
        if ($value > 0) {
            $stat->increment($valueColumn, $value);
        }
    }
}
