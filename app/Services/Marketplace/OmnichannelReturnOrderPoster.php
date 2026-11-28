<?php

namespace App\Services\Marketplace;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\MarketplaceChannelOrder;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\LocationAccessService;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OmnichannelReturnOrderPoster
{
    public function __construct(
        private OmnichannelPostReturnEligibility $eligibility,
        private OmnichannelPostSellLineResolver $lineResolver,
        private OmnichannelMarketplaceCustomerResolver $customerResolver,
        private MarketplaceReturnPostGuard $returnPostGuard,
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
        $sellInvoice = $this->eligibility->resolveSellInvoice($order);
        $returnInvoice = $this->eligibility->resolveReturnInvoice($order);

        if ($reject !== null) {
            if ($reject === 'Transaksi retur sudah ada.') {
                $this->returnPostGuard->reconcileNativeSkipBecauseReturnExists($order, $sellInvoice, $returnInvoice);
            }

            return ['posted' => false, 'skipped' => true, 'dry_run' => false, 'error' => $reject];
        }

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
            Log::info('Omnichannel post-return dry-run', [
                'channel' => $order->channel,
                'order_ref' => $order->order_ref,
                'sell_invoice' => $sellInvoice,
                'return_invoice' => $returnInvoice,
                'warehouse_id' => $warehouseId,
                'lines' => count($matched['items']),
            ]);

            return ['posted' => false, 'skipped' => false, 'dry_run' => true, 'error' => null];
        }

        $transactionDate = $order->last_event_at ?? $order->marketplace_ordered_at ?? now();

        try {
            return DB::transaction(function () use ($order, $sellInvoice, $returnInvoice, $warehouseId, $customerId, $transactionDate, $matched) {
                $locked = MarketplaceChannelOrder::query()
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->first();

                if ($locked === null) {
                    return ['posted' => false, 'skipped' => true, 'dry_run' => false, 'error' => 'Order tidak ditemukan.'];
                }

                if ($this->returnPostGuard->shouldSkipNativeReturnPost($locked, $sellInvoice, $returnInvoice)) {
                    $this->returnPostGuard->reconcileNativeSkipBecauseReturnExists($locked, $sellInvoice, $returnInvoice);

                    return ['posted' => false, 'skipped' => true, 'dry_run' => false, 'error' => 'Transaksi retur sudah ada.'];
                }

                $transactionId = $this->createReturnTransaction(
                    $warehouseId,
                    $customerId,
                    $sellInvoice,
                    $returnInvoice,
                    Carbon::parse($transactionDate)->toDateString(),
                    $matched['items'],
                    $matched['adjustment'],
                );

                if (Schema::hasColumn('marketplace_channel_orders', 'posted_return_transaction_id')) {
                    $locked->forceFill([
                        'posted_return_transaction_id' => $transactionId,
                        'posted_return_at' => now(),
                    ])->save();
                }

                Log::info('Omnichannel post-return success', [
                    'order_ref' => $locked->order_ref,
                    'sell_invoice' => $sellInvoice,
                    'return_invoice' => $returnInvoice,
                    'transaction_id' => $transactionId,
                ]);

                return ['posted' => true, 'skipped' => false, 'dry_run' => false, 'error' => null];
            });
        } catch (\Throwable $e) {
            if ($this->returnPostGuard->sellReturnAlreadyPosted($sellInvoice)
                || $this->returnPostGuard->returnInvoiceTaken($returnInvoice)) {
                $this->returnPostGuard->reconcileNativeSkipBecauseReturnExists($order, $sellInvoice, $returnInvoice);

                return ['posted' => false, 'skipped' => true, 'dry_run' => false, 'error' => 'Transaksi retur sudah ada.'];
            }

            return ['posted' => false, 'skipped' => false, 'dry_run' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return Builder<MarketplaceChannelOrder>
     */
    private function candidateQuery(?string $channelFilter, int $limit): Builder
    {
        $statuses = $this->eligibility->candidateStatuses();

        return MarketplaceChannelOrder::query()
            ->when($channelFilter !== null && $channelFilter !== '', function (Builder $q) use ($channelFilter) {
                $q->where('channel', strtolower($channelFilter));
            })
            ->whereIn('channel', $this->eligibility->allowedChannels())
            ->when($statuses !== [], function (Builder $q) use ($statuses) {
                $q->where(function (Builder $inner) use ($statuses) {
                    foreach ($statuses as $status) {
                        $inner->orWhereRaw('UPPER(TRIM(order_status)) = ?', [strtoupper($status)]);
                    }
                });
            })
            ->when(
                Schema::hasColumn('marketplace_channel_orders', 'posted_return_transaction_id'),
                fn (Builder $q) => $q->where(function (Builder $inner) {
                    $inner->whereNull('posted_return_transaction_id')
                        ->orWhere('posted_return_transaction_id', '<=', 0);
                })
            )
            ->orderByDesc('last_event_at')
            ->orderBy('id')
            ->limit($limit);
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
    private function createReturnTransaction(
        int $warehouseId,
        int $customerId,
        string $sellInvoice,
        string $returnInvoice,
        string $date,
        array $items,
        float $adjustment,
    ): int {
        return (int) DB::transaction(function () use ($warehouseId, $customerId, $sellInvoice, $returnInvoice, $date, $items, $adjustment) {
            if ($this->returnPostGuard->sellReturnAlreadyPosted($sellInvoice)
                || $this->returnPostGuard->returnInvoiceTaken($returnInvoice)) {
                throw new \RuntimeException('Transaksi retur sudah ada.');
            }

            $customer = Addrbook::findOrFail($customerId);
            $warehouse = Addrbook::findOrFail($warehouseId);
            $this->locationAccessService->ensureJubelioPartyLocations($warehouse, $customer);

            $transaction = new Transaction;
            $transaction->date = Carbon::parse($date);
            $transaction->type = Transaction::TYPE_RETURN;
            $transaction->adjustment = $adjustment;
            $transaction->user_id = Transaction::resolveJubelioCronUserId();
            $transaction->submit_type = Transaction::SUBMIT_TYPE_MARKETPLACE;
            $transaction->description = $sellInvoice;
            $transaction->notes = 'generated by omnichannel post-return';
            $transaction->invoice = $returnInvoice;
            $transaction->due = null;
            $transaction->status = Transaction::STATUS_COMPLETED;
            $transaction->sender_id = $customer->id;
            $transaction->sender_type = $customer->type;
            $transaction->receiver_id = $warehouse->id;
            $transaction->receiver_type = $warehouse->type;
            $transaction->save();

            $totalQty = 0;
            $subTotal = 0.0;

            foreach ($items as $item) {
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'date' => $transaction->date,
                    'transaction_type' => Transaction::TYPE_RETURN,
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
            $transaction->total = Transaction::signedAmount(Transaction::TYPE_RETURN, $grandTotal);
            $transaction->total_items = $totalQty;
            $transaction->save();

            $this->transactionService->handleTransaction($transaction);

            $sellTxn = Transaction::query()
                ->where('type', Transaction::TYPE_SELL)
                ->where('invoice', $sellInvoice)
                ->orderByDesc('id')
                ->first();
            if ($sellTxn !== null) {
                $sellTxn->update(['jubelio_return' => 2]);
            }

            return (int) $transaction->id;
        });
    }
}