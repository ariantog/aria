<?php

namespace App\Services\Shopee;

use App\Models\Shopeesync;
use App\Services\Marketplace\ShopeeFlexiOrderDetailFields;
use App\Support\ShopeeAdminOAuthStore;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;

/**
 * Bulk AWB / shipping label download via Shopee Open API logistics module.
 */
class ShopeeShippingDocumentService
{
    public const MAX_ORDERS_PER_BATCH = 50;

    public const POLL_MAX_SECONDS = 30;

    private const BATCH_ALL_FAILED_ERROR = 'common.batch_api_all_failed';

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
                $this->ensureShippingDocumentsReady($shopId, $apiChunk);
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
    private function ensureShippingDocumentsReady(int $shopId, array $orderList): void
    {
        $statuses = $this->fetchShippingDocumentStatuses($shopId, $orderList);
        if ($this->allReady($statuses)) {
            return;
        }

        $needsCreate = [];
        foreach ($orderList as $row) {
            $key = $this->resultKey($row['order_sn'], $row['package_number'] ?? null);
            $status = strtoupper(trim((string) ($statuses[$key] ?? 'PROCESSING')));
            if ($status !== 'READY') {
                $needsCreate[] = $row;
            }
        }

        if ($needsCreate !== []) {
            $this->createShippingDocument($shopId, $needsCreate);
        }

        $this->pollUntilReady($shopId, $orderList);
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
        $decoded = $this->decodeLogisticsBatchResponse($response, 'create_shipping_document');

        $this->assertCreatePerOrderFailures($decoded, 'create_shipping_document');
    }

