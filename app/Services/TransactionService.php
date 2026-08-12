<?php

namespace App\Services;

use App\Enums\AddrbookType;
use App\Enums\TransactionType;
use App\Models\Addrbook;
use App\Models\AddrbookDaily;
use App\Models\AddrbookStat;
use App\Models\Transaction;
use App\Models\WarehouseItem;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function handleTransaction(Transaction $transaction)
    {
        DB::transaction(function () use ($transaction) {
            $this->updateStock($transaction);
            $this->updateBalances($transaction);
        });
    }

    public function revertTransaction(Transaction $transaction)
    {
        DB::transaction(function () use ($transaction) {
            $this->updateStock($transaction, true);
            $this->updateBalances($transaction, true);
        });
    }

    protected function updateStock(Transaction $transaction, bool $revert = false)
    {
        foreach ($transaction->details as $detail) {
            $qty = $revert ? -$detail->quantity : $detail->quantity;

            if ($transaction->sender_id) {
                $this->adjustStock($transaction->sender_id, $transaction->sender_type, $detail->item_id, -$qty);
            }

            if ($transaction->receiver_id) {
                $this->adjustStock($transaction->receiver_id, $transaction->receiver_type, $detail->item_id, $qty);
            }

            $this->updateGlobalStock($transaction, $detail, $revert);
        }
    }

    protected function updateGlobalStock(Transaction $transaction, $detail, bool $revert = false)
    {
        $item = \App\Models\Item::find($detail->item_id);
        if (! $item) {
            return;
        }

        $qty = $revert ? -$detail->quantity : $detail->quantity;

        if ($transaction->type === TransactionType::Buy) {
            $item->increment('qty', $qty);
        } elseif ($transaction->type === TransactionType::Sell) {
            $item->decrement('qty', $qty);
        } elseif ($transaction->type === TransactionType::Return) {
            $item->increment('qty', $qty);
        } elseif ($transaction->type === TransactionType::ReturnSupplier) {
            $item->decrement('qty', $qty);
        }
    }

    protected function adjustStock($warehouseId, $warehouseType, $itemId, $quantity)
    {
        $wi = WarehouseItem::firstOrNew([
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
        ]);

        if ($warehouseType) {
            $wi->warehouse_type = $warehouseType;
        }

        $wi->quantity = ($wi->quantity ?? 0) + $quantity;
        $wi->save();
    }

    protected function updateBalances(Transaction $transaction, bool $revert = false)
    {
        $amount = $revert ? -$transaction->real_total : $transaction->real_total;

        // Use enum comparisons (not old integer constants) since type is now cast to TransactionType
        if ($transaction->type === TransactionType::Buy && $transaction->sender_id) {
            $this->updateEntityBalance($transaction, 'sender', $amount);
            $this->updateDailyReports($transaction, 'sender', $amount);
        } elseif ($transaction->type === TransactionType::Sell && $transaction->receiver_id) {
            $this->updateEntityBalance($transaction, 'receiver', $amount);
            $this->updateDailyReports($transaction, 'receiver', $amount);
        } elseif ($transaction->type === TransactionType::Return) {
            if ($transaction->sender_id) {
                $this->updateEntityBalance($transaction, 'sender', $amount);
                $this->updateDailyReports($transaction, 'sender', $amount);
            }
            if ($transaction->receiver_id) {
                $this->updateEntityBalance($transaction, 'receiver', $amount);
                $this->updateDailyReports($transaction, 'receiver', $amount);
            }
        } elseif ($transaction->type === TransactionType::CashIn) {
            $this->updateEntityBalance($transaction, 'sender', $amount);
            $this->updateDailyReports($transaction, 'sender', $amount);
            $this->updateEntityBalance($transaction, 'receiver', $amount);
            $this->updateDailyReports($transaction, 'receiver', $amount);
        } elseif ($transaction->type === TransactionType::CashOut) {
            $this->updateEntityBalance($transaction, 'sender', $amount);
            $this->updateDailyReports($transaction, 'sender', $amount);
            $this->updateEntityBalance($transaction, 'receiver', $amount);
            $this->updateDailyReports($transaction, 'receiver', $amount);
        } elseif ($transaction->type === TransactionType::Transfer || $transaction->type === TransactionType::Adjust) {
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
            'customer_id' => $addrbookId,
            'date' => $dateStr,
        ], [
            'customer_type' => $type instanceof AddrbookType ? $type->value : $type,
            'class' => '',
        ]);

        if ($daily->customer_type === null && $type !== null) {
            $daily->customer_type = $type instanceof AddrbookType ? $type->value : $type;
            $daily->save();
        }

        $daily->increment($column, $amount);
    }

    protected function getDailyReportColumn(TransactionType $type): ?string
    {
        return match ($type) {
            TransactionType::Buy => 'buy',
            TransactionType::Sell => 'sell',
            TransactionType::Return => 'return',
            TransactionType::ReturnSupplier => 'return_supplier',
            TransactionType::Move => 'move',
            TransactionType::Transfer => 'transfer',
            TransactionType::Adjust => 'adjust',
            TransactionType::Production => 'use',
            TransactionType::CashIn => 'sell',
            TransactionType::CashOut => 'buy',
            default => null,
        };
    }

    protected function updateEntityBalance(Transaction $transaction, string $side, $amount)
    {
        $entity = $transaction->$side;
        if (! $entity) {
            return;
        }

        $balanceCol = $side.'_balance';

        $this->updateStat($entity, $amount, $transaction->date);

        $transaction->$balanceCol = $this->getLastBalance($entity, $transaction->date, $transaction->id) + $amount;
        $transaction->saveQuietly();

        $this->updateFutureBalances($entity, $transaction->date, $amount, $side, $transaction->id);
    }

    protected function updateStat($entity, $amount, $date)
    {
        if ($entity instanceof Addrbook) {
            $stat = AddrbookStat::firstOrCreate(['customer_id' => $entity->id]);
            $stat->balance += $amount;
            $stat->save();
        }
    }

    protected function getLastBalance($entity, $date, $currentTransactionId = null)
    {
        if (! ($entity instanceof Addrbook)) {
            return 0;
        }

        $entityType = $entity->type instanceof AddrbookType ? $entity->type->value : $entity->type;

        $query = Transaction::where(function ($q) use ($entity, $entityType) {
            $q->where(function ($q2) use ($entity, $entityType) {
                $q2->where('sender_id', $entity->id)
                    ->where('sender_type', $entityType);
            })->orWhere(function ($q2) use ($entity, $entityType) {
                $q2->where('receiver_id', $entity->id)
                    ->where('receiver_type', $entityType);
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

        if ($lastTransaction->sender_id === $entity->id && $lastTransaction->sender_type == $entityType) {
            return $lastTransaction->sender_balance;
        }

        return $lastTransaction->receiver_balance;
    }

    protected function updateFutureBalances($entity, $date, $amount, $side, $currentTransactionId = null)
    {
        if (! ($entity instanceof Addrbook)) {
            return;
        }

        $entityType = $entity->type instanceof AddrbookType ? $entity->type->value : $entity->type;

        $query = Transaction::where(function ($q) use ($entity, $entityType) {
            $q->where(function ($q2) use ($entity, $entityType) {
                $q2->where('sender_id', $entity->id)
                    ->where('sender_type', $entityType);
            })->orWhere(function ($q2) use ($entity, $entityType) {
                $q2->where('receiver_id', $entity->id)
                    ->where('receiver_type', $entityType);
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
            if ($ft->sender_id === $entity->id && $ft->sender_type == $entityType) {
                $ft->sender_balance += $amount;
            }
            if ($ft->receiver_id === $entity->id && $ft->receiver_type == $entityType) {
                $ft->receiver_balance += $amount;
            }
            $ft->saveQuietly();
        }
    }
}
