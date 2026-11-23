<?php

namespace App\Actions\Marketplace;

use App\Models\Addrbook;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\WarehouseItem;
use App\Services\Jubelio\JubelioSellInvoiceGuard;
use App\Services\LocationAccessService;
use App\Services\Marketplace\ShopeeFlexiSellItemMatcher;
use App\Services\Marketplace\ShopeeOrderLineSummary;
use App\Services\Marketplace\ShopeeOrderPickupLocation;
use App\Services\Marketplace\ShopeeSyncInvoicePrefix;
use App\Services\Marketplace\ShopeeSyncWarehouseResolver;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Post a Shopee FLEXI/export SELL directly from Shopee get_order_detail — no Jubelio API payload.
 */
class ProcessShopeeFlexiSellOrder
{
    private const ORDER_DETAIL_OPTIONAL_FIELDS = 'order_status,item_list,package_list,total_amount';

    public function __construct(
        private ShopeeAdminOpenApiService $shopeeApi,
        private ShopeeSyncInvoicePrefix $invoicePrefix,
        private ShopeeSyncWarehouseResolver $warehouseResolver,
        private ShopeeOrderPickupLocation $pickupLocation,
        private ShopeeOrderLineSummary $lineSummary,
        private ShopeeFlexiSellItemMatcher $itemMatcher,
        private JubelioSellInvoiceGuard $sellInvoiceGuard,
        private TransactionService $transactionService,
        private LocationAccessService $locationAccessService,
    ) {}

    /**
     * @return array{success: bool, message: string, transaction_id?: int}
     */
    public function execute(int $shopId, string $orderSn, ?int $executedByUserId = null): array
    {
        $orderSn = trim($orderSn);
        if ($shopId <= 0 || $orderSn === '') {
            return ['success' => false, 'message' => 'shop_id dan ordersn wajib.'];
        }

        $invoice = $this->invoicePrefix->invoiceForOrderSn($shopId, $orderSn);

        if ($this->sellInvoiceGuard->sellInvoiceInTransactions($invoice)) {
            return ['success' => false, 'message' => 'Transaction sudah ada'];
        }

        if (! $this->shopeeApi->hasShopAuthorizationFor($shopId)) {
            return ['success' => false, 'message' => "Shop {$shopId} belum diotorisasi OAuth."];
        }

        $detail = $this->shopeeApi->getOrderDetail($orderSn, $shopId, self::ORDER_DETAIL_OPTIONAL_FIELDS);
        if ($detail === null || filled($detail['error'] ?? null)) {
            $message = (string) ($detail['message'] ?? $detail['error'] ?? 'get_order_detail gagal.');

            return ['success' => false, 'message' => $message];
        }

        $orderRow = is_array($detail['response']['order_list'][0] ?? null)
            ? $detail['response']['order_list'][0]
            : null;

        if ($orderRow === null) {
            return ['success' => false, 'message' => 'Order tidak ditemukan di Shopee API.'];
        }

        $pushData = [
            'ordersn' => $orderSn,
            'order_sn' => $orderSn,
            'item_list' => $orderRow['item_list'] ?? [],
            'order_detail' => $orderRow,
        ];

        $warehouseId = $this->warehouseResolver->ariaWarehouseIdForIngest($shopId, $pushData, $orderRow);
        if ($warehouseId <= 0) {
            return ['success' => false, 'message' => 'Gudang Aria tidak ter-resolve — cek Warehouse Map (product_location_id / pickup).'];
        }

        $customerId = $this->resolveCustomerId($warehouseId);
        if ($customerId <= 0) {
            return ['success' => false, 'message' => 'Customer channel tidak ditemukan — mapping jubeliosyncs untuk gudang ini butuh customer_id.'];
        }

        $summary = $this->lineSummary->fromOrderRow($orderRow);
        $matched = $this->itemMatcher->matchLines($summary['lines']);
        if ($matched['error'] !== null) {
            return ['success' => false, 'message' => $matched['error']];
        }

        $stockError = $this->validateWarehouseStock($warehouseId, $matched['items']);
        if ($stockError !== null) {
            return ['success' => false, 'message' => $stockError];
        }

        $adjustment = $this->resolveHeaderAdjustment($summary, $matched['items']);
        $transactionDate = $this->resolveTransactionDate($orderRow);

        try {
            $create = DB::transaction(function () use (
                $warehouseId,
                $customerId,
                $invoice,
                $orderSn,
                $matched,
                $adjustment,
                $transactionDate,
                $executedByUserId,
            ) {
                return $this->createSellTransaction(
                    $warehouseId,
                    $customerId,
                    $invoice,
                    $orderSn,
                    $matched['items'],
                    $adjustment,
                    $transactionDate,
                    $executedByUserId,
                );
            });
        } catch (\Throwable $e) {
            Log::error('ProcessShopeeFlexiSellOrder: '.$e->getMessage(), ['invoice' => $invoice]);

            return ['success' => false, 'message' => $e->getMessage()];
        }

        if (($create['status'] ?? '') !== '200') {
            return ['success' => false, 'message' => (string) ($create['message'] ?? 'Gagal membuat transaksi.')];
        }

        $transactionId = (int) ($create['transaction_id'] ?? 0);
        $this->touchQueueRowOnSuccess($invoice, $executedByUserId);

        Log::info("Shopee FLEXI sell posted: {$invoice}", ['transaction_id' => $transactionId]);

        return [
            'success' => true,
            'message' => 'Transaksi berhasil dibuat',
            'transaction_id' => $transactionId,
        ];
    }

