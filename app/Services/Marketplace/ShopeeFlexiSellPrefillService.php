<?php

namespace App\Services\Marketplace;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubeliosync;
use App\Services\Jubelio\JubelioSellInvoiceGuard;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Support\Facades\Schema;

/**
 * Build session prefill for manual FLEXI sell (form entry, not auto-post).
 */
class ShopeeFlexiSellPrefillService
{
    public function __construct(
        private ShopeeAdminOpenApiService $shopeeApi,
        private ShopeeSyncInvoicePrefix $invoicePrefix,
        private ShopeeSyncWarehouseResolver $warehouseResolver,
        private ShopeeFlexiOrderAmountResolver $amountResolver,
        private ShopeeFlexiSellItemMatcher $itemMatcher,
        private JubelioSellInvoiceGuard $sellInvoiceGuard,
        private ShopeeFlexiOrderLookupService $orderLookup,
        private ShopeeFlexiStagingWarehouse $stagingWarehouse,
    ) {}

    /**
     * @return array{success: bool, message: string, prefill?: array<string, mixed>}
     */
    public function build(int $shopId, string $orderSn): array
    {
        $orderSn = trim($orderSn);
        if ($shopId <= 0 || $orderSn === '') {
            return ['success' => false, 'message' => 'shop_id dan ordersn wajib.'];
        }

        $invoice = $this->invoicePrefix->invoiceForOrderSn($shopId, $orderSn);

        if ($this->sellInvoiceGuard->sellInvoiceInTransactions($invoice)) {
            return ['success' => false, 'message' => 'Invoice sudah ada di Transaksi.'];
        }

        if (! $this->shopeeApi->hasShopAuthorizationFor($shopId)) {
            return ['success' => false, 'message' => "Shop {$shopId} belum diotorisasi OAuth."];
        }

        $detail = $this->shopeeApi->getOrderDetail($orderSn, $shopId, ShopeeFlexiOrderDetailFields::OPTIONAL);
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

        $orderStatus = (string) ($orderRow['order_status'] ?? '');
        if (! $this->orderLookup->orderStatusAllowsManualSell($orderStatus)) {
            return [
                'success' => false,
                'message' => 'Order belum COMPLETED — Buat Transaksi Manual ditahan (status: '.($orderStatus !== '' ? $orderStatus : '—').').',
            ];
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

        $amounts = $this->amountResolver->resolve($shopId, $orderSn, $orderRow);
        if (! ($amounts['escrow_loaded'] ?? false)) {
            return ['success' => false, 'message' => 'Escrow belum dimuat dari Shopee (get_escrow_detail).'];
        }

        if (! $this->amountResolver->amountsUsableForSellPrefill($amounts)) {
            return ['success' => false, 'message' => 'Nominal escrow tidak cukup untuk prefill SELL — periksa exchange_rate / get_escrow_detail.'];
        }

        $matched = $this->itemMatcher->matchLines($amounts['lines']);
        if ($matched['error'] !== null) {
            return ['success' => false, 'message' => $matched['error']];
        }

        if ($matched['items'] === []) {
            return ['success' => false, 'message' => 'Tidak ada baris item yang bisa diprefill.'];
        }

        $pickupWarehouse = Addrbook::find($warehouseId);
        $customer = Addrbook::find($customerId);
        if (! $pickupWarehouse || ! $customer) {
            return ['success' => false, 'message' => 'Customer atau gudang tidak ditemukan.'];
        }

        $staging = $this->stagingWarehouse->resolveWarehouse();
        $warehouse = $staging ?? $pickupWarehouse;

        $adjustment = $this->resolveHeaderAdjustment($amounts, $matched['items']);
        $prefillItems = $this->buildPrefillItems($matched['items']);

        $note = filled($amounts['note_reference'] ?? null)
            ? (string) $amounts['note_reference']
            : 'Shopee FLEXI ('.$orderSn.')';

        return [
            'success' => true,
            'message' => 'ok',
            'prefill' => [
                'sender_id' => (string) $warehouse->id,
                'sender' => ['id' => $warehouse->id, 'name' => $warehouse->name],
                'receiver_id' => (string) $customer->id,
                'receiver' => ['id' => $customer->id, 'name' => $customer->name],
                'invoice' => $invoice,
                'note' => $note,
                'adjustment' => $adjustment,
                'items' => $prefillItems,
            ],
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
     * @return list<array<string, mixed>>
     */
    private function buildPrefillItems(array $matchedItems): array
    {
        $itemIds = collect($matchedItems)->pluck('itemId')->map(fn ($id) => (int) $id)->all();
        $loadedItems = Item::query()
            ->with('warehouseItems')
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($matchedItems as $row) {
            $item = $loadedItems->get((int) $row['itemId']);
            if (! $item) {
                continue;
            }

            $quantity = (float) $row['quantity'];
            $price = (float) $row['price'];

            $out[] = [
                'item_id' => (string) $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'jubelio_item_id' => (int) ($item->jubelio_item_id ?? 0),
                'quantity' => $quantity,
                'price' => $price,
                'discount' => 0,
                'subtotal' => (float) $row['subtotal'],
                'warehouse_item' => $item->warehouseItems->map(fn ($wi) => [
                    'warehouse_id' => (string) $wi->warehouse_id,
                    'quantity' => (float) $wi->quantity,
                ])->values()->all(),
            ];
        }

        return $out;
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
}