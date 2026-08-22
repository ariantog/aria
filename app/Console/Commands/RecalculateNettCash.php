<?php

namespace App\Console\Commands;

use App\Models\MonthlyAccountSummary;
use App\Models\Transaction;
use Illuminate\Console\Command;

class RecalculateNettCash extends Command
{
    protected $signature = 'app:recalculate-nett-cash';

    protected $description = 'Recalculate monthly account summaries using legacy logic (Proper one-sided attribution)';

    public function handle()
    {
        $this->info('Starting recalculation using one-sided attribution logic...');
        MonthlyAccountSummary::truncate();

        // In legacy, reports for Customers/Resellers look at:
        // Cash In: Sender ID (Where the money comes from)
        // Cash Out: Receiver ID (Where the money goes to)
        // Sell: Receiver ID (Who bought the item)
        // Return: Sender ID (Who returned the item)

        $this->processSide('sender', [Transaction::TYPE_CASH_IN, Transaction::TYPE_RETURN]);
        $this->processSide('receiver', [Transaction::TYPE_CASH_OUT, Transaction::TYPE_SELL]);

        $this->info('Recalculation completed.');
    }

    protected function processSide($side, array $types)
    {
        $this->info("Processing $side side for types: ".implode(',', $types));
        $idCol = "{$side}_id";

        $results = Transaction::selectRaw("
            YEAR(date) as year,
            MONTH(date) as month,
            $idCol as customer_id,
            SUM(CASE WHEN type = ".Transaction::TYPE_CASH_IN.' THEN total ELSE 0 END) as cash_in,
            SUM(CASE WHEN type = '.Transaction::TYPE_CASH_OUT.' THEN total ELSE 0 END) as cash_out,
            SUM(CASE WHEN type = '.Transaction::TYPE_SELL.' THEN total ELSE 0 END) as sell,
            SUM(CASE WHEN type = '.Transaction::TYPE_RETURN.' THEN total ELSE 0 END) as `return`
        ')
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereIn('type', $types)
            ->groupBy('year', 'month', $idCol)
            ->get();

        foreach ($results as $row) {
            if (! $row->customer_id) {
                continue;
            }

            $summary = MonthlyAccountSummary::firstOrNew([
                'year' => $row->year,
                'month' => $row->month,
                'customer_id' => $row->customer_id,
            ]);

            $summary->cash_in += $row->cash_in;
            $summary->cash_out += $row->cash_out;
            $summary->sell += $row->sell;
            $summary->return += $row->return;

            $summary->save();
        }
    }
}
