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
    public function handleTransaction(Transaction $transaction)
    {
        DB::transaction(function () use ($transaction) {
            $this->updateStock($transaction);
            $this->updateBalances($transaction);
        });

        $this->queueStockNotificationCheckIfNeeded($transaction);
    }

    private function queueStockNotificationCheckIfNeeded(Transaction $transaction): void
    {
        if ((int) $transaction->status !== Transaction::STATUS_COMPLETED) {
            return;
        }

        if ((int) $transaction->type !== Transaction::TYPE_SELL) {
            return;
        }

        DB::afterCommit(function () use ($transaction) {
            app(ItemStockNotificationService::class)->checkAfterSell(
                $transaction->loadMissing('details')
            );
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
        $type = (int) $transaction->type;

        if ($type === Transaction::TYPE_BUY) {
            $item->increment('qty', $qty);
        } elseif ($type === Transaction::TYPE_SELL) {
            $item->decrement('qty', $qty);
        } elseif ($type === Transaction::TYPE_RETURN) {
            $item->increment('qty', $qty);
        } elseif ($type === Transaction::TYPE_RETURN_SUPPLIER) {
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
        $amount = $this->balanceAmount($transaction, $revert);
        $type = (int) $transaction->type;

        if ($type === Transaction::TYPE_BUY && $transaction->sender_id) {
            $this->updateEntityBalance($transaction, 'sender', $amount);
            $this->updateDailyReports($transaction, 'sender', $amount);
        } elseif ($type === Transaction::TYPE_SELL && $transaction->receiver_id) {
            $this->updateEntityBalance($transaction, 'receiver', $amount);
            $this->updateDailyReports($transaction, 'receiver', $amount);
        } elseif ($type === Transaction::TYPE_RETURN) {
            if ($transaction->sender_id) {
                $this->updateEntityBalance($transaction, 'sender', $amount);
                $this->updateDailyReports($transaction, 'sender', $amount);
            }
            if ($transaction->receiver_id) {
                $this->updateEntityBalance($transaction, 'receiver', $amount);
                $this->updateDailyReports($transaction, 'receiver', $amount);
            }
        } elseif ($type === Transaction::TYPE_CASH_IN) {
            $this->updateEntityBalance($transaction, 'sender', $amount);
            $this->updateDailyReports($transaction, 'sender', $amount);
            $this->updateEntityBalance($transaction, 'receiver', $amount);
            $this->updateDailyReports($transaction, 'receiver', $amount);
        } elseif ($type === Transaction::TYPE_CASH_OUT) {
            $this->updateEntityBalance($transaction, 'sender', $amount);
            $this->updateDailyReports($transaction, 'sender', $amount);
            $this->updateEntityBalance($transaction, 'receiver', $amount);
            $this->updateDailyReports($transaction, 'receiver', $amount);
        } elseif ($type === Transaction::TYPE_TRANSFER) {
            $this->updateEntityBalance($transaction, 'sender', $amount);
            $this->updateDailyReports($transaction, 'sender', $amount);
            $this->updateEntityBalance($transaction, 'receiver', -$amount);
            $this->updateDailyReports($transaction, 'receiver', -$amount);
        } elseif ($type === Transaction::TYPE_ADJUST) {
            $this->updateEntityBalance($transaction, 'sender', -$amount);
            $this->updateDailyReports($transaction, 'sender', -$amount);
            $this->updateEntityBalance($transaction, 'receiver', $amount);
            $this->updateDailyReports($transaction, 'receiver', $amount);
        }
    }

    /**
     * Signed balance delta from the transaction header total (already signed per type).
     * Falls back to real_total only when total is unset (legacy adjust rows).
     * Legacy rows may store the wrong sign — normalize via signedAmount(abs(...)).
     */
    protected function balanceAmount(Transaction $transaction, bool $revert = false): float
    {
        $stored = (float) $transaction->total;
        if ($stored === 0.0 && (float) $transaction->real_total !== 0.0) {
            $stored = (float) $transaction->real_total;
        }

        $amount = Transaction::signedAmount((int) $transaction->type, abs($stored));

        return $revert ? -$amount : $amount;
    }

    public function syncStatFromLatestTransaction(Addrbook $entity): void
    {
        $entityType = (int) $entity->type;

        $lastTransaction = Transaction::query()
            ->where(function ($q) use ($entity, $entityType) {
                $q->where(function ($q2) use ($entity, $entityType) {
                    $q2->where('sender_id', $entity->id)
                        ->where('sender_type', $entityType);
                })->orWhere(function ($q2) use ($entity, $entityType) {
                    $q2->where('receiver_id', $entity->id)
                        ->where('receiver_type', $entityType);
                });
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        $balance = 0.0;

        if ($lastTransaction) {
            if ((int) $lastTransaction->sender_id === $entity->id && (int) $lastTransaction->sender_type === $entityType) {
                $balance = (float) $lastTransaction->sender_balance;
            } elseif ((int) $lastTransaction->receiver_id === $entity->id && (int) $lastTransaction->receiver_type === $entityType) {
                $balance = (float) $lastTransaction->receiver_balance;
            }
        }

        AddrbookStat::updateOrCreate(
            ['customer_id' => $entity->id],
            ['balance' => $balance]
        );
    }

    protected function updateDailyReports(Transaction $transaction, string $side, $amount)
    {
        $column = Transaction::dailyReportColumn((int) $transaction->type);
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
        $type = $addrbook ? (int) $addrbook->type : null;

        $daily = AddrbookDaily::firstOrCreate([
            'customer_id' => $addrbookId,
            'date' => $dateStr,
        ], [
            'customer_type' => $type,
            'class' => '',
            'adjust' => 0,
            'depreciation' => 0,
        ]);

        if ($daily->customer_type === null && $type !== null) {
            $daily->customer_type = $type;
            $daily->save();
        }

        $daily->increment($column, $amount);
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
            $stat = AddrbookStat::firstOrCreate(
                ['customer_id' => $entity->id],
                ['balance' => 0]
            );
            $stat->balance += $amount;
            $stat->save();
        }
    }

    protected function getLastBalance($entity, $date, $currentTransactionId = null)
    {
        if (! ($entity instanceof Addrbook)) {
            return 0;
        }

        $entityType = (int) $entity->type;

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

        $entityType = (int) $entity->type;

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
