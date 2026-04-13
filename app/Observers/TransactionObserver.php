<?php

namespace App\Observers;

use App\Models\Addrbook;
use App\Models\DailyInventorySummary;
use App\Models\MonthlyAccountSummary;
use App\Models\MonthlyCategorySummary;
use App\Models\Transaction;

class TransactionObserver
{
    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        if ($transaction->status === Transaction::STATUS_COMPLETED) {
            $this->updateSummaries($transaction);
        }
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        if ($transaction->isDirty('status') && $transaction->status === Transaction::STATUS_COMPLETED) {
            $this->updateSummaries($transaction);
        }
    }

    /**
     * Update all relevant report summaries for a transaction.
     */
    protected function updateSummaries(Transaction $transaction): void
    {
        $year = $transaction->date->year;
        $month = $transaction->date->month;
        $date = $transaction->date->toDateString();

        // 1. Update Monthly Account Summaries (Nett Cash - attribution based on role)
        $this->updateAccountSummary($transaction, $year, $month);

        // 2. Update Monthly Category Summaries (Cash Flow - attribution based on role)
        $this->updateCategorySummary($transaction, $year, $month);

        // 3. Update Daily Inventory Summaries (Stock Analysis)
        $this->updateInventorySummary($transaction, $date);
    }

    protected function updateAccountSummary(Transaction $transaction, $year, $month): void
    {
        // One-sided attribution to match legacy reporting logic
        $targetId = null;
        
        switch ($transaction->type) {
            case Transaction::TYPE_CASH_IN:
            case Transaction::TYPE_RETURN:
                $targetId = $transaction->sender_id;
                break;
            case Transaction::TYPE_CASH_OUT:
            case Transaction::TYPE_SELL:
                $targetId = $transaction->receiver_id;
                break;
        }

        if ($targetId) {
            $summary = MonthlyAccountSummary::firstOrCreate([
                'year' => $year,
                'month' => $month,
                'addrbook_id' => $targetId,
            ]);

            $value = (float) $transaction->total;

            if ($transaction->type === Transaction::TYPE_CASH_IN) {
                $summary->increment('cash_in', $value);
            } elseif ($transaction->type === Transaction::TYPE_CASH_OUT) {
                $summary->increment('cash_out', $value);
            } elseif ($transaction->type === Transaction::TYPE_SELL) {
                $summary->increment('sell', $value);
            } elseif ($transaction->type === Transaction::TYPE_RETURN) {
                $summary->increment('return', $value);
            }
        }
    }

    protected function updateCategorySummary(Transaction $transaction, $year, $month): void
    {
        // One-sided attribution to match legacy reporting logic
        $targetType = null;

        switch ($transaction->type) {
            case Transaction::TYPE_CASH_IN:
            case Transaction::TYPE_RETURN:
                $targetType = $transaction->sender_type;
                break;
            case Transaction::TYPE_CASH_OUT:
            case Transaction::TYPE_BUY:
            case Transaction::TYPE_SELL:
            case Transaction::TYPE_RETURN_SUPPLIER:
                $targetType = $transaction->receiver_type;
                break;
        }

        if ($targetType) {
            $summary = MonthlyCategorySummary::firstOrCreate([
                'year' => $year,
                'month' => $month,
                'addrbook_type' => $targetType,
            ]);

            $value = (float) $transaction->total;

            switch ($transaction->type) {
                case Transaction::TYPE_CASH_IN:
                    $summary->increment('cash_in', $value);
                    break;
                case Transaction::TYPE_CASH_OUT:
                    $summary->increment('cash_out', $value);
                    break;
                case Transaction::TYPE_SELL:
                    $summary->increment('sell', $value);
                    break;
                case Transaction::TYPE_BUY:
                    $summary->increment('buy', $value);
                    break;
                case Transaction::TYPE_RETURN:
                    $summary->increment('return', $value);
                    break;
                case Transaction::TYPE_RETURN_SUPPLIER:
                    $summary->increment('return_supplier', $value);
                    break;
            }
        }
    }

    protected function updateInventorySummary(Transaction $transaction, $date): void
    {
        $transaction->loadMissing('details');

        foreach ($transaction->details as $detail) {
            if ($transaction->sender_type == Addrbook::TYPE_WAREHOUSE) {
                $this->updateInventoryDetail($date, $transaction->sender_id, $detail->item_id, $transaction->type, $detail->quantity, 'sender');
            }

            if ($transaction->receiver_type == Addrbook::TYPE_WAREHOUSE) {
                $this->updateInventoryDetail($date, $transaction->receiver_id, $detail->item_id, $transaction->type, $detail->quantity, 'receiver');
            }
        }
    }

    protected function updateInventoryDetail($date, $warehouseId, $itemId, $type, $qty, $side): void
    {
        $summary = DailyInventorySummary::firstOrCreate([
            'date' => $date,
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
        ]);

        switch ($type) {
            case Transaction::TYPE_SELL:
                $summary->increment('qty_sell', $qty);
                break;
            case Transaction::TYPE_BUY:
                $summary->increment('qty_buy', $qty);
                break;
            case Transaction::TYPE_MOVE:
                if ($side === 'receiver') $summary->increment('qty_move_in', $qty);
                if ($side === 'sender') $summary->increment('qty_move_out', $qty);
                break;
            case Transaction::TYPE_RETURN:
                $summary->increment('qty_return_in', $qty);
                break;
            case Transaction::TYPE_RETURN_SUPPLIER:
                $summary->increment('qty_return_out', $qty);
                break;
            case Transaction::TYPE_ADJUST:
                if ($qty > 0) $summary->increment('qty_adjust_in', $qty);
                if ($qty < 0) $summary->increment('qty_adjust_out', abs($qty));
                break;
        }
    }
}
