<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateAggregation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:finalize-aggregation {--year= : Optional: Filter by year}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate all stocks, balances, and daily reports from local transactions.';

    // In-memory state tracking
    protected array $balances = [];

    protected array $stocks = [];

    protected array $dailies = [];

    protected array $itemGlobalQty = [];

    protected array $addrbookTypes = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $year = $this->option('year');
        $this->info('Starting Final Aggregation (Recalculation)...');

        // 1. Reset current stats to start fresh
        $this->resetStats();

        // 2. Load basic lookups
        $this->addrbookTypes = DB::table('customers')->pluck('type', 'id')->toArray();

        // 3. Process Transactions in chunks
        $query = DB::table('transactions')
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc');

        if ($year) {
            $query->whereYear('date', $year);
        }

        $total = $query->count();
        $this->info("Processing {$total} local transactions...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(1000, function ($transactions) use ($bar) {
            $trxIds = $transactions->pluck('id')->toArray();

            // Fetch all details for this chunk
            $allDetails = DB::table('transaction_details')
                ->whereIn('transaction_id', $trxIds)
                ->get()
                ->groupBy('transaction_id');

            foreach ($transactions as $trx) {
                $senderId = $trx->sender_id;
                $receiverId = $trx->receiver_id;
                $amount = $this->balanceAmountFromRow($trx);

                // Update Balances In-Memory (mirrors TransactionService::updateBalances)
                $this->applyBalanceDelta((int) $trx->type, $senderId, $receiverId, $amount);

                // Update Snapshots in Transaction table
                DB::table('transactions')->where('id', $trx->id)->update([
                    'sender_balance' => $this->balances[$senderId] ?? 0,
                    'receiver_balance' => $this->balances[$receiverId] ?? 0,
                ]);

                // Process Details & Stocks
                if (isset($allDetails[$trx->id])) {
                    foreach ($allDetails[$trx->id] as $detail) {
                        $qty = (float) $detail->quantity;
                        $itemId = $detail->item_id;

                        $this->adjustStockInMemory($senderId, $itemId, -$qty);
                        $this->adjustStockInMemory($receiverId, $itemId, $qty);
                        $this->updateGlobalStockInMemory($trx->type, $itemId, $qty);
                    }
                }

                // Update Daily Stats
                $this->trackDailyReport($trx->type, $trx->date, $senderId, $receiverId, $amount);

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        // 4. Save everything to DB
        $this->saveResultsToDb();

        $this->info('Aggregation completed successfully.');
    }

    protected function resetStats()
    {
        $this->info('Resetting current stats tables...');
        DB::table('warehouse_item')->truncate();
        DB::table('customerstat')->truncate();
        DB::table('customer_class')->truncate();
        DB::table('items')->update(['qty' => 0]);
    }

    protected function balanceAmountFromRow(object $trx): float
    {
        $stored = (float) $trx->total;
        if ($stored === 0.0 && (float) $trx->real_total !== 0.0) {
            $stored = (float) $trx->real_total;
        }

        return $stored;
    }

    /**
     * Mirrors TransactionService::updateBalances party deltas.
     */
    protected function applyBalanceDelta(int $type, ?int $senderId, ?int $receiverId, float $amount): void
    {
        if ($type === Transaction::TYPE_BUY && $senderId) {
            $this->balances[$senderId] = (float) ($this->balances[$senderId] ?? 0) + $amount;
        } elseif ($type === Transaction::TYPE_SELL && $receiverId) {
            $this->balances[$receiverId] = (float) ($this->balances[$receiverId] ?? 0) + $amount;
        } elseif ($type === Transaction::TYPE_RETURN) {
            if ($senderId) {
                $this->balances[$senderId] = (float) ($this->balances[$senderId] ?? 0) + $amount;
            }
            if ($receiverId) {
                $this->balances[$receiverId] = (float) ($this->balances[$receiverId] ?? 0) + $amount;
            }
        } elseif ($type === Transaction::TYPE_CASH_IN || $type === Transaction::TYPE_CASH_OUT) {
            if ($senderId) {
                $this->balances[$senderId] = (float) ($this->balances[$senderId] ?? 0) + $amount;
            }
            if ($receiverId) {
                $this->balances[$receiverId] = (float) ($this->balances[$receiverId] ?? 0) + $amount;
            }
        } elseif ($type === Transaction::TYPE_TRANSFER) {
            if ($senderId) {
                $this->balances[$senderId] = (float) ($this->balances[$senderId] ?? 0) + $amount;
            }
            if ($receiverId) {
                $this->balances[$receiverId] = (float) ($this->balances[$receiverId] ?? 0) - $amount;
            }
        } elseif ($type === Transaction::TYPE_ADJUST) {
            if ($senderId) {
                $this->balances[$senderId] = (float) ($this->balances[$senderId] ?? 0) - $amount;
            }
            if ($receiverId) {
                $this->balances[$receiverId] = (float) ($this->balances[$receiverId] ?? 0) + $amount;
            }
        }
    }

    protected function adjustStockInMemory($warehouseId, $itemId, $qty)
    {
        if (! $warehouseId) {
            return;
        }
        $key = "{$warehouseId}.{$itemId}";
        $this->stocks[$key] = (float) ($this->stocks[$key] ?? 0) + $qty;
    }

    protected function updateGlobalStockInMemory($type, $itemId, $qty)
    {
        if ($type == Transaction::TYPE_BUY || $type == Transaction::TYPE_RETURN) {
            $this->itemGlobalQty[$itemId] = (float) ($this->itemGlobalQty[$itemId] ?? 0) + $qty;
        } elseif ($type == Transaction::TYPE_SELL || $type == Transaction::TYPE_RETURN_SUPPLIER) {
            $this->itemGlobalQty[$itemId] = (float) ($this->itemGlobalQty[$itemId] ?? 0) - $qty;
        }
    }

    protected function trackDailyReport($type, $date, $senderId, $receiverId, $amount)
    {
        $column = match ((int) $type) {
            Transaction::TYPE_BUY => 'buy',
            Transaction::TYPE_SELL => 'sell',
            Transaction::TYPE_RETURN => 'return',
            Transaction::TYPE_RETURN_SUPPLIER => 'return_supplier',
            Transaction::TYPE_MOVE => 'move',
            Transaction::TYPE_TRANSFER => 'transfer',
            Transaction::TYPE_ADJUST => 'adjust',
            Transaction::TYPE_PRODUCTION => 'use',
            Transaction::TYPE_CASH_IN => 'sell',
            Transaction::TYPE_CASH_OUT => 'buy',
            default => null,
        };

        if (! $column) {
            return;
        }

        $dateStr = substr($date, 0, 10);
        $type = (int) $type;

        $trackSender = in_array($type, [
            Transaction::TYPE_BUY,
            Transaction::TYPE_RETURN,
            Transaction::TYPE_CASH_IN,
            Transaction::TYPE_CASH_OUT,
            Transaction::TYPE_TRANSFER,
            Transaction::TYPE_ADJUST,
        ], true);

        $trackReceiver = in_array($type, [
            Transaction::TYPE_SELL,
            Transaction::TYPE_RETURN,
            Transaction::TYPE_CASH_IN,
            Transaction::TYPE_CASH_OUT,
            Transaction::TYPE_TRANSFER,
            Transaction::TYPE_ADJUST,
        ], true);

        if ($senderId && $trackSender) {
            $senderAmt = $type === Transaction::TYPE_ADJUST ? -$amount : $amount;
            $this->addDaily($senderId, $dateStr, $column, $senderAmt);
        }

        if ($receiverId && $trackReceiver) {
            $receiverAmt = $type === Transaction::TYPE_TRANSFER ? -$amount : $amount;
            $this->addDaily($receiverId, $dateStr, $column, $receiverAmt);
        }
    }

    protected function addDaily($id, $date, $col, $amt)
    {
        $key = "{$id}.{$date}";
        if (! isset($this->dailies[$key])) {
            $this->dailies[$key] = ['buy' => 0, 'sell' => 0, 'return' => 0, 'return_supplier' => 0, 'move' => 0, 'transfer' => 0, 'adjust' => 0, 'use' => 0];
        }
        $this->dailies[$key][$col] += $amt;
    }

    protected function saveResultsToDb()
    {
        $this->info('Saving stocks to database...');
        foreach (array_chunk($this->stocks, 1000, true) as $chunk) {
            $data = [];
            foreach ($chunk as $key => $qty) {
                [$wId, $iId] = explode('.', $key);
                $data[] = [
                    'warehouse_id' => $wId,
                    'item_id' => $iId,
                    'warehouse_type' => $this->addrbookTypes[$wId] ?? 2,
                    'quantity' => $qty,
                    'updated_at' => now(),
                    'created_at' => now(),
                ];
            }
            DB::table('warehouse_item')->insert($data);
        }

        $this->info('Saving balances to database...');
        foreach (array_chunk($this->balances, 1000, true) as $chunk) {
            $data = [];
            foreach ($chunk as $id => $bal) {
                $data[] = ['customer_id' => $id, 'balance' => $bal];
            }
            DB::table('customerstat')->insert($data);
        }

        $this->info('Updating global items quantity...');
        foreach (array_chunk($this->itemGlobalQty, 1000, true) as $chunk) {
            foreach ($chunk as $id => $qty) {
                DB::table('items')->where('id', $id)->update(['qty' => $qty]);
            }
        }

        $this->info('Saving daily reports to database...');
        foreach (array_chunk($this->dailies, 1000, true) as $chunk) {
            $data = [];
            foreach ($chunk as $key => $vals) {
                [$id, $date] = explode('.', $key);
                $data[] = array_merge(['customer_id' => $id, 'date' => $date, 'type' => $this->addrbookTypes[$id] ?? 99], $vals);
            }
            DB::table('customer_class')->insert($data);
        }
    }
}
