<?php

namespace App\Services;

use App\Models\Addrbook;
use App\Models\AddrbookDaily;
use App\Models\AddrbookStat;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\WarehouseItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    protected static int $runningBalancePostingDepth = 0;

    public function handleTransaction(Transaction $transaction)
    {
        $this->withRunningBalancePosting(function () use ($transaction) {
            DB::transaction(function () use ($transaction) {
                $this->updateStock($transaction);
                $this->updateBalances($transaction);
            });

            $this->queueStockNotificationCheckIfNeeded($transaction);
        });
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
        $this->withRunningBalancePosting(function () use ($transaction) {
            DB::transaction(function () use ($transaction) {
                $transaction->loadMissing('details');
                $this->updateStock($transaction, true);
                $this->updateBalances($transaction, true);
            });
        });
    }

    /**
     * Revert the transaction's current posted effects, apply caller changes, then repost.
     * Caller must mutate the same model instance (header and/or details) inside $applyChanges.
     */
    public function editTransaction(Transaction $transaction, callable $applyChanges): Transaction
    {
        return $this->withRunningBalancePosting(function () use ($transaction, $applyChanges) {
            return DB::transaction(function () use ($transaction, $applyChanges) {
                $snapshot = $this->snapshotForRevert($transaction);

                $this->revertTransaction($snapshot);

                $applyChanges($transaction);

                $transaction->refresh()->load('details');
                $this->handleTransaction($transaction);

                return $transaction->fresh(['details']);
            });
        });
    }

    public function withRunningBalancePosting(callable $callback): mixed
    {
        self::$runningBalancePostingDepth++;

        try {
            return $callback();
        } finally {
            self::$runningBalancePostingDepth--;
        }
    }

    public static function isPostingRunningBalances(): bool
    {
        return self::$runningBalancePostingDepth > 0;
    }

    /**
     * Signed running-balance deltas for each party.
     * null = this side does not post a money balance (e.g. warehouse on a buy).
     *
     * @return array{sender: ?float, receiver: ?float}
     */
    public static function signedBalanceDeltas(int $type, float $signedAmount): array
    {
        return match ($type) {
            Transaction::TYPE_BUY => ['sender' => $signedAmount, 'receiver' => null],
            Transaction::TYPE_SELL => ['sender' => null, 'receiver' => $signedAmount],
            Transaction::TYPE_RETURN,
            Transaction::TYPE_CASH_IN,
            Transaction::TYPE_CASH_OUT,
            Transaction::TYPE_DEPRECIATION => ['sender' => $signedAmount, 'receiver' => $signedAmount],
            Transaction::TYPE_TRANSFER => ['sender' => $signedAmount, 'receiver' => -$signedAmount],
            Transaction::TYPE_ADJUST => ['sender' => -$signedAmount, 'receiver' => $signedAmount],
            default => ['sender' => null, 'receiver' => null],
        };
    }

    /**
     * In-memory copy of a transaction + details for revert before edits mutate the row.
     */
    protected function snapshotForRevert(Transaction $transaction): Transaction
    {
        $transaction->loadMissing('details');

        $snapshot = new Transaction($transaction->getAttributes());
        $snapshot->exists = true;
        $snapshot->id = $transaction->id;

        $snapshot->setRelation(
            'details',
            $transaction->details->map(function ($detail) {
                $copy = new \App\Models\TransactionDetail($detail->getAttributes());
                $copy->exists = true;

                return $copy;
            })
        );

        return $snapshot;
    }

    protected function updateStock(Transaction $transaction, bool $revert = false)
    {
        if ((int) $transaction->type === Transaction::TYPE_DEPRECIATION) {
            return;
        }

        $transaction->loadMissing('details');

        foreach ($transaction->details as $detail) {
            $qty = $revert ? -(float) $detail->quantity : (float) $detail->quantity;

            $senderId = (int) ($detail->sender_id ?: $transaction->sender_id);
            $receiverId = (int) ($detail->receiver_id ?: $transaction->receiver_id);

            if ($senderId) {
                $senderType = $senderId === (int) $transaction->sender_id
                    ? $transaction->sender_type
                    : $this->partyType($senderId);
                $this->adjustStock($senderId, $senderType, (int) $detail->item_id, -$qty);
            }

            if ($receiverId) {
                $receiverType = $receiverId === (int) $transaction->receiver_id
                    ? $transaction->receiver_type
                    : $this->partyType($receiverId);
                $this->adjustStock($receiverId, $receiverType, (int) $detail->item_id, $qty);
            }

            $this->updateGlobalStock($transaction, $detail, $revert);
        }
    }

    protected function partyType(int $partyId): ?int
    {
        $type = Addrbook::withTrashed()->whereKey($partyId)->value('type');

        return $type !== null ? (int) $type : null;
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
        if (! $warehouseId || (float) $quantity === 0.0) {
            return;
        }

        $item = Item::query()->find($itemId);
        if ($item && (int) $item->getRawOriginal('type') === Item::TYPE_SERVICE) {
            return;
        }

        WarehouseItem::applyDelta(
            (int) $warehouseId,
            (int) $itemId,
            (float) $quantity,
            $warehouseType !== null && $warehouseType !== '' ? (int) $warehouseType : null,
        );
    }

    protected function updateBalances(Transaction $transaction, bool $revert = false)
    {
        $amount = $this->balanceAmount($transaction, $revert);
        $type = (int) $transaction->type;

        if ($type === Transaction::TYPE_BUY && $transaction->sender_id) {
            $this->updateEntityBalance($transaction, 'sender', $amount, $revert);
            $this->updateDailyReports($transaction, 'sender', $amount);
        } elseif ($type === Transaction::TYPE_SELL && $transaction->receiver_id) {
            $this->updateEntityBalance($transaction, 'receiver', $amount, $revert);
            $this->updateDailyReports($transaction, 'receiver', $amount);
        } elseif ($type === Transaction::TYPE_RETURN) {
            if ($transaction->sender_id) {
                $this->updateEntityBalance($transaction, 'sender', $amount, $revert);
                $this->updateDailyReports($transaction, 'sender', $amount);
            }
            if ($transaction->receiver_id) {
                $this->updateEntityBalance($transaction, 'receiver', $amount, $revert);
                $this->updateDailyReports($transaction, 'receiver', $amount);
            }
        } elseif ($type === Transaction::TYPE_CASH_IN) {
            $this->updateEntityBalance($transaction, 'sender', $amount, $revert);
            $this->updateDailyReports($transaction, 'sender', $amount);
            $this->updateEntityBalance($transaction, 'receiver', $amount, $revert);
            $this->updateDailyReports($transaction, 'receiver', $amount);
        } elseif ($type === Transaction::TYPE_CASH_OUT) {
            $this->updateEntityBalance($transaction, 'sender', $amount, $revert);
            $this->updateDailyReports($transaction, 'sender', $amount);
            $this->updateEntityBalance($transaction, 'receiver', $amount, $revert);
            $this->updateDailyReports($transaction, 'receiver', $amount);
        } elseif ($type === Transaction::TYPE_TRANSFER) {
            $this->updateEntityBalance($transaction, 'sender', $amount, $revert);
            $this->updateDailyReports($transaction, 'sender', $amount);
            $this->updateEntityBalance($transaction, 'receiver', -$amount, $revert);
            $this->updateDailyReports($transaction, 'receiver', -$amount);
        } elseif ($type === Transaction::TYPE_ADJUST) {
            $this->updateEntityBalance($transaction, 'sender', -$amount, $revert);
            $this->updateDailyReports($transaction, 'sender', -$amount);
            $this->updateEntityBalance($transaction, 'receiver', $amount, $revert);
            $this->updateDailyReports($transaction, 'receiver', $amount);
        } elseif ($type === Transaction::TYPE_DEPRECIATION) {
            $this->updateEntityBalance($transaction, 'sender', $amount, $revert);
            $this->updateDailyReports($transaction, 'sender', $amount);
            $this->updateEntityBalance($transaction, 'receiver', $amount, $revert);
            $this->updateDailyReports($transaction, 'receiver', $amount);
        }
    }

    /**
     * Signed balance delta from header `total` (final payable after discount / PPN / adj).
     * A stored 0 is a real zero (e.g. 100% invoice discount).
     * Legacy rows may store the wrong sign — normalize via signedAmount(abs(...)).
     */
    public function balanceAmount(Transaction $transaction, bool $revert = false): float
    {
        $amount = Transaction::signedAmount((int) $transaction->type, abs((float) $transaction->total));

        return $revert ? -$amount : $amount;
    }

    public function partyBalanceDelta(Transaction $transaction, Addrbook $entity): float
    {
        $deltas = self::signedBalanceDeltas((int) $transaction->type, $this->balanceAmount($transaction));
        $entityType = (int) $entity->type;
        $delta = 0.0;

        if (
            (int) $transaction->sender_id === (int) $entity->id
            && (int) $transaction->sender_type === $entityType
            && $deltas['sender'] !== null
        ) {
            $delta += $deltas['sender'];
        }

        if (
            (int) $transaction->receiver_id === (int) $entity->id
            && (int) $transaction->receiver_type === $entityType
            && $deltas['receiver'] !== null
        ) {
            $delta += $deltas['receiver'];
        }

        return $delta;
    }

    public function syncStatFromLatestTransaction(Addrbook $entity, ?int $excludeId = null): void
    {
        $entityType = (int) $entity->type;

        $query = $this->entityTransactionsQuery($entity)
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $lastTransaction = $query->first();

        $balance = 0.0;

        if ($lastTransaction) {
            if ((int) $lastTransaction->sender_id === (int) $entity->id && (int) $lastTransaction->sender_type === $entityType) {
                $balance = (float) $lastTransaction->sender_balance;
            } elseif ((int) $lastTransaction->receiver_id === (int) $entity->id && (int) $lastTransaction->receiver_type === $entityType) {
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

    protected function updateEntityBalance(Transaction $transaction, string $side, $amount, bool $revert = false)
    {
        $entity = $transaction->$side;
        if (! $entity instanceof Addrbook) {
            return;
        }

        $excludeId = $revert ? (int) $transaction->id : null;

        $this->recalculateRunningBalancesFor(
            $entity,
            $transaction->date,
            (int) $transaction->id,
            $excludeId,
        );
        $this->syncStatFromLatestTransaction($entity, $excludeId);
    }

    /**
     * Re-derive sender_balance / receiver_balance for one addrbook from a ledger point forward.
     */
    public function recalculateRunningBalancesFor(
        Addrbook $entity,
        mixed $fromDate = null,
        ?int $fromId = null,
        ?int $excludeId = null,
    ): float {
        return $this->withRunningBalancePosting(function () use ($entity, $fromDate, $fromId, $excludeId) {
            $fromDate = $fromDate !== null ? $this->dateString($fromDate) : null;
            $running = $fromDate !== null
                ? $this->getLastBalance($entity, $fromDate, $fromId ?? 0, $excludeId)
                : 0.0;

            $query = $this->entityTransactionsQuery($entity);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            if ($fromDate !== null) {
                if ($fromId) {
                    $query->where(function ($q) use ($fromDate, $fromId) {
                        $q->where('date', '>', $fromDate)
                            ->orWhere(function ($q2) use ($fromDate, $fromId) {
                                $q2->where('date', $fromDate)->where('id', '>=', $fromId);
                            });
                    });
                } else {
                    $query->where('date', '>=', $fromDate);
                }
            }

            foreach ($query->orderBy('date')->orderBy('id')->cursor() as $txn) {
                $running += $this->partyBalanceDelta($txn, $entity);
                $this->writeEntityRunningBalance($txn, $entity, $running);
            }

            return $running;
        });
    }

    /**
     * Re-derive running balances for every party this row posts (and original parties when requested).
     */
    public function recalculateAffectedRunningBalances(
        Transaction $transaction,
        bool $includeOriginal = false,
        ?int $excludeId = null,
    ): void {
        $this->withRunningBalancePosting(function () use ($transaction, $includeOriginal, $excludeId) {
            foreach ($this->collectAffectedParties($transaction, $includeOriginal) as $party) {
                $this->recalculateRunningBalancesFor($party['entity'], $party['from'], null, $excludeId);
                $this->syncStatFromLatestTransaction($party['entity'], $excludeId);
            }
        });
    }

    /**
     * Rebuild running balances in date+id order. Used by Recalculate* commands.
     */
    public function rebuildRunningBalances(?int $addrbookId = null, ?string $fromDate = null): int
    {
        return $this->withRunningBalancePosting(function () use ($addrbookId, $fromDate) {
            $fromDate = $fromDate !== null && $fromDate !== '' ? $this->dateString($fromDate) : null;
            $balances = [];
            $touched = [];

            $query = Transaction::query()->orderBy('date')->orderBy('id');
            if ($fromDate !== null) {
                $query->where('date', '>=', $fromDate);
            }

            $updated = 0;

            foreach ($query->cursor() as $trx) {
                $deltas = self::signedBalanceDeltas((int) $trx->type, $this->balanceAmount($trx));
                $changed = false;

                if ($deltas['sender'] !== null && $trx->sender_id) {
                    $senderId = (int) $trx->sender_id;
                    if ($addrbookId === null || $senderId === $addrbookId) {
                        $balances[$senderId] = $this->openingRebuildBalance($balances, $senderId, $fromDate) + $deltas['sender'];
                        $trx->sender_balance = $balances[$senderId];
                        $touched[$senderId] = true;
                        $changed = true;
                    }
                }

                if ($deltas['receiver'] !== null && $trx->receiver_id) {
                    $receiverId = (int) $trx->receiver_id;
                    if ($addrbookId === null || $receiverId === $addrbookId) {
                        $balances[$receiverId] = $this->openingRebuildBalance($balances, $receiverId, $fromDate) + $deltas['receiver'];
                        $trx->receiver_balance = $balances[$receiverId];
                        $touched[$receiverId] = true;
                        $changed = true;
                    }
                }

                if ($changed) {
                    $trx->saveQuietly();
                    $updated++;
                }
            }

            if ($addrbookId) {
                $touched[$addrbookId] = true;
            }

            foreach (array_keys($touched) as $id) {
                $entity = Addrbook::withTrashed()->find($id);
                if ($entity) {
                    $this->syncStatFromLatestTransaction($entity);
                }
            }

            return $updated;
        });
    }

    protected function openingRebuildBalance(array $balances, int $addrbookId, ?string $fromDate): float
    {
        if (array_key_exists($addrbookId, $balances)) {
            return $balances[$addrbookId];
        }

        if ($fromDate === null) {
            return 0.0;
        }

        $entity = Addrbook::withTrashed()->find($addrbookId);

        return $entity ? $this->getLastBalance($entity, $fromDate, 0) : 0.0;
    }

    /**
     * @return array<int, array{entity: Addrbook, from: ?string}>
     */
    protected function collectAffectedParties(Transaction $transaction, bool $includeOriginal): array
    {
        $parties = [];

        $add = function (?int $id, mixed $date) use (&$parties): void {
            if (! $id) {
                return;
            }

            $entity = Addrbook::withTrashed()->find($id);
            if (! $entity) {
                return;
            }

            $dateStr = $date !== null ? $this->dateString($date) : null;
            if (! isset($parties[$entity->id])) {
                $parties[$entity->id] = ['entity' => $entity, 'from' => $dateStr];

                return;
            }

            $existing = $parties[$entity->id]['from'];
            if ($dateStr !== null && ($existing === null || $dateStr < $existing)) {
                $parties[$entity->id]['from'] = $dateStr;
            }
        };

        foreach ($this->postingPartyIds($transaction) as $id) {
            $add($id, $transaction->date);
        }

        if ($includeOriginal) {
            $original = new Transaction;
            $original->forceFill([
                'type' => $transaction->getOriginal('type') ?? $transaction->type,
                'total' => $transaction->getOriginal('total') ?? $transaction->total,
                'sender_id' => $transaction->getOriginal('sender_id'),
                'receiver_id' => $transaction->getOriginal('receiver_id'),
            ]);

            foreach ($this->postingPartyIds($original) as $id) {
                $add($id, $transaction->getOriginal('date') ?? $transaction->date);
            }
        }

        return $parties;
    }

    /**
     * @return list<int>
     */
    protected function postingPartyIds(Transaction $transaction): array
    {
        $deltas = self::signedBalanceDeltas((int) $transaction->type, $this->balanceAmount($transaction));
        $ids = [];

        if ($deltas['sender'] !== null && $transaction->sender_id) {
            $ids[] = (int) $transaction->sender_id;
        }

        if ($deltas['receiver'] !== null && $transaction->receiver_id) {
            $ids[] = (int) $transaction->receiver_id;
        }

        return $ids;
    }

    protected function writeEntityRunningBalance(Transaction $transaction, Addrbook $entity, float $running): void
    {
        $deltas = self::signedBalanceDeltas((int) $transaction->type, $this->balanceAmount($transaction));
        $entityType = (int) $entity->type;
        $dirty = false;

        if (
            (int) $transaction->sender_id === (int) $entity->id
            && (int) $transaction->sender_type === $entityType
            && $deltas['sender'] !== null
        ) {
            $transaction->sender_balance = $running;
            $dirty = true;
        }

        if (
            (int) $transaction->receiver_id === (int) $entity->id
            && (int) $transaction->receiver_type === $entityType
            && $deltas['receiver'] !== null
        ) {
            $transaction->receiver_balance = $running;
            $dirty = true;
        }

        if ($dirty) {
            $transaction->saveQuietly();
        }
    }

    protected function entityTransactionsQuery(Addrbook $entity)
    {
        $entityType = (int) $entity->type;

        return Transaction::query()->where(function ($q) use ($entity, $entityType) {
            $q->where(function ($q2) use ($entity, $entityType) {
                $q2->where('sender_id', $entity->id)
                    ->where('sender_type', $entityType);
            })->orWhere(function ($q2) use ($entity, $entityType) {
                $q2->where('receiver_id', $entity->id)
                    ->where('receiver_type', $entityType);
            });
        });
    }

    protected function getLastBalance($entity, $date, $currentTransactionId = null, ?int $excludeId = null)
    {
        if (! ($entity instanceof Addrbook)) {
            return 0;
        }

        $date = $this->dateString($date);
        $entityType = (int) $entity->type;

        $query = $this->entityTransactionsQuery($entity)
            ->where('date', '<=', $date);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($currentTransactionId !== null) {
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

        if ((int) $lastTransaction->sender_id === (int) $entity->id && (int) $lastTransaction->sender_type === $entityType) {
            return (float) $lastTransaction->sender_balance;
        }

        return (float) $lastTransaction->receiver_balance;
    }

    protected function dateString(mixed $date): string
    {
        return Carbon::parse($date)->format('Y-m-d');
    }
}
