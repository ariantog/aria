<?php

namespace App\Services\Marketplace;

use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\MarketplaceChannelOrder;
use App\Models\TiktokShopSync;
use App\Services\Jubelio\JubelioOrderReadyToShip;
use App\Services\Jubelio\JubelioResiScanService;
use App\Services\Jubelio\JubelioSellInvoiceGuard;
use Illuminate\Support\Facades\Schema;

/**
 * After successful TikTok / Tokopedia AWB print, upsert jubelioorders READY TO SHIP rows (no SELL post).
 */
class TikTokJubelioQueuePrecreator
{
    public function __construct(
        private JubelioSellInvoiceGuard $sellInvoiceGuard,
        private JubelioResiScanService $resiScanService,
    ) {}

    /**
     * @param  array<string, list<string>>  $ordersByShopCipher  shop_cipher => order id list
     */
    public function ensureQueueRowsFromAwbPrint(array $ordersByShopCipher, ?int $printerUserId = null): int
    {
        $count = 0;

        foreach ($ordersByShopCipher as $shopCipher => $orderIds) {
            $shopCipher = trim((string) $shopCipher);
            foreach (array_values(array_unique(array_filter(array_map(
                static fn ($id) => trim((string) $id),
                is_array($orderIds) ? $orderIds : [],
            )))) as $orderId) {
                if ($orderId === '') {
                    continue;
                }

                if ($this->ensureQueueRowFromOmnichannelAwb($shopCipher, $orderId, $printerUserId)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    public function ensureQueueRowFromOmnichannelAwb(
        string $shopCipher,
        string $orderId,
        ?int $printerUserId = null,
    ): bool {
        $orderId = trim($orderId);
        if ($orderId === '') {
            return false;
        }

        $marketplaceKey = $this->resolveMarketplaceKey($orderId);
        $jubelioStoreId = $this->resolveJubelioStoreId($shopCipher);

        $invoice = $this->resiScanService->formatInvoice(
            $marketplaceKey,
            $orderId,
            $jubelioStoreId,
        );

        if ($this->sellInvoiceGuard->sellInvoiceInTransactions($invoice)) {
            return false;
        }

        $existing = Jubelioorder::query()
            ->where('type', 'SELL')
            ->where('invoice', $invoice)
            ->first();

        if ($existing === null) {
            foreach ($this->resiScanService->invoiceMatchCandidates($marketplaceKey, $invoice, $jubelioStoreId) as $candidate) {
                $existing = Jubelioorder::query()
                    ->where('type', 'SELL')
                    ->where('invoice', $candidate)
                    ->first();
                if ($existing !== null) {
                    break;
                }
            }
        }

        $warehouseColumns = $this->resolveWarehouseColumns($shopCipher, $jubelioStoreId);

        if ($existing !== null) {
            return $this->touchExistingRowForAwb($existing, $warehouseColumns, $printerUserId);
        }

        $create = [
            'jubelio_order_id' => 'tiktok-awb:'.$orderId,
            'source' => Jubelioorder::SOURCE_SCAN_RESI,
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

    private function resolveMarketplaceKey(string $orderId): string
    {
        $row = MarketplaceChannelOrder::query()
            ->where('channel', MarketplaceWebhookRecorder::CHANNEL_TIKTOK)
            ->where('order_ref', $orderId)
            ->first();

        if ($row !== null && strtoupper((string) ($row->commerce_platform ?? '')) === 'TOKOPEDIA') {
            return 'tokopedia';
        }

        return 'tiktok';
    }

    private function resolveJubelioStoreId(string $shopCipher): ?int
    {
        $shopCipher = trim($shopCipher);
        if ($shopCipher === '' || ! Schema::hasTable('tiktok_shop_syncs')) {
            return null;
        }

        $warehouseId = (int) (TiktokShopSync::query()
            ->where('shop_cipher', $shopCipher)
            ->value('warehouse_id') ?? 0);

        if ($warehouseId <= 0 || ! Schema::hasTable('jubeliosyncs')) {
            return null;
        }

        $storeId = Jubeliosync::query()
            ->where('warehouse_id', $warehouseId)
            ->value('jubelio_store_id');

        if ($storeId === null || (int) $storeId <= 0) {
            return null;
        }

        return (int) $storeId;
    }

    /**
     * @return array{warehouse_id: int, jubelio_store_id: int, jubelio_location_id: int}
     */
    private function resolveWarehouseColumns(string $shopCipher, ?int $jubelioStoreId): array
    {
        $columns = [
            'warehouse_id' => 0,
            'jubelio_store_id' => (int) ($jubelioStoreId ?? 0),
            'jubelio_location_id' => 0,
        ];

        if ($jubelioStoreId !== null && $jubelioStoreId > 0 && Schema::hasTable('jubeliosyncs')) {
            $sync = Jubeliosync::query()
                ->where('jubelio_store_id', $jubelioStoreId)
                ->orderByDesc('warehouse_id')
                ->first();

            if ($sync !== null) {
                $columns['warehouse_id'] = (int) $sync->warehouse_id;
                $columns['jubelio_store_id'] = (int) $sync->jubelio_store_id;
                $columns['jubelio_location_id'] = (int) $sync->jubelio_location_id;
            }
        }

        if ($columns['warehouse_id'] <= 0 && Schema::hasTable('tiktok_shop_syncs')) {
            $shopCipher = trim($shopCipher);
            if ($shopCipher !== '') {
                $legacy = TiktokShopSync::query()->where('shop_cipher', $shopCipher)->first();
                if ($legacy !== null) {
                    $columns['warehouse_id'] = (int) ($legacy->warehouse_id ?? 0);
                }
            }
        }

        return $columns;
    }

    /**
     * @param  array{warehouse_id: int, jubelio_store_id: int, jubelio_location_id: int}  $warehouseColumns
     */
    private function touchExistingRowForAwb(Jubelioorder $existing, array $warehouseColumns, ?int $printerUserId): bool
    {
        $dirty = false;

        if ($warehouseColumns['warehouse_id'] > 0 && (int) $existing->warehouse_id !== $warehouseColumns['warehouse_id']) {
            $existing->warehouse_id = $warehouseColumns['warehouse_id'];
            $dirty = true;
        }

        if ($warehouseColumns['jubelio_store_id'] > 0 && (int) $existing->jubelio_store_id !== $warehouseColumns['jubelio_store_id']) {
            $existing->jubelio_store_id = $warehouseColumns['jubelio_store_id'];
            $dirty = true;
        }

        if ((int) $existing->user_id <= 0 && $printerUserId !== null && $printerUserId > 0) {
            $existing->user_id = $printerUserId;
            $dirty = true;
        }

        if ($existing->order_status !== JubelioOrderReadyToShip::STATUS) {
            $existing->order_status = JubelioOrderReadyToShip::STATUS;
            $dirty = true;
        }

        if ($dirty) {
            $existing->save();

            return true;
        }

        return false;
    }
}