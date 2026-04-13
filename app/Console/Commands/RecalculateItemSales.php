<?php

namespace App\Console\Commands;

use App\Models\MonthlyItemSale;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecalculateItemSales extends Command
{
    protected $signature = 'app:recalculate-item-sales {--year= : Specific year to recalculate}';

    protected $description = 'Recalculate monthly item sale summaries (Net Sell Logic)';

    public function handle()
    {
        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        $yearOption = $this->option('year');
        $this->info('Starting recalculation of Monthly Item Sales (Clean Refactor)...');
        $start = microtime(true);

        Schema::disableForeignKeyConstraints();

        try {
            if ($yearOption) {
                $this->info("Cleaning old data for year $yearOption...");
                MonthlyItemSale::where('year', $yearOption)->delete();
            } else {
                $this->info("Resetting entire summary table...");
                MonthlyItemSale::truncate();
            }

            $years = DB::table('transactions')
                ->when($yearOption, fn($q) => $q->whereYear('date', $yearOption))
                ->selectRaw('DISTINCT YEAR(date) as year')
                ->pluck('year');

            $typeSell = Transaction::TYPE_SELL;
            $typeReturn = Transaction::TYPE_RETURN;

            foreach ($years as $year) {
                $this->info("Processing year $year...");
                
                $results = DB::table('transaction_details as td')
                    ->join('transactions as t', 'td.transaction_id', '=', 't.id')
                    ->join('items as i', 'td.item_id', '=', 'i.id')
                    ->whereIn('t.type', [$typeSell, $typeReturn])
                    ->whereYear('t.date', $year)
                    ->selectRaw("
                        YEAR(t.date) as tahun,
                        MONTH(t.date) as bulan,
                        i.group_id,
                        CASE 
                            WHEN t.type = $typeSell THEN t.receiver_id 
                            WHEN t.type = $typeReturn THEN t.sender_id 
                        END as customer_id,
                        SUM(CASE 
                            WHEN t.type = $typeSell THEN td.quantity 
                            WHEN t.type = $typeReturn THEN -td.quantity 
                            ELSE 0 
                        END) as net_qty,
                        SUM(CASE 
                            WHEN t.type = $typeSell THEN td.total 
                            WHEN t.type = $typeReturn THEN -td.total 
                            ELSE 0 
                        END) as net_amount
                    ")
                    ->groupBy('tahun', 'bulan', 'i.group_id', 'customer_id')
                    ->get();

                if ($results->isEmpty()) {
                    continue;
                }

                $this->output->progressStart($results->count());

                foreach ($results->chunk(1000) as $chunk) {
                    $insertData = [];
                    foreach ($chunk as $row) {
                        $insertData[] = [
                            'year' => $row->tahun,
                            'month' => $row->bulan,
                            'group_id' => $row->group_id,
                            'customer_id' => $row->customer_id, 
                            'qty_net' => $row->net_qty,
                            'amount_net' => $row->net_amount,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $this->output->progressAdvance();
                    }

                    DB::table('monthly_item_sales')->insert($insertData);
                }

                $this->output->progressFinish();
            }

            $time = round(microtime(true) - $start, 2);
            $this->info("Recalculation completed successfully in {$time} seconds.");

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
