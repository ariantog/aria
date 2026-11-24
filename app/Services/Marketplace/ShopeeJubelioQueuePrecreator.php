<?php

namespace App\Services\Marketplace;

use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\MarketplaceChannelOrder;
use App\Models\Shopeesync;
use App\Services\Jubelio\JubelioOrderReadyToShip;
use App\Services\Jubelio\JubelioSellInvoiceGuard;
use App\Support\ShopeeTestPushDetector;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only ingest expansion: upsert jubelioorders placeholder on Shopee order-status push.
 * Does not post SELL or touch warehouse_item — Jubelio webhook/cron remains the posting path.
 */
class ShopeeJubelioQueuePrecreator
{
    public const PLACEHOLDER_PREFIX = 'shopee-push:';

    /** New ingress when {@see self::onOrderPrecreateEnabled()} — pairs Jubelio SELL with on_order release. */
    public const PLACEHOLDER_ON_ORDER_PREFIX = 'shopee-push-on-order:';

    public function __construct(
        private MarketplaceWebhookStatusPresenter $statusPresenter,
        private JubelioSellInvoiceGuard $sellInvoiceGuard,
        private ShopeeSyncWarehouseResolver $shopeeSyncWarehouseResolver,
        private ShopeeSyncInvoicePrefix $shopeeInvoicePrefix,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('services.shopee_push.precreate_jubelio_queue', false);
    }

    /**
     * @param  array<string, mixed>  $pushData
     * @param  array<string, mixed>  $fullPayload
     */
    public function upsertFromPush(
        ?int $shopId,
        string $orderSn,
        ?string $shopeeStatus,
        int $pushCode,
        array $pushData = [],
        array $fullPayload = [],
    ): bool {
        if ($this->shopeeInvoicePrefix->isFlexiExportShop($shopId)) {
            return false;
        }

        if (! $this->enabled()) {
            return false;
        }

        if ($pushCode !== 3 || trim($orderSn) === '') {
            return false;
        }

        if (ShopeeTestPushDetector::isTestPush($orderSn, $pushData, $fullPayload)) {
            return false;
        }

        if (! $this->shouldTriggerForStatus($shopeeStatus)) {
            return false;
        }

        if ($this->sellInvoiceGuard->sellInvoiceInTransactions($this->invoiceForOrderSn($orderSn, $shopId))) {
            return false;
        }

        $invoice = $this->invoiceForOrderSn($orderSn, $shopId);
        $warehouseColumns = $this->resolveWarehouseColumns($shopId, $pushData);

        $existing = Jubelioorder::query()
            ->where('type', 'SELL')
            ->where('invoice', $invoice)
            ->first();

        if ($existing !== null) {
            return $this->touchExistingRow($existing, $warehouseColumns);
        }

        Jubelioorder::query()->create([
            'jubelio_order_id' => $this->placeholderJubelioOrderId($orderSn),
            'source' => Jubelioorder::SOURCE_SHOPEE_PUSH,
            'invoice' => $invoice,
            'type' => 'SELL',
            'order_status' => JubelioOrderReadyToShip::STATUS,
            'run_count' => 0,
            'warehouse_id' => $warehouseColumns['warehouse_id'],
            'jubelio_store_id' => $warehouseColumns['jubelio_store_id'],
            'jubelio_location_id' => $warehouseColumns['jubelio_location_id'],
            'status' => 0,
        ]);

        return true;
    }

