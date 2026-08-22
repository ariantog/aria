<?php

namespace App\Jobs;

use App\Models\Addrbook;
use App\Models\DailyInventorySummary;
use App\Models\MonthlyAccountSummary;
use App\Models\MonthlyCategorySummary;
use App\Models\Transaction;
use App\Services\WarehouseItemStatsRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class UpdateTransactionSummaries implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $transactionId) {}

    public function handle(WarehouseItemStatsRecorder $statsRecorder): void
    {
        $transaction = Transaction::with('details')->find($this->transactionId);
        if (! $transaction || (int) $transaction->status !== Transaction::STATUS_COMPLETED) {
            return;
        }

        $year = $transaction->date->year;
        $month = $transaction->date->month;
        $date = $transaction->date->toDateString();

        $this->updateAccountSummary($transaction, $year, $month);
        $this->updateCategorySummary($transaction, $year, $month);
        $this->updateInventorySummary($transaction, $date);
        $this->updateStatSell($transaction);
        foreach ($transaction->details as $detail) {
            $statsRecorder->recordDetail($transaction, $detail);
        }
    }

    private function updateAccountSummary(Transaction $transaction, int $year, int $month): void
    {
        $type = (int) $transaction->type;
        $targetId = match ($type) {
            Transaction::TYPE_CASH_IN, Transaction::TYPE_RETURN => $transaction->sender_id,
            Transaction::TYPE_CASH_OUT, Transaction::TYPE_SELL => $transaction->receiver_id,
            default => null,
        };
        if (! $targetId) {
            return;
        }
        $summary = MonthlyAccountSummary::firstOrCreate(['year' => $year, 'month' => $month, 'customer_id' => $targetId]);
        $column = match ($type) {
            Transaction::TYPE_CASH_IN => 'cash_in',
            Transaction::TYPE_CASH_OUT => 'cash_out',
            Transaction::TYPE_SELL => 'sell',
            Transaction::TYPE_RETURN => 'return',
            default => null,
        };
        if ($column) {
            $summary->increment($column, (float) $transaction->total);
        }
    }

    private function updateCategorySummary(Transaction $transaction, int $year, int $month): void
    {
        $type = (int) $transaction->type;
        $targetType = match ($type) {
            Transaction::TYPE_CASH_IN, Transaction::TYPE_RETURN => $transaction->sender_type,
            Transaction::TYPE_CASH_OUT, Transaction::TYPE_BUY, Transaction::TYPE_SELL, Transaction::TYPE_RETURN_SUPPLIER => $transaction->receiver_type,
            default => null,
        };
        if (! $targetType) {
            return;
        }
        $summary = MonthlyCategorySummary::firstOrCreate(['year' => $year, 'month' => $month, 'addrbook_type' => $targetType]);
        $column = match ($type) {
            Transaction::TYPE_CASH_IN => 'cash_in',
            Transaction::TYPE_CASH_OUT => 'cash_out',
            Transaction::TYPE_SELL => 'sell',
            Transaction::TYPE_BUY => 'buy',
            Transaction::TYPE_RETURN => 'return',
            Transaction::TYPE_RETURN_SUPPLIER => 'return_supplier',
            default => null,
        };
        if ($column) {
            $summary->increment($column, (float) $transaction->total);
        }
    }

    private function updateInventorySummary(Transaction $transaction, string $date): void
    {
        foreach ($transaction->details as $detail) {
            if (Addrbook::typeIsWarehouse((int) $transaction->sender_type)) {
                $this->incrInv($date, $transaction->sender_id, $detail->item_id, (int) $transaction->type, $detail->quantity, 'sender');
            }
            if (Addrbook::typeIsWarehouse((int) $transaction->receiver_type)) {
                $this->incrInv($date, $transaction->receiver_id, $detail->item_id, (int) $transaction->type, $detail->quantity, 'receiver');
            }
        }
    }

    private function incrInv(string $date, int $wid, int $iid, int $type, float $qty, string $side): void
    {
        $s = DailyInventorySummary::firstOrCreate(['date' => $date, 'warehouse_id' => $wid, 'item_id' => $iid]);
        match ($type) {
            Transaction::TYPE_SELL => $s->increment('qty_sell', $qty),
            Transaction::TYPE_BUY => $s->increment('qty_buy', $qty),
            Transaction::TYPE_MOVE => $side === 'receiver' ? $s->increment('qty_move_in', $qty) : $s->increment('qty_move_out', $qty),
            Transaction::TYPE_RETURN => $s->increment('qty_return_in', $qty),
            Transaction::TYPE_RETURN_SUPPLIER => $s->increment('qty_return_out', $qty),
            Transaction::TYPE_ADJUST => $qty > 0 ? $s->increment('qty_adjust_in', $qty) : $s->increment('qty_adjust_out', abs($qty)),
            default => null,
        };
    }

    private function updateStatSell(Transaction $transaction): void
    {
        if (! in_array((int) $transaction->type, [Transaction::TYPE_SELL, Transaction::TYPE_RETURN], true)) {
            return;
        }
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $monthExpr = "CAST(strftime('%m', transaction_details.date) AS INTEGER)";
            $yearExpr = "CAST(strftime('%Y', transaction_details.date) AS INTEGER)";
        } else {
            $monthExpr = 'MONTH(transaction_details.date)';
            $yearExpr = 'YEAR(transaction_details.date)';
        }
        $result = DB::table('transaction_details')->where('transaction_details.transaction_id', $transaction->id)
            ->join('items', 'transaction_details.item_id', '=', 'items.id')
            ->selectRaw("items.group_id, {$monthExpr} as bulan, {$yearExpr} as tahun, transaction_details.sender_id, transaction_details.transaction_type as type, SUM(transaction_details.quantity) as sum_qty, SUM(transaction_details.total) as sum_total")
            ->groupBy('items.group_id', DB::raw($monthExpr), DB::raw($yearExpr), 'transaction_details.sender_id', 'transaction_details.transaction_type')->get();
        foreach ($result as $row) {
            DB::table('stat_sells')->updateOrInsert(
                ['group_id' => $row->group_id, 'bulan' => $row->bulan, 'tahun' => $row->tahun, 'sender_id' => $row->sender_id, 'type' => $row->type],
                ['sum_qty' => DB::raw('sum_qty + '.(float) $row->sum_qty), 'sum_total' => DB::raw('sum_total + '.(float) $row->sum_total), 'updated_at' => now()]);
        }
    }
}