    private function resolveCustomerId(int $warehouseId): int
    {
        if (! Schema::hasTable('jubeliosyncs')) {
            return 0;
        }

        $sync = Jubeliosync::query()
            ->where('warehouse_id', $warehouseId)
            ->where('customer_id', '>', 0)
            ->whereMappedJubelioLocation()
            ->orderBy('id')
            ->first();

        if ($sync !== null) {
            return (int) $sync->customer_id;
        }

        $fallback = Jubeliosync::query()
            ->where('warehouse_id', $warehouseId)
            ->where('customer_id', '>', 0)
            ->orderBy('id')
            ->first();

        return $fallback !== null ? (int) $fallback->customer_id : 0;
    }

    /**
     * @param  list<array<string, mixed>>  $matchedItems
     */
    private function resolveHeaderAdjustment(array $summary, array $matchedItems): float
    {
        $itemsTotal = (float) collect($matchedItems)->sum('subtotal');
        $headerTotal = $summary['total_amount'] ?? null;
        if ($headerTotal === null) {
            return 0.0;
        }

        return round((float) $headerTotal - $itemsTotal, 2);
    }

    /**
     * @param  array<string, mixed>  $orderRow
     */
    private function resolveTransactionDate(array $orderRow): string
    {
        foreach (['update_time', 'create_time', 'pay_time'] as $key) {
            $ts = (int) ($orderRow[$key] ?? 0);
            if ($ts > 0) {
                return Carbon::createFromTimestamp($ts)->toDateString();
            }
        }

        return now()->toDateString();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{status: string, message: string, transaction_id?: int}
     */
    private function createSellTransaction(
        int $warehouseId,
        int $customerId,
        string $invoice,
        string $orderSn,
        array $items,
        float $adjustment,
        string $transactionDate,
        ?int $executedByUserId,
    ): array {
        $customer = Addrbook::find($customerId);
        $warehouse = Addrbook::find($warehouseId);

        if (! $customer || ! $warehouse) {
            throw new \RuntimeException('Customer atau gudang tidak ditemukan.');
        }

        $this->locationAccessService->ensureJubelioPartyLocations($warehouse, $customer);

        $transaction = new Transaction;
        $transaction->date = Carbon::parse($transactionDate);
        $transaction->type = Transaction::TYPE_SELL;
        $transaction->adjustment = $adjustment;
        $transaction->user_id = $executedByUserId ?? Transaction::resolveJubelioCronUserId();
        $transaction->submit_type = Transaction::SUBMIT_TYPE_MANUAL;
        $transaction->description = $invoice;
        $transaction->notes = 'Shopee FLEXI manual sell ('.$orderSn.')';
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

        $grandTotal = $subTotal + (float) $transaction->adjustment;
        $transaction->total = Transaction::signedAmount(Transaction::TYPE_SELL, $grandTotal);
        $transaction->total_items = $totalQty;
        $transaction->save();

        $this->transactionService->handleTransaction($transaction);

        return [
            'status' => '200',
            'message' => 'ok',
            'transaction_id' => $transaction->id,
        ];
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

        return 'Stok tidak cukup di gudang '.$warehouse->name.': '.implode(', ', $insufficient);
    }

    private function touchQueueRowOnSuccess(string $invoice, ?int $executedByUserId): void
    {
        $order = Jubelioorder::query()
            ->where('type', 'SELL')
            ->where('invoice', $invoice)
            ->first();

        if ($order === null || $order->isSuccessful()) {
            return;
        }

        $order->update([
            'run_count' => (int) $order->run_count + 1,
            'error_type' => 10,
            'error' => null,
            'stock_error_items' => null,
            'status' => 2,
            'execute_by' => $executedByUserId ?? 0,
        ]);
    }
}