<?php

namespace App\Services\Marketplace;

use App\Models\Addrbook;
use App\Models\ShopeeFlexiShop;
use App\Models\Shopeesync;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Support\Facades\Schema;

/**
 * Maintainer tool: fetch one Shopee order via Admin Open API and preview FLEXI / warehouse mapping.
 */
class ShopeeFlexiOrderLookupService
{
    /** Same optional fields as {@see ShopeeOrderPollService} / poll ingest (no invalid `region` param). */
    private const ORDER_DETAIL_OPTIONAL_FIELDS = 'order_status,item_list,package_list,total_amount';

    public function __construct(
        private ShopeeAdminOpenApiService $shopeeApi,
        private ShopeeSyncInvoicePrefix $invoicePrefix,
        private ShopeeSyncWarehouseResolver $warehouseResolver,
        private ShopeeOrderPickupLocation $pickupLocation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function lookup(int $shopId, string $orderRef): array
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
            'invoice_prefix' => $this->invoicePrefix->prefixForShopId($shopId),
            'invoice_preview' => $this->invoicePrefix->invoiceForOrderSn($shopId, $orderSn),
            'flexi_mapping' => $this->flexiSummary($shopId),
            'region_hint' => $this->regionHintForShop($shopId),
            'order_status' => null,
            'pickup' => null,
            'warehouse_id' => 0,
            'warehouse_name' => null,
            'sync_matches' => [],
            'api_snippet' => null,
        ];

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

        $accessToken = $this->shopeeApi->getAccessToken($shopId);
        if ($accessToken === null || $accessToken === '') {
            return $this->failApi($base, $shopId,
                "Token akses shop {$shopId} tidak tersedia — authorize/refresh OAuth untuk shop ini (UI \"connected\" bisa dari shop primary lain).",
            );
        }

        $detail = $this->shopeeApi->getOrderDetail(
            $orderSn,
            $shopId,
            self::ORDER_DETAIL_OPTIONAL_FIELDS,
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

        $base['ok'] = true;
        $base['order_status'] = (string) ($orderRow['order_status'] ?? '');
        $base['pickup'] = $pickup;
        $base['warehouse_id'] = $warehouseId;
        $base['warehouse_name'] = $this->warehouseLabel($warehouseId);
        $base['sync_matches'] = $this->syncRowsForPickup($pickup, $warehouseId);
        $base['api_snippet'] = $this->safeSnippet($orderRow);

        if (filled($orderRow['region'] ?? null)) {
            $base['region_hint'] = strtoupper((string) $orderRow['region']);
        }

        return $base;
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