    /**
     * After successful omnichannel AWB print — ensure a jubelioorders row exists (no SELL post).
     *
     * @param  array<int, list<string>>  $ordersByShopId  shop_id => order_sn list
     */
    public function ensureQueueRowsFromAwbPrint(array $ordersByShopId, ?int $printerUserId = null): int
    {
        $count = 0;

        foreach ($ordersByShopId as $shopId => $orderSns) {
            $shopId = (int) $shopId;
            foreach (array_values(array_unique(array_filter(array_map(
                static fn ($sn) => trim((string) $sn),
                is_array($orderSns) ? $orderSns : [],
            )))) as $orderSn) {
                if ($orderSn === '') {
                    continue;
                }

                if ($this->ensureQueueRowFromOmnichannelAwb($shopId, $orderSn, $printerUserId)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    public function ensureQueueRowFromOmnichannelAwb(int $shopId, string $orderSn, ?int $printerUserId = null): bool
    {
        $orderSn = trim($orderSn);
        if ($orderSn === '' || $shopId <= 0) {
            return false;
        }

        if ($this->shopeeInvoicePrefix->isFlexiExportShop($shopId)) {
            return false;
        }

        $invoice = $this->invoiceForOrderSn($orderSn, $shopId);

        if ($this->sellInvoiceGuard->sellInvoiceInTransactions($invoice)) {
            return false;
        }

        $warehouseColumns = $this->resolveWarehouseColumnsForAwb($shopId, $orderSn);

        $existing = Jubelioorder::query()
            ->where('type', 'SELL')
            ->where('invoice', $invoice)
            ->first();

        if ($existing !== null) {
            return $this->touchExistingRowForAwb($existing, $warehouseColumns, $printerUserId);
        }

        $create = [
            'jubelio_order_id' => $this->placeholderJubelioOrderId($orderSn),
            'source' => Jubelioorder::SOURCE_SHOPEE_PUSH,
            'invoice' => $invoice,
            'type' => 'SELL',
            'order_status' => JubelioOrderReadyToShip::STATUS,
            'run_count' => 0,
            'warehouse_id' => $warehouseColumns['warehouse_id'],
            'jubelio_store_id' => $warehouseColumns['jubelio_store_id'],
            'jubelio_location_id' => $warehouseColumns['jubelio_location_id'],
            'status' => 0,
        ];

        if ($printerUserId !== null && (int) $printerUserId > 0) {
            $create['user_id'] = (int) $printerUserId;
        }

        Jubelioorder::query()->create($create);

        return true;
    }

    public function invoiceForOrderSn(string $orderSn, ?int $shopId = null): string
    {
        return $this->shopeeInvoicePrefix->invoiceForOrderSn($shopId, $orderSn);
    }

    public static function onOrderPrecreateEnabled(): bool
    {
        return (bool) config('marketplace.shopee_on_order.enabled', false);
    }

    public function placeholderJubelioOrderId(string $orderSn): string
    {
        $prefix = self::onOrderPrecreateEnabled()
            ? self::PLACEHOLDER_ON_ORDER_PREFIX
            : self::PLACEHOLDER_PREFIX;

        return $prefix.trim($orderSn);
    }

    /**
     * @return list<string> Jubelio id candidates when resolving SP-{ordersn} queue rows.
     */
    public static function placeholderLookupIdsForOrderSn(string $orderSn): array
    {
        $orderSn = trim($orderSn);
        if ($orderSn === '') {
            return [];
        }

        $ids = [];
        if (self::onOrderPrecreateEnabled()) {
            $ids[] = self::PLACEHOLDER_ON_ORDER_PREFIX.$orderSn;
        }
        $ids[] = self::PLACEHOLDER_PREFIX.$orderSn;

        return array_values(array_unique($ids));
    }

    public static function isPlaceholderJubelioOrderId(mixed $jubelioOrderId): bool
    {
        return self::isLegacyPlaceholderJubelioOrderId($jubelioOrderId)
            || self::isOnOrderPlaceholderJubelioOrderId($jubelioOrderId);
    }

    public static function isLegacyPlaceholderJubelioOrderId(mixed $jubelioOrderId): bool
    {
        if (! is_string($jubelioOrderId)) {
            return false;
        }

        return str_starts_with($jubelioOrderId, self::PLACEHOLDER_PREFIX)
            && ! str_starts_with($jubelioOrderId, self::PLACEHOLDER_ON_ORDER_PREFIX);
    }

    public static function isOnOrderPlaceholderJubelioOrderId(mixed $jubelioOrderId): bool
    {
        return is_string($jubelioOrderId)
            && str_starts_with($jubelioOrderId, self::PLACEHOLDER_ON_ORDER_PREFIX);
    }

    public function shouldTriggerForStatus(?string $shopeeStatus): bool
    {
        if ($shopeeStatus === null || trim($shopeeStatus) === '') {
            return false;
        }

        $configured = config('services.shopee_push.precreate_trigger_statuses', []);
        if (is_array($configured) && $configured !== []) {
            $upper = strtoupper(trim($shopeeStatus));

            return in_array($upper, array_map('strtoupper', $configured), true);
        }

        $bucket = $this->statusPresenter->normalizeBucket(
            MarketplaceWebhookStatusPresenter::CHANNEL_SHOPEE,
            null,
            $shopeeStatus,
        );

        return $bucket === 'awaiting_shipment';
    }

    /**
     * @param  array{warehouse_id: int, jubelio_store_id: int, jubelio_location_id: int}  $warehouseColumns
     */
    private function touchExistingRow(
        Jubelioorder $existing,
        array $warehouseColumns,
    ): bool {
        $dirty = false;

        if ($existing->type !== 'SELL') {
            return false;
        }

        if ((int) $existing->source === Jubelioorder::SOURCE_SHOPEE_PUSH
            && self::isPlaceholderJubelioOrderId($existing->jubelio_order_id)
            && ! JubelioOrderReadyToShip::isReadyToShipStatus($existing->order_status)) {
            $existing->order_status = JubelioOrderReadyToShip::STATUS;
            $dirty = true;
        }

        if ($warehouseColumns['warehouse_id'] > 0 && (int) $existing->warehouse_id === 0) {
            $existing->warehouse_id = $warehouseColumns['warehouse_id'];
            $existing->jubelio_store_id = $warehouseColumns['jubelio_store_id'];
            $existing->jubelio_location_id = $warehouseColumns['jubelio_location_id'];
            $dirty = true;
        }

        if ($dirty) {
            $existing->save();
        }

        return true;
    }

    /**
     * @param  array{warehouse_id: int, jubelio_store_id: int, jubelio_location_id: int}  $warehouseColumns
     */
    private function touchExistingRowForAwb(
        Jubelioorder $existing,
        array $warehouseColumns,
        ?int $printerUserId = null,
    ): bool {
        if ($existing->type !== 'SELL') {
            return false;
        }

        $dirty = false;

        if (! JubelioOrderReadyToShip::isReadyToShipStatus($existing->order_status)) {
            $existing->order_status = JubelioOrderReadyToShip::STATUS;
            $dirty = true;
        }

        if ($warehouseColumns['warehouse_id'] > 0 && (int) $existing->warehouse_id === 0) {
            $existing->warehouse_id = $warehouseColumns['warehouse_id'];
            $existing->jubelio_store_id = $warehouseColumns['jubelio_store_id'];
            $existing->jubelio_location_id = $warehouseColumns['jubelio_location_id'];
            $dirty = true;
        }

        if ($printerUserId !== null
            && (int) $printerUserId > 0
            && (int) ($existing->user_id ?? 0) === 0) {
            $existing->user_id = (int) $printerUserId;
            $dirty = true;
        }

        if ($dirty) {
            $existing->save();
        }

        return true;
    }

    /**
     * @return array{warehouse_id: int, jubelio_store_id: int, jubelio_location_id: int}
     */
    private function resolveWarehouseColumnsForAwb(int $shopId, string $orderSn): array
    {
        $pushData = [];

        if (Schema::hasTable('marketplace_channel_orders')) {
            $channelOrder = MarketplaceChannelOrder::query()
                ->where('channel', MarketplaceWebhookRecorder::CHANNEL_SHOPEE)
                ->where('order_ref', $orderSn)
                ->first();

            if ($channelOrder !== null) {
                $persistedWarehouseId = (int) ($channelOrder->aria_warehouse_id ?? 0);
                if ($persistedWarehouseId > 0) {
                    return $this->jubelioColumnsForAriaWarehouse($persistedWarehouseId);
                }

                $last = $channelOrder->relationLoaded('lastWebhookEvent')
                    ? $channelOrder->lastWebhookEvent
                    : $channelOrder->lastWebhookEvent()->first();

                if ($last !== null && is_array($last->payload)) {
                    $data = is_array($last->payload['data'] ?? null) ? $last->payload['data'] : [];
                    if ($data !== []) {
                        $pushData = $data;
                    }
                }
            }
        }

        return $this->resolveWarehouseColumns($shopId, $pushData);
    }

    /**
     * @return array{warehouse_id: int, jubelio_store_id: int, jubelio_location_id: int}
     */
    private function jubelioColumnsForAriaWarehouse(int $warehouseId): array
    {
        $defaults = [
            'warehouse_id' => $warehouseId,
            'jubelio_store_id' => 0,
            'jubelio_location_id' => 0,
        ];

        if (! Schema::hasTable('jubeliosyncs')) {
            return $defaults;
        }

        $sync = Jubeliosync::query()
            ->where('warehouse_id', $warehouseId)
            ->whereMappedJubelioLocation()
            ->orderBy('id')
            ->first();

        if ($sync === null) {
            $sync = Jubeliosync::query()
                ->where('warehouse_id', $warehouseId)
                ->orderBy('id')
                ->first();
        }

        if ($sync === null) {
            return $defaults;
        }

        return [
            'warehouse_id' => $warehouseId,
            'jubelio_store_id' => (int) ($sync->jubelio_store_id ?? 0),
            'jubelio_location_id' => (int) ($sync->jubelio_location_id ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $pushData
     * @return array{warehouse_id: int, jubelio_store_id: int, jubelio_location_id: int}
     */
    private function resolveWarehouseColumns(?int $shopId, array $pushData = []): array
    {
        $defaults = [
            'warehouse_id' => 0,
            'jubelio_store_id' => 0,
            'jubelio_location_id' => 0,
        ];

        if ($shopId === null || $shopId <= 0) {
            return $defaults;
        }

        $orderRow = isset($pushData['order_detail']) && is_array($pushData['order_detail'])
            ? $pushData['order_detail']
            : null;
        $warehouseId = $this->shopeeSyncWarehouseResolver->ariaWarehouseIdForIngest($shopId, $pushData, $orderRow);
        if ($warehouseId <= 0) {
            return $defaults;
        }

        if (! Schema::hasTable('jubeliosyncs')) {
            return ['warehouse_id' => $warehouseId] + array_slice($defaults, 1);
        }

        $sync = Jubeliosync::query()
            ->where('warehouse_id', $warehouseId)
            ->whereMappedJubelioLocation()
            ->orderBy('id')
            ->first();

        if ($sync === null) {
            $sync = Jubeliosync::query()
                ->where('warehouse_id', $warehouseId)
                ->orderBy('id')
                ->first();
        }

        if ($sync === null) {
            return ['warehouse_id' => $warehouseId] + array_slice($defaults, 1);
        }

        return [
            'warehouse_id' => $warehouseId,
            'jubelio_store_id' => (int) ($sync->jubelio_store_id ?? 0),
            'jubelio_location_id' => (int) ($sync->jubelio_location_id ?? 0),
        ];
    }
}