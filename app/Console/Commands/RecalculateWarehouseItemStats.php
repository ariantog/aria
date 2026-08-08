<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\WarehouseItemMonthlyStat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateWarehouseItemStats extends Command
{
    protected $signature = 'app:recalculate-warehouse-item-stats';

    protected $description = 'Rebuild per-warehouse per-SKU monthly sell/return statistics';

    public function handle(): int
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
            ->where('transaction_type', $sellType)
            ->whereNotNull('sender_id')
            ->selectRaw("sender_id as warehouse_id, item_id, {$monthExpr} as month, {$yearExpr} as year, SUM(ABS(quantity)) as qty")
            ->groupBy('sender_id', 'item_id', DB::raw($monthExpr), DB::raw($yearExpr))
            ->get();

        foreach ($sellRows as $row) {
            $this->upsertStat((int) $row->warehouse_id, (int) $row->item_id, (int) $row->month, (int) $row->year, 'sold_qty', (float) $row->qty);
        }

        $this->info('Aggregating return transactions...');
        $returnRows = DB::table('transaction_details')
            ->where('transaction_type', $returnType)
            ->whereNotNull('receiver_id')
            ->selectRaw("receiver_id as warehouse_id, item_id, {$monthExpr} as month, {$yearExpr} as year, SUM(ABS(quantity)) as qty")
            ->groupBy('receiver_id', 'item_id', DB::raw($monthExpr), DB::raw($yearExpr))
            ->get();

        foreach ($returnRows as $row) {
            $this->upsertStat((int) $row->warehouse_id, (int) $row->item_id, (int) $row->month, (int) $row->year, 'returned_qty', (float) $row->qty);
        }

        $total = WarehouseItemMonthlyStat::count();
        $this->info("Done. {$total} monthly stat rows stored.");

        return self::SUCCESS;
    }

    private function upsertStat(int $warehouseId, int $itemId, int $month, int $year, string $column, float $qty): void
    {
        if ($qty <= 0) {
            return;
        }

        $stat = WarehouseItemMonthlyStat::firstOrCreate([
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
            'month' => $month,
            'year' => $year,
        ]);

        $stat->increment($column, $qty);
    }
}
