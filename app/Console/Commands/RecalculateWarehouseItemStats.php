<?php

namespace App\Console\Commands;

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

        $addrbookTable = (new \App\Models\Addrbook)->getTable();
        $stats = [];

        $this->info('Aggregating sell transactions (may take a minute on large databases)...');
        $sellStarted = microtime(true);
        $sellRows = DB::table('transaction_details')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->join($addrbookTable.' as wh', 'wh.id', '=', 'transaction_details.sender_id')
            ->where('transaction_details.transaction_type', $sellType)
            ->selectRaw("transaction_details.sender_id as warehouse_id, transaction_details.item_id, {$monthExpr} as month, {$yearExpr} as year, SUM(ABS(transaction_details.quantity)) as qty, SUM(transaction_details.total * (100 - COALESCE(transactions.discount, 0)) / 100) as value")
            ->groupBy('transaction_details.sender_id', 'transaction_details.item_id', DB::raw($monthExpr), DB::raw($yearExpr))
            ->get();
        $this->info(sprintf('  %d sell aggregate rows in %.1fs', $sellRows->count(), microtime(true) - $sellStarted));

        foreach ($sellRows as $row) {
            $this->accumulateStat($stats, $row, 'sold_qty', 'sold_value', (float) $row->qty, (float) $row->value);
        }

        $this->info('Aggregating return transactions...');
        $returnStarted = microtime(true);
        $returnRows = DB::table('transaction_details')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->join($addrbookTable.' as wh', 'wh.id', '=', 'transaction_details.receiver_id')
            ->where('transaction_details.transaction_type', $returnType)
            ->selectRaw("transaction_details.receiver_id as warehouse_id, transaction_details.item_id, {$monthExpr} as month, {$yearExpr} as year, SUM(ABS(transaction_details.quantity)) as qty, SUM(transaction_details.total * (100 - COALESCE(transactions.discount, 0)) / 100) as value")
            ->groupBy('transaction_details.receiver_id', 'transaction_details.item_id', DB::raw($monthExpr), DB::raw($yearExpr))
            ->get();
        $this->info(sprintf('  %d return aggregate rows in %.1fs', $returnRows->count(), microtime(true) - $returnStarted));

        foreach ($returnRows as $row) {
            $this->accumulateStat($stats, $row, 'returned_qty', 'returned_value', (float) $row->qty, (float) $row->value);
        }

        if ($stats === []) {
            $this->info('No monthly stat rows to store.');

            return self::SUCCESS;
        }

        $itemIds = array_values(array_unique(array_column($stats, 'item_id')));
        $this->info(sprintf('Resolving dimensions for %d items...', count($itemIds)));
        $resolveStarted = microtime(true);
        $resolved = $dimensions->resolveMany($itemIds);
        $this->info(sprintf('  resolved in %.1fs', microtime(true) - $resolveStarted));

        $now = now();
        $insertRows = [];
        foreach ($stats as $stat) {
            $dims = $resolved[$stat['item_id']] ?? null;
            if (! $dims) {
                continue;
            }

            $insertRows[] = array_merge($dims, [
                'warehouse_id' => $stat['warehouse_id'],
                'item_id' => $stat['item_id'],
                'month' => $stat['month'],
                'year' => $stat['year'],
                'sold_qty' => $stat['sold_qty'],
                'sold_value' => $stat['sold_value'],
                'returned_qty' => $stat['returned_qty'],
                'returned_value' => $stat['returned_value'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->info(sprintf('Inserting %d monthly stat rows...', count($insertRows)));
        $insertStarted = microtime(true);
        $bar = $this->output->createProgressBar(count($insertRows));
        $bar->start();

        foreach (array_chunk($insertRows, 500) as $chunk) {
            DB::table('warehouse_item_monthly_stats')->insert($chunk);
            $bar->advance(count($chunk));
        }

        $bar->finish();
        $this->newLine();
        $this->info(sprintf('  inserted in %.1fs', microtime(true) - $insertStarted));

        $total = WarehouseItemMonthlyStat::count();
        $this->info("Done. {$total} monthly stat rows stored.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array<string, mixed>>  $stats
     */
    private function accumulateStat(
        array &$stats,
        object $row,
        string $qtyColumn,
        string $valueColumn,
        float $qty,
        float $value,
    ): void {
        if ($qty <= 0 && $value <= 0) {
            return;
        }

        $key = $row->warehouse_id.'|'.$row->item_id.'|'.$row->month.'|'.$row->year;

        if (! isset($stats[$key])) {
            $stats[$key] = [
                'warehouse_id' => (int) $row->warehouse_id,
                'item_id' => (int) $row->item_id,
                'month' => (int) $row->month,
                'year' => (int) $row->year,
                'sold_qty' => 0.0,
                'sold_value' => 0.0,
                'returned_qty' => 0.0,
                'returned_value' => 0.0,
            ];
        }

        $stats[$key][$qtyColumn] += $qty;
        $stats[$key][$valueColumn] += $value;
    }
}
