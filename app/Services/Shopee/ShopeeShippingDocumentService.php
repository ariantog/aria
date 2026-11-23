<?php

namespace App\Services\Shopee;

use App\Models\Shopeesync;
use App\Services\Marketplace\ShopeeFlexiOrderDetailFields;
use App\Support\ShopeeAdminOAuthStore;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;

/**
 * Bulk AWB / shipping label download via Shopee Open API logistics module.
 */
class ShopeeShippingDocumentService
{
    public const MAX_ORDERS_PER_BATCH = 50;

    public const POLL_MAX_SECONDS = 30;

    public function __construct(
        private ShopeeAdminOpenApiService $shopeeApi,
    ) {}

    public function shippingDocumentType(): string
    {
        $type = trim((string) config('services.shopee_admin.shipping_document_type', 'NORMAL_AIR_WAYBILL'));

        return $type !== '' ? $type : 'NORMAL_AIR_WAYBILL';
    }

    public function resolveShopId(?string $shopRef): ?int
    {
        $shopRef = trim((string) $shopRef);
        if ($shopRef === '') {
            return null;
        }

        $candidate = (int) $shopRef;
        if ($candidate > 0 && $this->shopeeApi->hasShopAuthorizationFor($candidate)) {
            return $candidate;
        }

        if (Schema::hasTable('shopee_syncs') && $candidate > 0) {
            $exists = Shopeesync::query()->where('shop_id', $candidate)->exists();
            if ($exists && $this->shopeeApi->hasShopAuthorizationFor($candidate)) {
                return $candidate;
            }
        }

        foreach (ShopeeAdminOAuthStore::authorizedShopIds($this->oauthPayload()) as $authorizedId) {
            if ((string) $authorizedId === $shopRef) {
                return $authorizedId;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $orderSns
     * @return list<string>  One PDF binary string per API batch (≤50 orders each)
     */
    public function downloadPdfBatchesForShop(int $shopId, array $orderSns): array
    {
        if ($shopId <= 0) {
            throw new \InvalidArgumentException('shop_id tidak valid.');
        }

        if (! $this->shopeeApi->hasShopAuthorizationFor($shopId)) {
            throw new \RuntimeException("Shop {$shopId} belum diotorisasi (Shopee Admin OAuth).");
        }

        $orderSns = array_values(array_unique(array_filter(array_map(
            fn ($sn) => trim((string) $sn),
            $orderSns,
        ), fn ($sn) => $sn !== '')));

        if ($orderSns === []) {
            throw new \InvalidArgumentException('Tidak ada order Shopee yang dipilih.');
        }

        $pdfs = [];
        foreach (array_chunk($orderSns, self::MAX_ORDERS_PER_BATCH) as $chunk) {
            $orderList = [];
            foreach ($chunk as $orderSn) {
                foreach ($this->resolveOrderPackageEntries($shopId, $orderSn) as $entry) {
                    $orderList[] = $entry;
                }
            }

            if ($orderList === []) {
                continue;
            }

            foreach (array_chunk($orderList, self::MAX_ORDERS_PER_BATCH) as $apiChunk) {
                $this->createShippingDocument($shopId, $apiChunk);
                $this->pollUntilReady($shopId, $apiChunk);
                $pdfs[] = $this->downloadShippingDocument($shopId, $apiChunk);
            }
        }

        if ($pdfs === []) {
            throw new \RuntimeException('Gagal mengunduh dokumen pengiriman — tidak ada PDF.');
        }

        return $pdfs;
    }

    /**
     * @param  list<array{order_sn: string, package_number?: string, tracking_number?: string}>  $orderList
     */
    private function createShippingDocument(int $shopId, array $orderList): void
    {
        $docType = $this->shippingDocumentType();
        $payload = [
            'order_list' => array_map(function (array $row) use ($docType) {
                $item = ['order_sn' => $row['order_sn'], 'shipping_document_type' => $docType];
                if (filled($row['package_number'] ?? null)) {
                    $item['package_number'] = (string) $row['package_number'];
                }
                if (filled($row['tracking_number'] ?? null)) {
                    $item['tracking_number'] = (string) $row['tracking_number'];
                }

                return $item;
            }, $orderList),
        ];

        $response = $this->shopeeApi->shopApiPost('/api/v2/logistics/create_shipping_document', $payload, $shopId);
        $decoded = $this->shopeeApi->decodeShopResponse($response, 'create_shipping_document');
        if ($decoded === null) {
            throw new \RuntimeException('Shopee create_shipping_document gagal.');
        }

        $this->assertNoPerOrderFailures($decoded, 'create_shipping_document');
    }

    /**
     * @param  list<array{order_sn: string, package_number?: string, tracking_number?: string}>  $orderList
     */
    private function pollUntilReady(int $shopId, array $orderList): void
    {
        $deadline = microtime(true) + self::POLL_MAX_SECONDS;
        $docType = $this->shippingDocumentType();

        do {
            $payload = [
                'order_list' => array_map(function (array $row) use ($docType) {
                    $item = ['order_sn' => $row['order_sn'], 'shipping_document_type' => $docType];
                    if (filled($row['package_number'] ?? null)) {
                        $item['package_number'] = (string) $row['package_number'];
                    }

                    return $item;
                }, $orderList),
            ];

            $response = $this->shopeeApi->shopApiPost('/api/v2/logistics/get_shipping_document_result', $payload, $shopId);
            $decoded = $this->shopeeApi->decodeShopResponse($response, 'get_shipping_document_result');
            if ($decoded === null) {
                throw new \RuntimeException('Shopee get_shipping_document_result gagal.');
            }

            $statuses = $this->resultStatuses($decoded, $orderList);
            if ($this->allReady($statuses)) {
                return;
            }

            if ($this->anyFailed($statuses)) {
                $failed = implode(', ', array_keys(array_filter($statuses, fn ($s) => strtoupper($s) === 'FAILED')));

                throw new \RuntimeException('Shopee gagal membuat AWB untuk: '.$failed);
            }

            Sleep::sleep(1);
        } while (microtime(true) < $deadline);

        throw new \RuntimeException('Timeout menunggu AWB Shopee ('.self::POLL_MAX_SECONDS.' detik).');
    }

    /**
     * @param  list<array{order_sn: string, package_number?: string, tracking_number?: string}>  $orderList
     */
    private function downloadShippingDocument(int $shopId, array $orderList): string
    {
        $payload = [
            'shipping_document_type' => $this->shippingDocumentType(),
            'order_list' => array_map(function (array $row) {
                $item = ['order_sn' => $row['order_sn']];
                if (filled($row['package_number'] ?? null)) {
                    $item['package_number'] = (string) $row['package_number'];
                }

                return $item;
            }, $orderList),
        ];

        $response = $this->shopeeApi->shopApiPost('/api/v2/logistics/download_shipping_document', $payload, $shopId);
        if (! $response->successful()) {
            throw new \RuntimeException('Shopee download_shipping_document HTTP '.$response->status());
        }

        $body = $response->body();
        if ($body === '') {
            throw new \RuntimeException('Shopee download_shipping_document mengembalikan kosong.');
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        if (str_contains($contentType, 'json')) {
            $json = $response->json();
            if (is_array($json) && trim((string) ($json['error'] ?? '')) !== '') {
                throw new \RuntimeException('Shopee download_shipping_document: '.($json['message'] ?? $json['error']));
            }
        }

        return $body;
    }

    /**
     * @return list<array{order_sn: string, package_number?: string, tracking_number?: string}>
     */
    private function resolveOrderPackageEntries(int $shopId, string $orderSn): array
    {
        $detail = $this->shopeeApi->getOrderDetail(
            $orderSn,
            $shopId,
            ShopeeFlexiOrderDetailFields::OPTIONAL,
        );

        $orderRow = $this->firstOrderRow($detail);
        if ($orderRow === null) {
            return [['order_sn' => $orderSn]];
        }

        $headerTracking = trim((string) ($orderRow['tracking_number'] ?? ''));
        $packages = $orderRow['package_list'] ?? [];
        if (! is_array($packages) || $packages === []) {
            $entry = ['order_sn' => $orderSn];
            if ($headerTracking !== '') {
                $entry['tracking_number'] = $headerTracking;
            }

            return [$entry];
        }

        $entries = [];
        foreach ($packages as $package) {
            if (! is_array($package)) {
                continue;
            }
            $packageNumber = trim((string) ($package['package_number'] ?? ''));
            $entry = ['order_sn' => $orderSn];
            if ($packageNumber !== '') {
                $entry['package_number'] = $packageNumber;
            }
            $tracking = trim((string) ($package['tracking_number'] ?? $headerTracking));
            if ($tracking !== '') {
                $entry['tracking_number'] = $tracking;
            }
            $entries[] = $entry;
        }

        return $entries !== [] ? $entries : [['order_sn' => $orderSn]];
    }

    /**
     * @param  array<string, mixed>|null  $detail
     * @return array<string, mixed>|null
     */
    private function firstOrderRow(?array $detail): ?array
    {
        if ($detail === null) {
            return null;
        }

        $list = $detail['response']['order_list'] ?? $detail['order_list'] ?? null;
        if (! is_array($list) || $list === []) {
            return null;
        }

        $first = $list[0];

        return is_array($first) ? $first : null;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  list<array{order_sn: string, package_number?: string}>  $orderList
     * @return array<string, string> key order_sn|package → status
     */
    private function resultStatuses(array $decoded, array $orderList): array
    {
        $expected = [];
        foreach ($orderList as $row) {
            $key = $this->resultKey($row['order_sn'], $row['package_number'] ?? null);
            $expected[$key] = 'PROCESSING';
        }

        $results = $decoded['response']['result_list'] ?? [];
        if (! is_array($results)) {
            return $expected;
        }

        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sn = trim((string) ($row['order_sn'] ?? ''));
            if ($sn === '') {
                continue;
            }
            $pkg = trim((string) ($row['package_number'] ?? ''));
            $key = $this->resultKey($sn, $pkg !== '' ? $pkg : null);
            $expected[$key] = strtoupper(trim((string) ($row['status'] ?? 'PROCESSING')));
        }

        return $expected;
    }

    private function resultKey(string $orderSn, ?string $packageNumber): string
    {
        $pkg = trim((string) $packageNumber);

        return $pkg !== '' ? "{$orderSn}|{$pkg}" : $orderSn;
    }

    /**
     * @param  array<string, string>  $statuses
     */
    private function allReady(array $statuses): bool
    {
        foreach ($statuses as $status) {
            if (strtoupper($status) !== 'READY') {
                return false;
            }
        }

        return $statuses !== [];
    }

    /**
     * @param  array<string, string>  $statuses
     */
    private function anyFailed(array $statuses): bool
    {
        foreach ($statuses as $status) {
            if (strtoupper($status) === 'FAILED') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function assertNoPerOrderFailures(array $decoded, string $context): void
    {
        $results = $decoded['response']['result_list'] ?? [];
        if (! is_array($results)) {
            return;
        }

        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fail = trim((string) ($row['fail_error'] ?? ''));
            if ($fail === '') {
                continue;
            }
            $sn = $row['order_sn'] ?? '?';
            $msg = trim((string) ($row['fail_message'] ?? $fail));

            throw new \RuntimeException("{$context} — {$sn}: {$msg}");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function oauthPayload(): array
    {
        $value = \App\Models\Setting::getValue(ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG, []);

        return is_array($value) ? $value : [];
    }
}