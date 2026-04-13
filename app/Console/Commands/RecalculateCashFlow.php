<?php

namespace App\Console\Commands;

use App\Models\MonthlyCategorySummary;
use App\Models\Transaction;
use Illuminate\Console\Command;

class RecalculateCashFlow extends Command
{
    protected $signature = 'app:recalculate-cash-flow';
    protected $description = 'Recalculate monthly category summaries using legacy logic (total column, net aggregation)';

    public function handle()
    {
        $this->info('Starting recalculation using legacy logic...');
        MonthlyCategorySummary::truncate();

        $this->processSide('sender');
        $this->processSide('receiver');

        $this->info('Recalculation completed.');
    }

    protected function processSide($side)
    {
        $this->info("Processing $side side...");
        $typeCol = "{$side}_type";

        $results = Transaction::selectRaw("
            YEAR(date) as year,
            MONTH(date) as month,
            $typeCol as addrbook_type,
            SUM(CASE WHEN type = ".Transaction::TYPE_CASH_IN." THEN total ELSE 0 END) as cash_in,
            SUM(CASE WHEN type = ".Transaction::TYPE_CASH_OUT." THEN total ELSE 0 END) as cash_out,
            SUM(CASE WHEN type = ".Transaction::TYPE_SELL." THEN total ELSE 0 END) as sell,
            SUM(CASE WHEN type = ".Transaction::TYPE_BUY." THEN total ELSE 0 END) as buy,
            SUM(CASE WHEN type = ".Transaction::TYPE_RETURN." THEN total ELSE 0 END) as `return`,
            SUM(CASE WHEN type = ".Transaction::TYPE_RETURN_SUPPLIER." THEN total ELSE 0 END) as return_supplier
        ")
        ->where('status', Transaction::STATUS_COMPLETED)
        ->whereIn('type', [
            Transaction::TYPE_CASH_IN,
            Transaction::TYPE_CASH_OUT,
            Transaction::TYPE_SELL,
            Transaction::TYPE_BUY,
            Transaction::TYPE_RETURN,
            Transaction::TYPE_RETURN_SUPPLIER
        ])
        ->groupBy('year', 'month', $typeCol)
        ->get();

        foreach ($results as $row) {
            if (!$row->addrbook_type) continue;

            $summary = MonthlyCategorySummary::firstOrNew([
                'year' => $row->year,
                'month' => $row->month,
                'addrbook_type' => $row->addrbook_type,
            ]);

            $summary->cash_in += $row->cash_in;
            $summary->cash_out += $row->cash_out;
            $summary->sell += $row->sell;
            $summary->buy += $row->buy;
            $summary->return += $row->return;
            $summary->return_supplier += $row->return_supplier;

            $summary->save();
        }
    }
}
