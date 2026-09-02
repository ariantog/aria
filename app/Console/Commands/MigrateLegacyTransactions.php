<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateLegacyTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:legacy-transactions {--year=2024 : The year to migrate} {--month= : Optional: The month to migrate} {--only : Only migrate the specific year provided}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate transactions with HIGH PERFORMANCE optimized logic.';

    // In-memory state tracking
    protected array $balances = [];

    protected array $stocks = [];

    protected array $dailies = [];

    protected array $itemGlobalQty = [];

    /**
     * Execute the console command.
     */
    public function handle(TransactionService $service)
    {
        $year = $this->option('year');
        $month = $this->option('month');
        $only = $this->option('only');

        $scopeText = $only ? "ONLY year {$year}" : "from year {$year} onwards";
        if ($month) {
            $scopeText .= " (Month: {$month})";
        }
        $this->info("Starting HIGH PERFORMANCE migration for {$scopeText}");

        $this->setupLegacyConnection();

        try {
            DB::connection('legacy')->getPdo();
        } catch (\Exception $e) {
            $this->error('Could not connect to legacy database: '.$e->getMessage());

            return 1;
        }

        // Initialize current state from local DB
        $this->initializeState();

        Schema::disableForeignKeyConstraints();

        try {
            $this->migrateOptimized($year, $only);

            $this->finalizeMigration();

            $this->info('Migration process finished (Data migrated and aggregated).');
        } catch (\Exception $e) {
            $this->error('Migration failed: '.$e->getMessage());
            $this->error($e->getTraceAsString());
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    protected function setupLegacyConnection()
    {
        Config::set('database.connections.legacy', [
            'driver' => 'mysql',
            'host' => env('LEGACY_DB_HOST', '127.0.0.1'),
            'port' => env('LEGACY_DB_PORT', '3306'),
            'database' => env('LEGACY_DB_DATABASE', 'core'),
            'username' => env('LEGACY_DB_USERNAME', 'root'),
            'password' => env('LEGACY_DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);
    }

    protected function initializeState()
    {
        $this->info('Initializing current state from local database...');
        $this->balances = DB::table('customerstat')->pluck('balance', 'customer_id')->toArray();
        $this->itemGlobalQty = DB::table('items')->pluck('qty', 'id')->toArray();

        // Stock initialization simplified to avoid memory bloat
        $this->stocks = [];
    }

    protected function migrateOptimized($year, $only)
    {
        $month = $this->option('month');
        $operator = $only ? '=' : '>=';
        $query = DB::connection('legacy')->table('transactions')
            ->whereYear('date', $operator, $year)
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc');

        if ($month) {
            $query->whereMonth('date', $month);
        }

        $total = $query->count();
        $this->info("Total transactions to process: {$total}");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $addrbookTypes = DB::table('customers')->pluck('type', 'id')->toArray();

        // Reduce chunk size to avoid "Too many placeholders" error
        $query->chunkById(1000, function ($transactions) use ($bar) {
            $transactionBuffer = [];
            $detailBuffer = [];
            $trxIds = $transactions->pluck('id')->toArray();

            $allDetails = DB::connection('legacy')->table('transaction_details')
                ->whereIn('transaction_id', $trxIds)
                ->get()
                ->groupBy('transaction_id');

            foreach ($transactions as $lTrans) {
                $data = (array) $lTrans;

                $senderId = $data['sender_id'];
                $receiverId = $data['receiver_id'];

                // Use raw legacy types to ensure consistency (even if they are 0)
                $senderType = $data['sender_type'];
                $receiverType = $data['receiver_type'];

                $lDiscountPercent = (float) ($data['discount'] ?? 0);
                $lTotal = (float) ($data['total'] ?? 0);
                $lPpn = (float) ($data['ppn'] ?? $data['ppn'] ?? 0);

                $details = $allDetails[$data['id']] ?? collect();
                $subtotal = $details->sum('total');

                // In the new system, 'total' must match legacy 'total' for reports to be consistent.
                // Legacy 'total' is the amount after transaction discount and adjustments, but before tax.
                $total = $lTotal;

                // Nominal discount calculation for the header
                $nominalDiscount = $subtotal * ($lDiscountPercent / 100);

                $grandTotal = $lTotal;
                if ($lPpn > 0) {
                    // Adjust grandTotal with tax (following the sign of total)
                    if ($total < 0) {
                        $grandTotal -= $lPpn;
                    } else {
                        $grandTotal += $lPpn;
                    }
                }

                if ($data['type'] == Transaction::TYPE_SELL || $data['type'] == Transaction::TYPE_RETURN_SUPPLIER) {
                    $total = -abs($total);
                    $grandTotal = -abs($grandTotal);
                }

                $this->calculateBalances($data, $senderId, $receiverId, $grandTotal);

                $createdAt = $data['created_at'];
                $updatedAt = $data['updated_at'] ?? $createdAt;
                if ($updatedAt == '0000-00-00 00:00:00') {
                    $updatedAt = $createdAt;
                }

                $transactionBuffer[] = [
                    'id' => $data['id'],
                    'date' => $data['date'],
                    'type' => (int) $data['type'],
                    'sender_id' => $senderId,
                    'sender_type' => $senderType,
                    'sender_balance' => (float) ($this->balances[$senderId] ?? 0),
                    'receiver_id' => $receiverId,
                    'receiver_type' => $receiverType,
                    'receiver_balance' => (float) ($this->balances[$receiverId] ?? 0),
                    'invoice' => $data['invoice'] ?? $data['invoice'] ?? (string) $data['id'],
                    'due' => (isset($data['due']) && $data['due'] !== '0000-00-00') ? $data['due'] : null,
                    'description' => $data['description'] ?? null,
                    'notes' => $data['notes'] ?? $data['note'] ?? null,
                    'real_total' => 0,
                    'total' => $grandTotal,
                    'discount' => $nominalDiscount,
                    'discount_percent' => $lDiscountPercent,
                    'adjustment' => (float) ($data['adjustment'] ?? 0),
                    'ppn' => $lPpn,
                    'total_items' => (float) ($data['total_items'] ?? 0),
                    'user_id' => ($data['user_id'] < 0) ? 1 : $data['user_id'],
                    'status' => $data['status'] ?? 1,
                    'submit_type' => (int) ($data['submit_type'] ?? 1),
                    'sync_hide' => $data['sync_hide'] ?? 'N',
                    'a_submit_by' => $data['a_submit_by'] ?? null,
                    'b_submit_by' => $data['b_submit_by'] ?? null,
                    'a_reference_id' => $data['a_reference_id'] ?? null,
                    'b_reference_id' => $data['b_reference_id'] ?? null,
                    'submit_a_count' => $data['submit_a_count'] ?? 0,
                    'submit_b_count' => $data['submit_b_count'] ?? 0,
                    'jubelio_return' => $data['jubelio_return'] ?? 0,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ];

                if (isset($allDetails[$data['id']])) {
                    foreach ($allDetails[$data['id']] as $lDetail) {
                        $itemId = $lDetail->item_id;

                        // SKIP: If item doesn't exist in our local database
                        if (! isset($this->itemGlobalQty[$itemId])) {
                            continue;
                        }

                        $dQty = (float) ($lDetail->quantity ?? 0);
                        $dPrice = (float) ($lDetail->price ?? 0);
                        $dTotal = (float) ($lDetail->total ?? 0);

                        $this->adjustStockInMemory($senderId, $itemId, -$dQty);
                        $this->adjustStockInMemory($receiverId, $itemId, $dQty);
                        $this->updateGlobalStockInMemory($data['type'], $itemId, $dQty);

                        $dCreatedAt = $lDetail->created_at ?? $createdAt;
                        $dUpdatedAt = $lDetail->updated_at ?? $dCreatedAt;
                        if ($dUpdatedAt == '0000-00-00 00:00:00') {
                            $dUpdatedAt = $dCreatedAt;
                        }

                        // In the new system, detail discount is NOMINAL
                        $dDiscountNominal = ($dPrice * $dQty) - $dTotal;

                        $detailBuffer[] = [
                            'id' => $lDetail->id,
                            'transaction_id' => $data['id'],
                            'item_id' => $itemId,
                            'date' => $lDetail->date,
                            'transaction_type' => (int) $lDetail->transaction_type,
                            'sender_id' => $lDetail->sender_id,
                            'receiver_id' => $lDetail->receiver_id,
                            'quantity' => $dQty,
                            'price' => $dPrice,
                            'discount' => $dDiscountNominal,
                            'total' => $dTotal,
                            'notes' => $lDetail->notes ?? null,
                            'created_at' => $dCreatedAt,
                            'updated_at' => $dUpdatedAt,
                        ];
                    }
                }

                $this->trackDailyReport($data['type'], $data['date'], $senderId, $receiverId, $total);
                $bar->advance();
            }

            // High Performance Upsert
            if (! empty($transactionBuffer)) {
                DB::table('transactions')->upsert($transactionBuffer, ['id'], [
                    'date', 'type', 'sender_id', 'sender_type', 'sender_balance',
                    'receiver_id', 'receiver_type', 'receiver_balance', 'invoice',
                    'due', 'description', 'notes',
                    'real_total', 'total', 'discount', 'discount_percent', 'adjustment', 'ppn',
                    'total_items', 'user_id', 'status', 'submit_type',
                    'sync_hide', 'a_submit_by', 'b_submit_by', 'a_reference_id', 'b_reference_id',
                    'submit_a_count', 'submit_b_count', 'jubelio_return',
                    'created_at', 'updated_at',
                ]);
            }

            if (! empty($detailBuffer)) {
                // Chunk details to avoid "Query too large" or memory issues
                foreach (array_chunk($detailBuffer, 500) as $chunk) {
                    DB::table('transaction_details')->upsert($chunk, ['id'], [
                        'transaction_id', 'item_id', 'date', 'transaction_type', 'sender_id', 'receiver_id', 'quantity', 'price', 'discount', 'total', 'notes', 'created_at', 'updated_at',
                    ]);
                }
            }
        });

        $bar->finish();
        $this->newLine();
    }

    protected function calculateBalances($data, $senderId, $receiverId, $amount)
    {
        $type = $data['type'];
        if ($type == Transaction::TYPE_BUY || $type == Transaction::TYPE_RETURN) {
            $this->balances[$senderId] = (float) ($this->balances[$senderId] ?? 0) + $amount;
        } elseif ($type == Transaction::TYPE_SELL || $type == Transaction::TYPE_RETURN_SUPPLIER) {
            $this->balances[$receiverId] = (float) ($this->balances[$receiverId] ?? 0) + $amount;
        } elseif ($type == Transaction::TYPE_CASH_IN || $type == Transaction::TYPE_CASH_OUT) {
            $this->balances[$senderId] = (float) ($this->balances[$senderId] ?? 0) + $amount;
            $this->balances[$receiverId] = (float) ($this->balances[$receiverId] ?? 0) + $amount;
        } elseif ($type == Transaction::TYPE_TRANSFER || $type == Transaction::TYPE_ADJUST) {
            $this->balances[$senderId] = (float) ($this->balances[$senderId] ?? 0) - $amount;
            $this->balances[$receiverId] = (float) ($this->balances[$receiverId] ?? 0) + $amount;
        }
    }

    protected function adjustStockInMemory($warehouseId, $itemId, $qty)
    {
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

        // One-sided attribution to match legacy reporting logic
        // CASH_IN & RETURN: Hanya dihitung dari sisi Sender.
        // CASH_OUT & SELL: Hanya dihitung dari sisi Receiver.
        // BUY & RETURN_SUPPLIER: Hanya dihitung dari sisi Receiver (Warehouse/Account side).

        if ($type == Transaction::TYPE_CASH_IN || $type == Transaction::TYPE_RETURN) {
            $this->addDaily($senderId, $dateStr, $column, $amount);
        } elseif ($type == Transaction::TYPE_CASH_OUT || $type == Transaction::TYPE_SELL || $type == Transaction::TYPE_BUY || $type == Transaction::TYPE_RETURN_SUPPLIER) {
            $this->addDaily($receiverId, $dateStr, $column, $amount);
        } else {
            // Default behavior for other types (TRANSFER, MOVE, ADJUST, etc.)
            $this->addDaily($senderId, $dateStr, $column, (int) $type == Transaction::TYPE_TRANSFER ? -$amount : $amount);
            $this->addDaily($receiverId, $dateStr, $column, $amount);
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

    protected function finalizeMigration()
    {
        $this->info('Finalizing migration (Saving aggregated states)...');

        DB::transaction(function () {
            // 1. Save Addrbook Stats
            $this->info('Saving addrbook balances...');
            foreach ($this->balances as $id => $balance) {
                DB::table('customerstat')->updateOrInsert(
                    ['customer_id' => $id],
                    ['balance' => $balance, 'updated_at' => now()]
                );
            }

            // 2. Save Item Global Qty
            $this->info('Saving item global quantities...');
            foreach ($this->itemGlobalQty as $id => $qty) {
                DB::table('items')->where('id', $id)->update([
                    'qty' => $qty,
                    'updated_at' => now(),
                ]);
            }

            // 3. Save Warehouse Items (Stock per Warehouse)
            $this->info('Saving warehouse stocks...');
            $wsBuffer = [];
            $addrbookTypes = DB::table('customers')->pluck('type', 'id')->toArray();
            foreach ($this->stocks as $key => $qty) {
                [$warehouseId, $itemId] = explode('.', $key);
                $wsBuffer[] = [
                    'warehouse_id' => (int) $warehouseId,
                    'warehouse_type' => (int) ($addrbookTypes[$warehouseId] ?? 0),
                    'item_id' => (int) $itemId,
                    'quantity' => $qty,
                    'updated_at' => now(),
                ];
            }
            foreach (array_chunk($wsBuffer, 500) as $chunk) {
                DB::table('warehouse_item')->upsert($chunk, ['warehouse_id', 'item_id', 'warehouse_type'], ['quantity', 'updated_at']);
            }

            // 4. Save Daily Reports
            $this->info('Saving daily reports...');
            $dailyBuffer = [];
            foreach ($this->dailies as $key => $cols) {
                [$addrbookId, $date] = explode('.', $key);
                $dailyBuffer[] = array_merge([
                    'customer_id' => (int) $addrbookId,
                    'date' => $date,
                    'type' => (int) ($addrbookTypes[$addrbookId] ?? 0),
                    'updated_at' => now(),
                ], $cols);
            }
            foreach (array_chunk($dailyBuffer, 500) as $chunk) {
                DB::table('customer_class')->upsert($chunk, ['customer_id', 'date'], [
                    'buy', 'sell', 'return', 'return_supplier', 'move', 'transfer', 'adjust', 'use', 'type', 'updated_at',
                ]);
            }
        });
    }
}