    /**
     * @param  list<array{order_sn: string, package_number?: string, tracking_number?: string}>  $orderList
     * @return array<string, string>
     */
    private function fetchShippingDocumentStatuses(int $shopId, array $orderList): array
    {
        $docType = $this->shippingDocumentType();
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
        $decoded = $this->decodeLogisticsBatchResponse($response, 'get_shipping_document_result');

        return $this->resultStatuses($decoded, $orderList);
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
            $decoded = $this->decodeLogisticsBatchResponse($response, 'get_shipping_document_result');

            $statuses = $this->resultStatuses($decoded, $orderList);
            if ($this->allReady($statuses)) {
                return;
            }

            if ($this->anyFailed($statuses)) {
                $detail = $this->formatFailedDocumentOrders($decoded, $orderList);

                throw new \RuntimeException('Shopee gagal membuat AWB untuk: '.$detail);
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

        $orderStatus = strtoupper(trim((string) ($orderRow['order_status'] ?? '')));
        $headerTracking = trim((string) ($orderRow['tracking_number'] ?? ''));
        if ($headerTracking === '' && $this->orderStatusMayHaveTracking($orderStatus)) {
            $headerTracking = $this->fetchTrackingNumber($shopId, $orderSn, null);
        }
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
            $tracking = trim((string) ($package['tracking_number'] ?? ''));
            if ($tracking === '' && $headerTracking !== '') {
                $tracking = $headerTracking;
            }
            if ($tracking === '' && $packageNumber !== '' && $this->orderStatusMayHaveTracking($orderStatus)) {
                $tracking = $this->fetchTrackingNumber($shopId, $orderSn, $packageNumber);
            }
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
     * @param  array<string, mixed>  $decoded
     * @param  list<array{order_sn: string, package_number?: string}>  $orderList
     */
    private function formatFailedDocumentOrders(array $decoded, array $orderList): string
    {
        $lines = [];
        $results = $decoded['response']['result_list'] ?? [];
        if (is_array($results)) {
            foreach ($results as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (strtoupper(trim((string) ($row['status'] ?? ''))) !== 'FAILED') {
                    continue;
                }
                $sn = trim((string) ($row['order_sn'] ?? ''));
                $fail = trim((string) ($row['fail_error'] ?? ''));
                $msg = trim((string) ($row['fail_message'] ?? ''));
                $line = $sn !== '' ? $sn : '?';
                if ($fail !== '') {
                    $line .= " ({$fail}";
                    $line .= $msg !== '' ? ": {$msg})" : ')';
                } elseif ($msg !== '') {
                    $line .= " ({$msg})";
                }
                $lines[] = $line;
            }
        }

        if ($lines === []) {
            return implode(', ', array_keys(array_filter(
                $this->resultStatuses($decoded, $orderList),
                fn ($s) => strtoupper($s) === 'FAILED',
            )));
        }

        return implode('; ', $lines);
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
    private function assertCreatePerOrderFailures(array $decoded, string $context): void
    {
        $results = $decoded['response']['result_list'] ?? [];
        if (! is_array($results)) {
            return;
        }

        $fatals = [];
        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fail = trim((string) ($row['fail_error'] ?? ''));
            if ($fail === '' || $this->ignorableCreateFailure($fail, $row)) {
                continue;
            }
            $sn = $row['order_sn'] ?? '?';
            $msg = trim((string) ($row['fail_message'] ?? $fail));
            $fatals[] = "{$sn}: {$fail}".($msg !== '' && $msg !== $fail ? " — {$msg}" : '');
        }

        if ($fatals !== []) {
            $requestId = trim((string) ($decoded['request_id'] ?? ''));
            $suffix = $requestId !== '' ? " (request_id: {$requestId})" : '';

            throw new \RuntimeException("{$context} — ".implode('; ', $fatals).$suffix);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function ignorableCreateFailure(string $failError, array $row): bool
    {
        $failErrorLower = strtolower($failError);
        $messageLower = strtolower(trim((string) ($row['fail_message'] ?? '')));

        if (str_contains($failErrorLower, 'duplicat') || str_contains($messageLower, 'duplicat')) {
            return true;
        }

        return in_array($failErrorLower, [
            'logistics.package_can_not_print',
        ], true);
    }

    private function orderStatusMayHaveTracking(string $orderStatus): bool
    {
        return in_array($orderStatus, ['READY_TO_SHIP', 'PROCESSED', 'RETRY_SHIP', 'SHIPPED'], true);
    }

    private function fetchTrackingNumber(int $shopId, string $orderSn, ?string $packageNumber): string
    {
        $query = ['order_sn' => $orderSn];
        $packageNumber = trim((string) $packageNumber);
        if ($packageNumber !== '') {
            $query['package_number'] = $packageNumber;
        }

        $response = $this->shopeeApi->shopApiGet('/api/v2/logistics/get_tracking_number', $query, $shopId);
        $decoded = $this->shopeeApi->decodeShopResponse($response, 'get_tracking_number');
        if ($decoded === null) {
            return '';
        }

        $tracking = trim((string) ($decoded['response']['tracking_number'] ?? ''));

        return $tracking;
    }

    /**
     * Batch logistics responses may set top-level error to common.batch_api_all_failed while still
     * returning per-order result_list — decodeShopResponse treats that as a hard failure.
     *
     * @return array<string, mixed>
     */
    private function decodeLogisticsBatchResponse(Response $response, string $context): array
    {
        if (! $response->successful()) {
            $body = $response->body();
            Log::warning('Shopee logistics API HTTP error', [
                'context' => $context,
                'status' => $response->status(),
                'body' => mb_substr($body, 0, 2000),
            ]);

            throw new \RuntimeException("Shopee {$context} HTTP {$response->status()}.");
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new \RuntimeException("Shopee {$context} mengembalikan JSON tidak valid.");
        }

        if (isset($data[0]) && is_array($data[0]) && array_key_exists('error', $data[0])) {
            $data = $data[0];
        }

        $error = trim((string) ($data['error'] ?? ''));
        $hasResultList = is_array($data['response']['result_list'] ?? null);

        if ($error === '' || ($error === self::BATCH_ALL_FAILED_ERROR && $hasResultList)) {
            return $data;
        }

        $message = trim((string) ($data['message'] ?? ''));
        $detail = $message !== '' ? "{$error} — {$message}" : $error;
        $requestId = trim((string) ($data['request_id'] ?? ''));
        if ($requestId !== '') {
            $detail .= " (request_id: {$requestId})";
        }

        Log::warning('Shopee logistics API error', [
            'context' => $context,
            'error' => $error,
            'message' => $message,
            'request_id' => $requestId,
        ]);

        throw new \RuntimeException("Shopee {$context}: {$detail}");
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