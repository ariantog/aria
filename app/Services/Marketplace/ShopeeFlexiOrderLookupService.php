<?php

namespace App\Services\Marketplace;

use App\Models\Addrbook;
use App\Models\ShopeeFlexiShop;
use App\Models\Shopeesync;
use App\Models\Transaction;
use App\Services\Jubelio\JubelioSellInvoiceGuard;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Support\Facades\Schema;

/**
 * Maintainer tool: fetch one Shopee order via Admin Open API and preview FLEXI / warehouse mapping.
 */
class ShopeeFlexiOrderLookupService
{
    /** Shopee MY/export statuses that allow manual SELL prefill (maintainer: completed + escrow). */
    private const SELL_ELIGIBLE_STATUSES = ['COMPLETED'];

    public function __construct(
        private ShopeeAdminOpenApiService $shopeeApi,
        private ShopeeSyncInvoicePrefix $invoicePrefix,
        private ShopeeSyncWarehouseResolver $warehouseResolver,
        private ShopeeOrderPickupLocation $pickupLocation,
        private ShopeeFlexiOrderAmountResolver $amountResolver,
        private ShopeeFlexiSellItemMatcher $sellItemMatcher,
        private JubelioSellInvoiceGuard $sellInvoiceGuard,
        private ShopeeFlexiStagingWarehouse $stagingWarehouse,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function lookup(int $shopId, string $orderRef, bool $loadDetail = true): array
    {
        $orderSn = $this->normalizeOrderSn($orderRef);

        $base = [
            'ok' => false,
            'error' => null,
            'error_detail' => null,
            'order_sn' => $orderSn,
            'order_ref_input' => trim($orderRef),
            'shop_id' => $shopId,
            'shop_authorized' => false,
            'detail_loaded' => false,
            'invoice_prefix' => $this->invoicePrefix->prefixForShopId($shopId),
            'invoice_preview' => $this->invoicePrefix->invoiceForOrderSn($shopId, $orderSn),
            'flexi_mapping' => $this->flexiSummary($shopId),
            'region_hint' => $this->regionHintForShop($shopId),
            'order_status' => null,
            'pickup' => null,
            'warehouse_id' => 0,
            'warehouse_name' => null,
            'sync_matches' => [],
            'line_summary' => null,
            'line_match' => null,
            'duplicate_transaction' => false,
            'invoice_exists' => false,
            'existing_transaction_id' => null,
            'can_manual_sell' => false,
            'manual_sell_block' => null,
            'can_move_to_staging' => false,
            'move_to_staging_block' => null,
            'staging' => $this->stagingWarehouse->statusForUi(),
            'api_snippet' => null,
            'order_currency' => null,
            'order_total_original' => null,
            'amount_resolution' => null,
            'escrow_snippet' => null,
        ];

        $invoicePreview = (string) $base['invoice_preview'];
        $invoiceExists = $this->sellInvoiceGuard->sellInvoiceInTransactions($invoicePreview);
        $base['duplicate_transaction'] = $invoiceExists;
        $base['invoice_exists'] = $invoiceExists;
        if ($invoiceExists) {
            $base['existing_transaction_id'] = Transaction::query()
                ->where('type', Transaction::TYPE_SELL)
                ->where('invoice', $invoicePreview)
                ->value('id');
        }

        if ($shopId <= 0) {
            $base['error'] = 'shop_id wajib diisi — lihat OAuth shop IDs di halaman FLEXI atau Seller Centre.';

            return $base;
        }

        if ($orderSn === '') {
            $base['error'] = 'Order ref / ordersn kosong.';

            return $base;
        }

        if (! $this->shopeeApi->isConfigured()) {
            $base['error'] = 'Shopee Admin API belum dikonfigurasi (SHOPEE_ADMIN_PARTNER_ID / KEY).';

            return $base;
        }

        $base['shop_authorized'] = $this->shopeeApi->hasShopAuthorizationFor($shopId);
        if (! $base['shop_authorized']) {
            $base['error'] = "Shop {$shopId} belum diotorisasi — authorize lewat Omnichannel → Shopee Admin OAuth.";

            return $base;
        }

        if (! $loadDetail) {
            $base['ok'] = true;
            $base['manual_sell_block'] = 'Muat detail dari Shopee API untuk status, baris item, gudang, dan Buat Transaksi Manual.';

            return $base;
        }

        $accessToken = $this->shopeeApi->getAccessToken($shopId);
        if ($accessToken === null || $accessToken === '') {
            return $this->failApi($base, $shopId,
                "Token akses shop {$shopId} tidak tersedia — authorize/refresh OAuth untuk shop ini (UI \"connected\" bisa dari shop primary lain).",
            );
        }

        $detail = $this->shopeeApi->getOrderDetail(
            $orderSn,
            $shopId,
            ShopeeFlexiOrderDetailFields::OPTIONAL,
        );

        if ($detail === null) {
            return $this->failApi($base, $shopId,
                "API get_order_detail gagal untuk shop {$shopId} — periksa ordersn dan laravel.log.",
            );
        }

        if (filled($detail['error'] ?? null) || filled($detail['message'] ?? null)) {
            $base['error'] = (string) ($detail['message'] ?? $detail['error'] ?? 'Shopee menolak permintaan.');
            $base['error_detail'] = $this->shopeeApi->getLastOAuthError();
            $base['api_snippet'] = $this->safeSnippet($detail);

            return $base;
        }

        $orderRow = is_array($detail['response']['order_list'][0] ?? null)
            ? $detail['response']['order_list'][0]
            : null;

        if ($orderRow === null) {
            $base['error'] = 'Order tidak ditemukan — periksa ordersn dan shop_id (order list kosong).';
            $base['api_snippet'] = $this->safeSnippet($detail);

            return $base;
        }

        $pushData = [
            'ordersn' => $orderSn,
            'order_sn' => $orderSn,
            'item_list' => $orderRow['item_list'] ?? [],
            'order_detail' => $orderRow,
        ];

        $pickup = $this->pickupLocation->extract($pushData, $orderRow);
        $warehouseId = $this->warehouseResolver->ariaWarehouseIdForIngest($shopId, $pushData, $orderRow);

        $amounts = $this->amountResolver->resolve($shopId, $orderSn, $orderRow);

        $lineSummary = [
            'lines' => $amounts['lines'],
            'line_count' => $amounts['line_count'],
            'total_amount' => $amounts['total_amount'],
        ];

        $lineMatch = $this->sellItemMatcher->matchLines($amounts['lines']);

        $base['ok'] = true;
        $base['detail_loaded'] = true;
        $base['order_status'] = (string) ($orderRow['order_status'] ?? '');
        $base['pickup'] = $pickup;
        $base['warehouse_id'] = $warehouseId;
        $base['warehouse_name'] = $this->warehouseLabel($warehouseId);
        $base['sync_matches'] = $this->syncRowsForPickup($pickup, $warehouseId);
        $base['line_summary'] = $lineSummary;
        $base['line_match'] = [
            'error' => $lineMatch['error'],
            'item_count' => count($lineMatch['items']),
        ];
        $base['line_match_items'] = $lineMatch['items'];
        $base['order_currency'] = $amounts['order_currency'];
        $base['order_total_original'] = $amounts['order_total_original'];
        $base['escrow_snippet'] = $amounts['escrow_snippet'];
        $base['amount_resolution'] = [
            'price_source' => $amounts['price_source'],
            'exchange_rate' => $amounts['exchange_rate'],
            'banner' => $amounts['banner'],
            'escrow_loaded' => $amounts['escrow_loaded'],
            'escrow_usable' => $amounts['escrow_usable'],
            'idr_escrow_total' => $amounts['idr_escrow_total'],
            'escrow_fetch' => $amounts['escrow_fetch'],
        ];
        $base['api_snippet'] = $this->safeSnippet($orderRow);

        if (filled($orderRow['region'] ?? null)) {
            $base['region_hint'] = strtoupper((string) $orderRow['region']);
        }

        $base['staging'] = $this->stagingWarehouse->statusForUi();
        $base['can_move_to_staging'] = $this->canMoveToStaging($base, $lineMatch);
        $base['move_to_staging_block'] = $base['can_move_to_staging'] ? null : $this->moveToStagingBlockReason($base, $lineMatch);

        $base['can_manual_sell'] = $this->canManualSell($base, $lineMatch, $amounts);
        $base['manual_sell_block'] = $base['can_manual_sell'] ? null : $this->manualSellBlockReason($base, $lineMatch, $amounts);

        return $base;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array{error: ?string, items: list<array<string, mixed>>}  $lineMatch
     * @param  array<string, mixed>  $amounts
     */
    private function canManualSell(array $base, array $lineMatch, array $amounts): bool
    {
        if ($base['duplicate_transaction'] ?? false) {
            return false;
        }

        if (($base['warehouse_id'] ?? 0) <= 0) {
            return false;
        }

        if ($lineMatch['error'] !== null || $lineMatch['items'] === []) {
            return false;
        }

        if (! ($base['detail_loaded'] ?? false) || ! ($base['ok'] ?? false)) {
            return false;
        }

        if (! $this->orderStatusAllowsManualSell((string) ($base['order_status'] ?? ''))) {
            return false;
        }

        if (! $this->amountResolver->amountsUsableForSellPrefill($amounts)) {
            return false;
        }

        $staging = $this->stagingWarehouse->resolveWarehouse();
        if ($staging !== null && ($base['warehouse_id'] ?? 0) === (int) $staging->id) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array{error: ?string, items: list<array<string, mixed>>}  $lineMatch
     * @param  array<string, mixed>  $amounts
     */
    private function manualSellBlockReason(array $base, array $lineMatch, array $amounts): string
    {
        if ($base['duplicate_transaction'] ?? false) {
            return 'Invoice sudah ada di Transaksi.';
        }

        if (($base['warehouse_id'] ?? 0) <= 0) {
            return 'Gudang belum ter-resolve.';
        }

        if ($lineMatch['error'] !== null) {
            return $lineMatch['error'];
        }

        if ($lineMatch['items'] === []) {
            return 'Tidak ada baris item yang bisa diposting.';
        }

        $status = (string) ($base['order_status'] ?? '');
        if (! $this->orderStatusAllowsManualSell($status)) {
            return 'Buat Transaksi Manual hanya saat order Shopee COMPLETED (status saat ini: '.($status !== '' ? $status : '—').').';
        }

        if (! ($amounts['escrow_loaded'] ?? false)) {
            return 'Escrow belum dimuat dari Shopee (get_escrow_detail) — tunggu escrow tersedia sebelum SELL.';
        }

        if (! $this->amountResolver->amountsUsableForSellPrefill($amounts)) {
            $source = (string) ($amounts['price_source'] ?? '');
            if ($source === ShopeeFlexiOrderAmountResolver::PRICE_SOURCE_CATALOG) {
                return 'Escrow IDR/kurs tidak ditemukan — SELL tidak tersedia (coba shop primary + ref MY-{ordersn}, atau tunggu exchange_rate).';
            }

            return 'Nominal escrow tidak cukup untuk prefill SELL — periksa get_escrow_detail.';
        }

        $staging = $this->stagingWarehouse->resolveWarehouse();
        if ($staging !== null && ($base['warehouse_id'] ?? 0) === (int) $staging->id) {
            return 'SELL dari gudang staging — pastikan stok sudah di pickup gudang (sender SELL = staging setelah move).';
        }

        return 'Prasyarat Buat Transaksi Manual belum terpenuhi.';
    }

    /**
     * @param  array{error: ?string, items: list<array<string, mixed>>}  $lineMatch
     */
    private function canMoveToStaging(array $base, array $lineMatch): bool
    {
        if (! ($base['detail_loaded'] ?? false) || ! ($base['ok'] ?? false)) {
            return false;
        }

        if ($this->stagingWarehouse->resolveWarehouse() === null) {
            return false;
        }

        if (($base['warehouse_id'] ?? 0) <= 0) {
            return false;
        }

        $stagingId = $this->stagingWarehouse->configuredId();
        if ($stagingId > 0 && (int) ($base['warehouse_id'] ?? 0) === $stagingId) {
            return false;
        }

        if ($lineMatch['error'] !== null || $lineMatch['items'] === []) {
            return false;
        }

        return true;
    }

    /**
     * @param  array{error: ?string, items: list<array<string, mixed>>}  $lineMatch
     */
    private function moveToStagingBlockReason(array $base, array $lineMatch): string
    {
        $stagingUi = $this->stagingWarehouse->statusForUi();
        if (! ($stagingUi['valid'] ?? false)) {
            return (string) ($stagingUi['warning'] ?? 'Gudang staging FLEXI belum valid.');
        }

        if (($base['warehouse_id'] ?? 0) <= 0) {
            return 'Gudang pickup belum ter-resolve.';
        }

        $stagingId = $this->stagingWarehouse->configuredId();
        if ($stagingId > 0 && (int) ($base['warehouse_id'] ?? 0) === $stagingId) {
            return 'Order sudah di-resolve ke gudang staging — tidak perlu move.';
        }

        if ($lineMatch['error'] !== null) {
            return $lineMatch['error'];
        }

        if ($lineMatch['items'] === []) {
            return 'Tidak ada baris item untuk move.';
        }

        return 'Prasyarat Pindah ke gudang FLEXI belum terpenuhi.';
    }

    public function orderStatusAllowsManualSell(string $status): bool
    {
        $normalized = strtoupper(trim($status));
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, self::SELL_ELIGIBLE_STATUSES, true);
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function failApi(array $base, int $shopId, string $fallback): array
    {
        $raw = $this->shopeeApi->getLastOAuthError();
        $base['error_detail'] = $raw;
        $formatted = $this->shopeeApi->formatOAuthErrorForUser($raw);
        $base['error'] = ($formatted !== null && $formatted !== '') ? $formatted : $fallback;

        return $base;
    }

    public function normalizeOrderSn(string $ref): string
    {
        $ref = trim($ref);
        if ($ref === '') {
            return '';
        }

        foreach ($this->invoicePrefix->allKnownPrefixes() as $prefix) {
            $quoted = preg_quote($prefix, '/');
            if (preg_match('/^'.$quoted.'-(.+)$/i', $ref, $matches) === 1) {
                return trim($matches[1]);
            }
        }

        if (preg_match('/^[A-Z]{2,4}-(.+)$/i', $ref, $matches) === 1) {
            return trim($matches[1]);
        }

        return $ref;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function flexiSummary(int $shopId): ?array
    {
        if (! Schema::hasTable('shopee_flexi_shops')) {
            return null;
        }

        $row = ShopeeFlexiShop::query()->where('shop_id', $shopId)->first();
        if ($row === null) {
            return null;
        }

        return [
            'invoice_prefix' => strtoupper((string) $row->invoice_prefix),
            'direct_order_ingest' => (bool) $row->direct_order_ingest,
            'label' => (string) ($row->label ?? ''),
        ];
    }

    private function regionHintForShop(int $shopId): ?string
    {
        $prefix = $this->invoicePrefix->prefixForShopId($shopId);
        $labels = config('shopee_flexi.flexi_prefixes', []);

        return $labels[$prefix] ?? ($prefix === 'SP' ? 'Indonesia (domestic)' : null);
    }

    private function warehouseLabel(int $warehouseId): ?string
    {
        if ($warehouseId <= 0) {
            return null;
        }

        $name = Addrbook::query()->where('id', $warehouseId)->value('name');

        return filled($name) ? (string) $name : 'Gudang #'.$warehouseId;
    }

    /**
     * @param  array{shopee_location_id: string, shopee_warehouse_id: int}|null  $pickup
     * @return list<array<string, mixed>>
     */
    private function syncRowsForPickup(?array $pickup, int $resolvedWarehouseId): array
    {
        if (! Schema::hasTable('shopee_syncs')) {
            return [];
        }

        $rows = Shopeesync::query()
            ->with('warehouse:id,name')
            ->where('warehouse_id', '>', 0)
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($rows as $sync) {
            $wid = (int) $sync->warehouse_id;
            $matchesResolved = $resolvedWarehouseId > 0 && $wid === $resolvedWarehouseId;
            $matchesPickup = false;
            if ($pickup !== null) {
                $loc = (string) ($pickup['shopee_location_id'] ?? '');
                $swId = (int) ($pickup['shopee_warehouse_id'] ?? 0);
                if ($loc !== '' && strtoupper((string) $sync->shopee_location_id) === strtoupper($loc)) {
                    $matchesPickup = true;
                }
                if ($swId > 0 && (int) $sync->shopee_warehouse_id === $swId) {
                    $matchesPickup = true;
                }
            }

            if (! $matchesResolved && ! $matchesPickup) {
                continue;
            }

            $out[] = [
                'id' => (int) $sync->id,
                'warehouse_id' => $wid,
                'warehouse_name' => $sync->warehouse?->name ?? '—',
                'shopee_location_id' => (string) $sync->shopee_location_id,
                'shopee_warehouse_id' => (int) $sync->shopee_warehouse_id,
                'shopee_warehouse_name' => (string) ($sync->shopee_warehouse_name ?? ''),
                'shop_id' => (int) ($sync->shop_id ?? 0),
                'matched_resolved' => $matchesResolved,
                'matched_pickup' => $matchesPickup,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function safeSnippet(array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false || strlen($json) > 12_000) {
            return ['_note' => 'Payload terlalu besar — ringkas di log.'];
        }

        return $payload;
    }
}