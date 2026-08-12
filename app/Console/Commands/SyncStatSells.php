<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStatSells extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-stat-sells {--refresh : Truncate the table before syncing}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Sync item sales statistics from transaction details';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('refresh')) {
            $this->info('Refreshing stat_sells table...');
            DB::table('stat_sells')->truncate();
        }

        $this->info('Calculating aggregates...');

        $query = DB::table('transaction_details as td')
            ->join('items as i', 'td.item_id', '=', 'i.id')
            ->leftJoin('item_group as ig', 'i.group_id', '=', 'ig.id')
            ->leftJoin('customers as a', 'td.sender_id', '=', 'a.id')
            ->whereIn('td.transaction_type', [Transaction::TYPE_SELL, Transaction::TYPE_RETURN])
            ->selectRaw('
                ig.id as group_id,
                MONTH(td.date) as bulan,
                YEAR(td.date) as tahun,
                a.id as sender_id,
                td.transaction_type as type,
                SUM(td.quantity) as sum_qty,
                SUM(td.total) as sum_total
            ')
            ->groupBy('ig.id', 'bulan', 'tahun', 'a.id', 'td.transaction_type');

        $results = $query->get();
        $total = $results->count();
        $this->info("Found {$total} aggregate rows to sync.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $results->chunk(1000)->each(function ($chunk) use ($bar) {
            $data = $chunk->map(function ($row) {
                return [
                    'group_id' => $row->group_id,
                    'bulan' => $row->bulan,
                    'tahun' => $row->tahun,
                    'sender_id' => $row->sender_id,
                    'type' => $row->type,
                    'sum_qty' => (float) $row->sum_qty,
                    'sum_total' => (float) $row->sum_total,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            DB::table('stat_sells')->upsert(
                $data,
                ['group_id', 'bulan', 'tahun', 'sender_id', 'type'],
                ['sum_qty', 'sum_total', 'updated_at']
            );

            $bar->advance(count($data));
        });

        $bar->finish();
        $this->newLine();
        $this->info('Sync completed successfully.');
    }
}
