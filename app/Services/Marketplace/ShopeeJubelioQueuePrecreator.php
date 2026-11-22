<?php

namespace App\Services\Marketplace;

use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
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

    public function __construct(
        private MarketplaceWebhookStatusPresenter $statusPresenter,
        private JubelioSellInvoiceGuard $sellInvoiceGuard,
        private MarketplaceWebhookShopWarehouseResolver $shopWarehouseResolver,
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

        if ($this->sellInvoiceGuard->sellInvoiceInTransactions($this->invoiceForOrderSn($orderSn))) {
            return false;
        }

        $invoice = $this->invoiceForOrderSn($orderSn);
        $warehouseColumns = $this->resolveWarehouseColumns($shopId);

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

    public function invoiceForOrderSn(string $orderSn): string
    {
        $prefix = strtoupper((string) config('jubelio_resi_scan.marketplaces.shopee.invoice_prefix', 'SP'));

        return $prefix.'-'.trim($orderSn);
    }

    public function placeholderJubelioOrderId(string $orderSn): string
    {
        return self::PLACEHOLDER_PREFIX.trim($orderSn);
    }

    public static function isPlaceholderJubelioOrderId(mixed $jubelioOrderId): bool
    {
        return is_string($jubelioOrderId)
            && str_starts_with($jubelioOrderId, self::PLACEHOLDER_PREFIX);
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
     * @return array{warehouse_id: int, jubelio_store_id: int, jubelio_location_id: int}
     */
    private function resolveWarehouseColumns(?int $shopId): array
    {
        $defaults = [
            'warehouse_id' => 0,
            'jubelio_store_id' => 0,
            'jubelio_location_id' => 0,
        ];

        if ($shopId === null || $shopId <= 0) {
            return $defaults;
        }

        $map = $this->shopWarehouseResolver->shopRefToWarehouseMap();
        $warehouseId = $map[(string) $shopId] ?? 0;
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