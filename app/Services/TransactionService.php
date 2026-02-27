<?php

namespace App\Services;

use App\Models\Addrbook;
use App\Models\AddrbookDaily;
use App\Models\AddrbookStat;
use App\Models\Transaction;
use App\Models\WarehouseItem;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * Create a new class instance.
     */
    public function handleTransaction(Transaction $transaction)
    {
        DB::transaction(function () use ($transaction) {
            $this->updateStock($transaction);
            $this->updateBalances($transaction);
        });
    }

    protected function updateStock(Transaction $transaction)
    {
        foreach ($transaction->details as $detail) {
            // Update Sender (Stock Out)
            if ($transaction->sender_id) {
                $this->adjustStock($transaction->sender_id, $transaction->sender_type, $detail->item_id, -$detail->quantity);
            }

            // Update Receiver (Stock In)
            if ($transaction->receiver_id) {
                $this->adjustStock($transaction->receiver_id, $transaction->receiver_type, $detail->item_id, $detail->quantity);
            }

            // Update Global Item Stock
            $this->updateGlobalStock($transaction, $detail);
        }
    }

    protected function updateGlobalStock(Transaction $transaction, $detail)
    {
        $item = \App\Models\Item::find($detail->item_id);
        if (! $item) {
            return;
        }

        // Logic:
        // Buy: Supplier -> Warehouse (Increase Global Stock)
        // Sell: Warehouse -> Customer (Decrease Global Stock)
        // Move: Warehouse -> Warehouse (No Change)
        // Transfer, Adjust: Depends.

        if ($transaction->type === Transaction::TYPE_BUY) {
            $item->increment('qty', $detail->quantity);
        } elseif ($transaction->type === Transaction::TYPE_SELL) {
            $item->decrement('qty', $detail->quantity);
        } elseif ($transaction->type === Transaction::TYPE_RETURN) {
            $item->increment('qty', $detail->quantity);
        } elseif ($transaction->type === Transaction::TYPE_RETURN_SUPPLIER) {
            $item->decrement('qty', $detail->quantity);
        }
        // CASH_IN does not affect global stock as it's purely financial.
        // Add other types if necessary (e.g. production, return)
    }

    protected function adjustStock($warehouseId, $warehouseType, $itemId, $quantity)
    {
        // Find by unique constraint (warehouse_id, item_id) to avoid duplicate entry errors
        $wi = WarehouseItem::firstOrNew([
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
        ]);

        // Update warehouse_type ensuring it's set correctly
        if ($warehouseType) {
            $wi->warehouse_type = $warehouseType;
        }

        $wi->quantity = ($wi->quantity ?? 0) + $quantity;
        $wi->save();
    }

    protected function updateBalances(Transaction $transaction)
    {
        $amount = $transaction->grand_total;

        if ($transaction->type === Transaction::TYPE_BUY && $transaction->sender_id) {
            // BUY: Sender (Supplier) balance increases (+)
            $this->updateEntityBalance($transaction, 'sender', $amount);
            $this->updateDailyReports($transaction, 'sender', $amount);
        } elseif ($transaction->type === Transaction::TYPE_SELL && $transaction->receiver_id) {
            // SELL: Receiver (Customer) balance decreases (-)
            // grand_total is already negative from controller for SELL
            $this->updateEntityBalance($transaction, 'receiver', $amount);
            $this->updateDailyReports($transaction, 'receiver', $amount);
        } elseif ($transaction->type === Transaction::TYPE_RETURN && $transaction->sender_id) {
            // RETURN: Sender (Customer) balance increases (+)
            $this->updateEntityBalance($transaction, 'sender', $amount);
            $this->updateDailyReports($transaction, 'sender', $amount);
            // RETURN_SUPPLIER: Receiver balance decreases (-)
            // grand_total is already negative from controller
            $this->updateEntityBalance($transaction, 'receiver', $amount);
            $this->updateDailyReports($transaction, 'receiver', $amount);
        } elseif ($transaction->type === Transaction::TYPE_CASH_IN) {
            // CASH_IN: Increase balance for both sides (Sender: Source of funds, Receiver: Bank account)
            // legacy: $sm->add($transaction->sender_id,$transaction); $sm->add($transaction->receiver_id,$transaction);
            $this->updateEntityBalance($transaction, 'sender', $amount);
            $this->updateDailyReports($transaction, 'sender', $amount);
            $this->updateEntityBalance($transaction, 'receiver', $amount);
            $this->updateDailyReports($transaction, 'receiver', $amount);
        } elseif ($transaction->type === Transaction::TYPE_CASH_OUT) {
            // CASH_OUT: Decrease balance for both sides
            // amount is negative for Cash Out from controller
            $this->updateEntityBalance($transaction, 'sender', $amount);
            $this->updateDailyReports($transaction, 'sender', $amount);
            $this->updateEntityBalance($transaction, 'receiver', $amount);
            $this->updateDailyReports($transaction, 'receiver', $amount);
        } elseif ($transaction->type === Transaction::TYPE_TRANSFER || $transaction->type === Transaction::TYPE_ADJUST) {
            // TRANSFER & ADJUST: Sender (Debit+) balance decreases (-), Receiver (Credit+) balance increases (+)
            $this->updateEntityBalance($transaction, 'sender', -$amount);
            $this->updateDailyReports($transaction, 'sender', -$amount);
            $this->updateEntityBalance($transaction, 'receiver', $amount);
            $this->updateDailyReports($transaction, 'receiver', $amount);
        }
    }

    protected function updateDailyReports(Transaction $transaction, string $side, $amount)
    {
        $column = $this->getDailyReportColumn($transaction->type);
        if (! $column) {
            return;
        }

        $entityId = $side === 'sender' ? $transaction->sender_id : $transaction->receiver_id;

        if ($entityId) {
            $this->incrementDailyTotal($entityId, $transaction->date, $column, $amount);
        }
    }

    protected function incrementDailyTotal($addrbookId, $date, $column, $amount)
    {
        $dateStr = \Illuminate\Support\Carbon::parse($date)->format('Y-m-d');

        $addrbook = Addrbook::find($addrbookId);
        $type = $addrbook ? $addrbook->type : null;

        $daily = AddrbookDaily::firstOrCreate([
            'addrbook_id' => $addrbookId,
            'date' => $dateStr,
        ], [
            'type' => $type,
        ]);

        if ($daily->type === null && $type !== null) {
            $daily->type = $type;
            $daily->save();
        }

        $daily->increment($column, $amount);
    }

    protected function getDailyReportColumn($type)
    {
        return match ($type) {
            Transaction::TYPE_BUY => 'buy',
            Transaction::TYPE_SELL => 'sell',
            Transaction::TYPE_RETURN => 'return',
            Transaction::TYPE_RETURN_SUPPLIER => 'return_supplier',
            Transaction::TYPE_MOVE => 'move',
            Transaction::TYPE_TRANSFER => 'transfer',
            Transaction::TYPE_ADJUST => 'adjust',
            Transaction::TYPE_PRODUCTION => 'use', // Assuming production "uses" items
            Transaction::TYPE_CASH_IN => 'sell', // Map to matching column if specific one doesn't exist, legacy uses CCManager update with type
            Transaction::TYPE_CASH_OUT => 'buy', // Map to matching column (expense)
            default => null,
        };
    }

    protected function updateEntityBalance(Transaction $transaction, string $side, $amount) // $side = 'sender' or 'receiver'
    {
        $entity = $transaction->$side;
        if (! $entity) {
            return;
        }

        $balanceCol = $side.'_balance';

        // Update Stat Table (Overall current balance)
        $this->updateStat($entity, $amount, $transaction->date);

        // Update Transaction Snapshots
        $transaction->$balanceCol = $this->getLastBalance($entity, $transaction->date, $transaction->id) + $amount;
        $transaction->saveQuietly();

        // Update Future Transactions
        $this->updateFutureBalances($entity, $transaction->date, $amount, $side, $transaction->id);
    }

    protected function updateStat($entity, $amount, $date)
    {
        if ($entity instanceof Addrbook) {
            $stat = AddrbookStat::firstOrCreate(['addrbook_id' => $entity->id]);
            $stat->balance += $amount;
            $stat->save();
        }
    }

    protected function getLastBalance($entity, $date, $currentTransactionId = null)
    {
        if (! ($entity instanceof Addrbook)) {
            return 0;
        }

        // Find the most recent transaction for this entity before this date,
        // or on this date but with a lower ID.
        // We look at both sender and receiver roles.
        $query = Transaction::where(function ($q) use ($entity) {
            $q->where(function ($q2) use ($entity) {
                $q2->where('sender_id', $entity->id)
                    ->where('sender_type', $entity->type);
            })->orWhere(function ($q2) use ($entity) {
                $q2->where('receiver_id', $entity->id)
                    ->where('receiver_type', $entity->type);
            });
        })
            ->where('date', '<=', $date);

        if ($currentTransactionId) {
            $query->where(function ($q) use ($date, $currentTransactionId) {
                $q->where('date', '<', $date)
                    ->orWhere(function ($q2) use ($date, $currentTransactionId) {
                        $q2->where('date', $date)->where('id', '<', $currentTransactionId);
                    });
            });
        }

        $lastTransaction = $query->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (! $lastTransaction) {
            return 0;
        }

        // Return the balance corresponding to the role the entity played in that last transaction
        if ($lastTransaction->sender_id === $entity->id && $lastTransaction->sender_type == $entity->type) {
            return $lastTransaction->sender_balance;
        }

        return $lastTransaction->receiver_balance;
    }

    protected function updateFutureBalances($entity, $date, $amount, $side, $currentTransactionId = null)
    {
        if (! ($entity instanceof Addrbook)) {
            return;
        }

        $query = Transaction::where(function ($q) use ($entity) {
            $q->where(function ($q2) use ($entity) {
                $q2->where('sender_id', $entity->id)
                    ->where('sender_type', $entity->type);
            })->orWhere(function ($q2) use ($entity) {
                $q2->where('receiver_id', $entity->id)
                    ->where('receiver_type', $entity->type);
            });
        })
            ->where('date', '>=', $date);

        if ($currentTransactionId) {
            $query->where(function ($q) use ($date, $currentTransactionId) {
                $q->where('date', '>', $date)
                    ->orWhere(function ($q2) use ($date, $currentTransactionId) {
                        $q2->where('date', $date)->where('id', '>', $currentTransactionId);
                    });
            });
        } else {
            $query->where('date', '>', $date);
        }

        $futureTransactions = $query->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($futureTransactions as $ft) {
            if ($ft->sender_id === $entity->id && $ft->sender_type == $entity->type) {
                $ft->sender_balance += $amount;
            }
            if ($ft->receiver_id === $entity->id && $ft->receiver_type == $entity->type) {
                $ft->receiver_balance += $amount;
            }
            $ft->saveQuietly();
        }
    }
}
