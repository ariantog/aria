<?php

namespace App\Services\Marketplace;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\MarketplaceChannelOrder;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\WarehouseItem;
use App\Services\LocationAccessService;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OmnichannelShippedOrderPoster
{
    public function __construct(
        private OmnichannelPostSellEligibility $eligibility,
        private OmnichannelPostSellLineResolver $lineResolver,
        private OmnichannelMarketplaceCustomerResolver $customerResolver,
        private MarketplaceSellPostGuard $sellPostGuard,
        private TransactionService $transactionService,
        private LocationAccessService $locationAccessService,
    ) {}

    /**
     * @return array{processed: int, posted: int, skipped: int, dry_run: int, errors: list<string>}
     */
    public function runBatch(?string $channelFilter, int $limit, bool $forceDryRun): array
    {
        $stats = [
            'processed' => 0,
            'posted' => 0,
            'skipped' => 0,
            'dry_run' => 0,
            'errors' => [],
        ];

        if (! Schema::hasTable('marketplace_channel_orders')) {
            $stats['errors'][] = 'marketplace_channel_orders table missing.';

            return $stats;
        }

        $dryRun = $forceDryRun || $this->eligibility->configDryRun();
        $orders = $this->candidateQuery($channelFilter, $limit)->get();

        foreach ($orders as $order) {
            $stats['processed']++;
            $result = $this->postOrder($order, $dryRun);
            if ($result['skipped']) {
                $stats['skipped']++;
            } elseif ($result['dry_run']) {
                $stats['dry_run']++;
            } elseif ($result['posted']) {
                $stats['posted']++;
            } elseif ($result['error'] !== null) {
                $stats['errors'][] = $order->order_ref.': '.$result['error'];
            }
        }

        return $stats;
    }

    /**
     * @return array{posted: bool, skipped: bool, dry_run: bool, error: ?string}
     */
    public function postOrder(MarketplaceChannelOrder $order, ?bool $dryRunOverride = null): array
    {
        $dryRun = $dryRunOverride ?? $this->eligibility->configDryRun();

        $reject = $this->eligibility->rejectReason($order);
        if ($reject !== null) {
            return ['posted' => false, 'skipped' => true, 'dry_run' => false, 'error' => $reject];
        }

        $invoice = $this->eligibility->resolveInvoice($order);

        $warehouseId = (int) $order->aria_warehouse_id;
        $customerId = $this->customerResolver->customerIdForWarehouse($warehouseId);
        if ($customerId <= 0) {
            return ['posted' => false, 'skipped' => false, 'dry_run' => false, 'error' => 'Customer channel tidak ditemukan untuk gudang.'];
        }

        $rawLines = $this->lineResolver->linesForOrder($order);
        if ($rawLines === []) {
            return ['posted' => false, 'skipped' => false, 'dry_run' => false, 'error' => 'Baris item tidak ditemukan (shadow/reservasi).'];
        }

        $matched = $this->matchLinesToItems($rawLines);
        if ($matched['error'] !== null) {
            return ['posted' => false, 'skipped' => false, 'dry_run' => false, 'error' => $matched['error']];
        }

        if ($dryRun) {
            Log::info('Omnichannel post-sell dry-run', [
                'channel' => $order->channel,
                'order_ref' => $order->order_ref,
                'invoice' => $invoice,
                'warehouse_id' => $warehouseId,
                'lines' => count($matched['items']),
            ]);

            return ['posted' => false, 'skipped' => false, 'dry_run' => true, 'error' => null];
        }

        $stockError = $this->validateWarehouseStock($warehouseId, $matched['items']);
        if ($stockError !== null) {
            return ['posted' => false, 'skipped' => false, 'dry_run' => false, 'error' => $stockError];
        }

        $transactionDate = $order->marketplace_ordered_at ?? $order->last_event_at ?? now();

        try {
            return DB::transaction(function () use ($order, $invoice, $warehouseId, $customerId, $transactionDate, $matched) {
                $locked = MarketplaceChannelOrder::query()
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->first();

                if ($locked === null) {
                    return ['posted' => false, 'skipped' => true, 'dry_run' => false, 'error' => 'Order tidak ditemukan.'];
                }

                if ($this->sellPostGuard->shouldSkipNativePost($locked, $invoice)) {
                    return ['posted' => false, 'skipped' => true, 'dry_run' => false, 'error' => 'Transaction sudah ada.'];
                }

                $transactionId = $this->createSellTransaction(
                    $warehouseId,
                    $customerId,
                    $invoice,
                    Carbon::parse($transactionDate)->toDateString(),
                    $matched['items'],
                    $matched['adjustment'],
                );

                if (Schema::hasColumn('marketplace_channel_orders', 'posted_transaction_id')) {
                    $locked->forceFill([
                        'posted_transaction_id' => $transactionId,
                        'posted_at' => now(),
                    ])->save();
                }

                Log::info('Omnichannel post-sell success', [
                    'order_ref' => $locked->order_ref,
                    'invoice' => $invoice,
                    'transaction_id' => $transactionId,
                ]);

                return ['posted' => true, 'skipped' => false, 'dry_run' => false, 'error' => null];
            });
        } catch (\Throwable $e) {
            if ($this->sellPostGuard->invoiceTaken($invoice)) {
                return ['posted' => false, 'skipped' => true, 'dry_run' => false, 'error' => 'Transaction sudah ada.'];
            }

            return ['posted' => false, 'skipped' => false, 'dry_run' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return Builder<MarketplaceChannelOrder>
     */
    private function candidateQuery(?string $channelFilter, int $limit): Builder
    {
        $query = MarketplaceChannelOrder::query()
            ->when($channelFilter !== null && $channelFilter !== '', function (Builder $q) use ($channelFilter) {
                $q->where('channel', strtolower($channelFilter));
            })
            ->whereIn('channel', $this->eligibility->allowedChannels())
            ->when(
                Schema::hasColumn('marketplace_channel_orders', 'posted_transaction_id'),
                fn (Builder $q) => $q->where(function (Builder $inner) {
                    $inner->whereNull('posted_transaction_id')
                        ->orWhere('posted_transaction_id', '<=', 0);
                })
            )
            ->orderBy('last_event_at')
            ->orderBy('id')
            ->limit($limit);

        return $query;
    }

    /**
     * @param  list<array{sku: string, qty: float, amount: float|null}>  $rawLines
     * @return array{error: ?string, items: list<array<string, mixed>>, adjustment: float}
     */
    private function matchLinesToItems(array $rawLines): array
    {
        $items = [];
        $subtotal = 0.0;

        foreach ($rawLines as $line) {
            $sku = strtoupper(trim((string) $line['sku']));
            $qty = (float) $line['qty'];
            $item = Item::query()
                ->where('code', $sku)
                ->orWhere('legacy_code', $sku)
                ->orderBy('id')
                ->first();

            if ($item === null) {
                return ['error' => 'SKU tidak ditemukan: '.$sku, 'items' => [], 'adjustment' => 0.0];
            }

            $amount = $line['amount'] ?? null;
            if ($amount !== null && $amount > 0) {
                $lineSubtotal = (float) $amount;
                $unit = $qty > 0 ? $lineSubtotal / $qty : $lineSubtotal;
            } else {
                $unit = (float) ($item->price ?? 0);
                $lineSubtotal = $unit * $qty;
            }

            $items[] = [
                'itemId' => (int) $item->id,
                'code' => (string) $item->code,
                'quantity' => $qty,
                'price' => $unit,
                'subtotal' => $lineSubtotal,
            ];
            $subtotal += $lineSubtotal;
        }

        return ['error' => null, 'items' => $items, 'adjustment' => 0.0];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function validateWarehouseStock(int $warehouseId, array $items): ?string
    {
        $warehouse = Addrbook::find($warehouseId);
        if (! $warehouse || ! Addrbook::typeIsWarehouse((int) $warehouse->type)) {
            return null;
        }
        if (Addrbook::typeAllowsNegativeStock((int) $warehouse->type)) {
            return null;
        }

        $insufficient = [];
        foreach ($items as $item) {
            $wi = WarehouseItem::query()
                ->where('warehouse_id', $warehouseId)
                ->where('item_id', $item['itemId'])
                ->first();
            $available = $wi ? (float) $wi->quantity : 0.0;
            $needed = (float) $item['quantity'];
            if ($needed > $available) {
                $insufficient[] = "{$item['code']} (avail: {$available}, need: {$needed})";
            }
        }

        if ($insufficient === []) {
            return null;
        }

        return 'Stok tidak cukup: '.implode(', ', $insufficient);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function createSellTransaction(
        int $warehouseId,
        int $customerId,
        string $invoice,
        string $date,
        array $items,
        float $adjustment,
    ): int {
        return (int) DB::transaction(function () use ($warehouseId, $customerId, $invoice, $date, $items, $adjustment) {
            if ($this->sellPostGuard->invoiceTaken($invoice)) {
                throw new \RuntimeException('Transaction sudah ada.');
            }

            $customer = Addrbook::findOrFail($customerId);
            $warehouse = Addrbook::findOrFail($warehouseId);
            $this->locationAccessService->ensureJubelioPartyLocations($warehouse, $customer);

            $transaction = new Transaction;
            $transaction->date = Carbon::parse($date);
            $transaction->type = Transaction::TYPE_SELL;
            $transaction->adjustment = $adjustment;
            $transaction->user_id = Transaction::resolveJubelioCronUserId();
            $transaction->submit_type = Transaction::SUBMIT_TYPE_MARKETPLACE;
            $transaction->description = $invoice;
            $transaction->notes = 'generated by omnichannel post-shipped-orders';
            $transaction->invoice = $invoice;
            $transaction->due = null;
            $transaction->status = Transaction::STATUS_COMPLETED;
            $transaction->sender_id = $warehouse->id;
            $transaction->sender_type = $warehouse->type;
            $transaction->receiver_id = $customer->id;
            $transaction->receiver_type = $customer->type;
            $transaction->save();

            $totalQty = 0;
            $subTotal = 0.0;

            foreach ($items as $item) {
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'date' => $transaction->date,
                    'transaction_type' => Transaction::TYPE_SELL,
                    'sender_id' => $transaction->sender_id,
                    'receiver_id' => $transaction->receiver_id,
                    'item_id' => $item['itemId'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'discount' => 0,
                    'total' => $item['subtotal'],
                ]);
                $totalQty += $item['quantity'];
                $subTotal += $item['subtotal'];
            }

            $grandTotal = $subTotal + $adjustment;
            $transaction->total = Transaction::signedAmount(Transaction::TYPE_SELL, $grandTotal);
            $transaction->total_items = $totalQty;
            $transaction->save();

            $this->transactionService->handleTransaction($transaction);

            return (int) $transaction->id;
        });
    }
}