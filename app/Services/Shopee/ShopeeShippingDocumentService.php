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

    public const POLL_MAX_SECONDS = 90;

    private const BATCH_ALL_FAILED_ERROR = 'common.batch_api_all_failed';

    private const ERROR_SHOULD_PRINT_FIRST = 'logistics.shipping_document_should_print_first';

    private const ORDER_STATUSES_NEED_ARRANGE = ['PROCESSED', 'READY_TO_SHIP', 'RETRY_SHIP'];

    private const MAX_SHOULD_PRINT_FIRST_RETRIES = 2;

    private const ERROR_TRACKING_NUMBER_INVALID = 'logistics.tracking_number_invalid';

    public function __construct(
        private ShopeeAdminOpenApiService $shopeeApi,
        private ShopeeAwbPdfPortraitNormalizer $awbPdfPortraitNormalizer,
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
     * @return list<string> One PDF binary string per API batch (≤50 orders each)
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
            if ($status === 'READY') {
                continue;
            }
            // Task already queued by Seller Center or a prior create — poll only (do not re-create).
            if ($status === 'PROCESSING') {
                continue;
            }
            $needsCreate[] = $row;
        }

        if ($needsCreate !== []) {
            $this->arrangeShipmentsBeforeCreate($shopId, $needsCreate);
            $needsCreate = $this->refreshVerifiedTrackingOnOrderList($shopId, $needsCreate);
            $this->createShippingDocumentWithArrangeRetry($shopId, $needsCreate);
        }

        $this->pollUntilReady($shopId, $orderList);
    }

    /**
     * @param  list<array{order_sn: string, package_number?: string, tracking_number?: string}>  $orderList
     */
    private function createShippingDocumentWithArrangeRetry(int $shopId, array $orderList): void
    {
        try {
            $this->createShippingDocument($shopId, $orderList);
        } catch (\RuntimeException $e) {
            if (! $this->exceptionSuggestsArrangeShipmentFirst($e)) {
                throw $e;
            }

            $this->arrangeShipmentsBeforeCreate($shopId, $orderList, force: true);
            $orderList = $this->refreshVerifiedTrackingOnOrderList($shopId, $orderList);
            $this->createShippingDocument($shopId, $orderList);
        }
    }

    /**
     * @param  list<array{order_sn: string, package_number?: string, tracking_number?: string}>  $orderList
     */
    private function createShippingDocument(int $shopId, array $orderList): void
    {
        $decoded = $this->postCreateShippingDocument($shopId, $orderList, includeTracking: true);

        $retryRows = $this->orderListRowsWithCreateError($decoded, $orderList, self::ERROR_TRACKING_NUMBER_INVALID);
        if ($retryRows !== []) {
            $retryDecoded = $this->postCreateShippingDocument(
                $shopId,
                $this->stripTrackingFromOrderList($retryRows),
                includeTracking: false,
            );
            $decoded = $this->mergeCreateShippingDocumentResults($decoded, $retryDecoded);
        }

        $stillInvalid = $this->orderListRowsWithCreateError($decoded, $orderList, self::ERROR_TRACKING_NUMBER_INVALID);
        if ($stillInvalid !== []) {
            $withFreshTracking = $this->applyFreshTrackingFromApi($shopId, $stillInvalid);
            if ($this->orderListHasVerifiedTracking($withFreshTracking)) {
                $retryDecoded = $this->postCreateShippingDocument($shopId, $withFreshTracking, includeTracking: true);
                $decoded = $this->mergeCreateShippingDocumentResults($decoded, $retryDecoded);
            }
        }

        $stillInvalid = $this->orderListRowsWithCreateError($decoded, $orderList, self::ERROR_TRACKING_NUMBER_INVALID);
        if ($stillInvalid !== []) {
            Log::info('Shopee create_shipping_document tracking_number_invalid after omit — polling existing AWB task', [
                'shop_id' => $shopId,
                'order_sns' => array_values(array_unique(array_map(
                    fn (array $row) => trim((string) ($row['order_sn'] ?? '')),
                    $stillInvalid,
                ))),
            ]);
        }

        $this->assertCreatePerOrderFailures($decoded, 'create_shipping_document');
    }

    /**
     * Shopee assigns resi on create; stale tracking from get_order_detail often triggers
     * logistics.tracking_number_invalid on READY_TO_SHIP / unsplit orders — omit unless explicitly asked.
     *
     * @param  list<array{order_sn: string, package_number?: string, tracking_number?: string}>  $orderList
     * @return array<string, mixed>
     */
    private function postCreateShippingDocument(int $shopId, array $orderList, bool $includeTracking): array
    {
        $docType = $this->shippingDocumentType();
        $payload = [
            'order_list' => array_map(function (array $row) use ($docType, $includeTracking) {
                $item = ['order_sn' => $row['order_sn'], 'shipping_document_type' => $docType];
                if (filled($row['package_number'] ?? null)) {
                    $item['package_number'] = (string) $row['package_number'];
                }
                if ($includeTracking && filled($row['tracking_number'] ?? null) && ($row['tracking_verified'] ?? false)) {
                    $item['tracking_number'] = (string) $row['tracking_number'];
                }

                return $item;
            }, $orderList),
        ];

        $response = $this->shopeeApi->shopApiPost('/api/v2/logistics/create_shipping_document', $payload, $shopId);

        return $this->decodeLogisticsBatchResponse($response, 'create_shipping_document');
    }

    /**
     * @param  list<array{order_sn: string, package_number?: string, tracking_number?: string}>  $orderList
     * @return list<array{order_sn: string, package_number?: string, tracking_number?: string}>
     */
    private function stripTrackingFromOrderList(array $orderList): array
    {
        return array_map(function (array $row) {
            unset($row['tracking_number'], $row['tracking_verified']);

            return $row;
        }, $orderList);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  list<array{order_sn: string, package_number?: string, tracking_number?: string}>  $orderList
     * @return list<array{order_sn: string, package_number?: string, tracking_number?: string}>
     */
    private function orderListRowsWithCreateError(array $decoded, array $orderList, string $failError): array
    {
        $failErrorLower = strtolower($failError);
        $retry = [];

        $results = $decoded['response']['result_list'] ?? [];
        if (! is_array($results)) {
            return [];
        }

        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }
            $perFail = strtolower(trim((string) ($row['fail_error'] ?? '')));
            if ($perFail !== $failErrorLower) {
                continue;
            }
            $sn = trim((string) ($row['order_sn'] ?? ''));
            if ($sn === '') {
                continue;
            }
            $pkg = trim((string) ($row['package_number'] ?? ''));
            foreach ($orderList as $entry) {
                $entrySn = trim((string) ($entry['order_sn'] ?? ''));
                if ($entrySn !== $sn) {
                    continue;
                }
                $entryPkg = trim((string) ($entry['package_number'] ?? ''));
                if ($pkg !== '' && $entryPkg !== $pkg) {
                    continue;
                }
                if ($pkg === '' && $entryPkg !== '') {
                    continue;
                }
                $retry[] = $entry;

                break;
            }
        }

        return $retry;
    }

    /**
     * @param  array<string, mixed>  $primary
     * @param  array<string, mixed>  $retry
     * @return array<string, mixed>
     */
    private function mergeCreateShippingDocumentResults(array $primary, array $retry): array
    {
        $primaryList = $primary['response']['result_list'] ?? [];
        $retryList = $retry['response']['result_list'] ?? [];
        if (! is_array($primaryList) || ! is_array($retryList)) {
            return $primary;
        }

        $retryByKey = [];
        foreach ($retryList as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sn = trim((string) ($row['order_sn'] ?? ''));
            if ($sn === '') {
                continue;
            }
            $pkg = trim((string) ($row['package_number'] ?? ''));
            $retryByKey[$this->resultKey($sn, $pkg !== '' ? $pkg : null)] = $row;
        }

        $merged = [];
        foreach ($primaryList as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sn = trim((string) ($row['order_sn'] ?? ''));
            $pkg = trim((string) ($row['package_number'] ?? ''));
            $key = $this->resultKey($sn, $pkg !== '' ? $pkg : null);
            $merged[] = $retryByKey[$key] ?? $row;
        }

        foreach ($retryList as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sn = trim((string) ($row['order_sn'] ?? ''));
            $pkg = trim((string) ($row['package_number'] ?? ''));
            $key = $this->resultKey($sn, $pkg !== '' ? $pkg : null);
            $found = false;
            foreach ($merged as $existing) {
                if (! is_array($existing)) {
                    continue;
                }
                $existingSn = trim((string) ($existing['order_sn'] ?? ''));
                $existingPkg = trim((string) ($existing['package_number'] ?? ''));
                if ($this->resultKey($existingSn, $existingPkg !== '' ? $existingPkg : null) === $key) {
                    $found = true;

                    break;
                }
            }
            if (! $found) {
                $merged[] = $row;
            }
        }

        $primary['response']['result_list'] = $merged;
        if (trim((string) ($retry['request_id'] ?? '')) !== '') {
            $primary['request_id'] = $retry['request_id'];
        }

        return $primary;
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
        $decoded = $this->decodeLogisticsBatchResponse(
            $response,
            'get_shipping_document_result',
            allowMissingDocumentTask: true,
        );

        return $this->resultStatuses($decoded, $orderList);
    }

    /**
     * @param  list<array{order_sn: string, package_number?: string, tracking_number?: string}>  $orderList
     */
    private function pollUntilReady(int $shopId, array $orderList): void
    {
        $deadline = microtime(true) + $this->documentPollMaxSeconds();
        $docType = $this->shippingDocumentType();
        $shouldPrintFirstRetries = 0;

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

            if (! $response->successful()) {
                throw new \RuntimeException('Shopee get_shipping_document_result HTTP '.$response->status().'.');
            }

            $raw = $response->json();
            if ($this->rawResponseIndicatesShouldPrintFirst($raw)
                && $shouldPrintFirstRetries < self::MAX_SHOULD_PRINT_FIRST_RETRIES) {
                $shouldPrintFirstRetries++;
                $this->recoverMissingShippingDocumentTask($shopId, $orderList);

                continue;
            }

            $decoded = $this->decodeLogisticsBatchResponse(
                $response,
                'get_shipping_document_result',
                allowMissingDocumentTask: true,
            );

            $statuses = $this->resultStatuses($decoded, $orderList);
            if ($this->allReady($statuses)) {
                return;
            }

            if ($this->resultListHasShouldPrintFirst($decoded)
                && $shouldPrintFirstRetries < self::MAX_SHOULD_PRINT_FIRST_RETRIES) {
                $shouldPrintFirstRetries++;
                $this->recoverMissingShippingDocumentTask($shopId, $orderList);

                continue;
            }

            if ($this->anyFailed($statuses)) {
                $detail = $this->formatFailedDocumentOrders($decoded, $orderList);
                $trackingHint = $this->formatTrackingResiHint($orderList);

                throw new \RuntimeException('Shopee gagal membuat AWB untuk: '.$detail.$trackingHint);
            }

            Sleep::sleep(1);
        } while (microtime(true) < $deadline);

        throw new \RuntimeException(
            'Timeout menunggu AWB Shopee ('.$this->documentPollMaxSeconds().' detik).'
            .$this->formatTrackingResiHint($orderList)
            .$this->formatTrackingTimeoutRetryHint($orderList),
        );
    }

    /**
     * @param  list<array{order_sn: string, package_number?: string, tracking_number?: string}>  $orderList
     */
    private function recoverMissingShippingDocumentTask(int $shopId, array $orderList): void
    {
        $this->arrangeShipmentsBeforeCreate($shopId, $orderList, force: true);
        $orderList = $this->refreshVerifiedTrackingOnOrderList($shopId, $orderList);
        $this->createShippingDocument($shopId, $orderList);
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

        return $this->awbPdfPortraitNormalizer->normalize($body);
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

        $splitShipment = $this->orderIsSplitShipment($packages);

        $entries = [];
        foreach ($packages as $package) {
            if (! is_array($package)) {
                continue;
            }
            $packageNumber = trim((string) ($package['package_number'] ?? ''));
            $entry = ['order_sn' => $orderSn];
            if ($splitShipment && $packageNumber !== '') {
                $entry['package_number'] = $packageNumber;
            }
            $tracking = trim((string) ($package['tracking_number'] ?? ''));
            if ($tracking === '' && $headerTracking !== '') {
                $tracking = $headerTracking;
            }
            if ($tracking === '' && $this->orderStatusMayHaveTracking($orderStatus)) {
                if ($splitShipment && $packageNumber !== '') {
                    $tracking = $this->fetchTrackingNumber($shopId, $orderSn, $packageNumber);
                } elseif (! $splitShipment) {
                    $tracking = $this->fetchTrackingNumber($shopId, $orderSn, null);
                }
            }
            if ($tracking !== '') {
                $entry['tracking_number'] = $tracking;
            }
            $entries[] = $entry;
        }

        return $entries !== [] ? $entries : [['order_sn' => $orderSn]];
    }

    /**
     * Shopee unsplit orders must not send package_number on ship_order / get_shipping_parameter.
     *
     * @param  list<mixed>  $packages
     */
    private function orderIsSplitShipment(array $packages): bool
    {
        $count = 0;
        foreach ($packages as $package) {
            if (! is_array($package)) {
                continue;
            }
            $count++;
            if ($count > 1) {
                return true;
            }
        }

        return false;
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
            // Default when Shopee returns no row (e.g. should_print_first) — not an in-flight task.
            $expected[$key] = 'UNKNOWN';
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

        if ($failErrorLower === self::ERROR_TRACKING_NUMBER_INVALID) {
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
     * Poll get_tracking_number after arrange — Shopee may delay 3PL resi up to ~5 minutes.
     */
    public function waitForTrackingNumber(int $shopId, string $orderSn, ?string $packageNumber = null): string
    {
        $orderSn = trim($orderSn);
        if ($shopId <= 0 || $orderSn === '') {
            return '';
        }

        $packageNumber = trim((string) $packageNumber);
        if ($packageNumber === '') {
            $packageNumber = null;
        }

        $deadline = microtime(true) + $this->trackingWaitMaxSeconds();

        do {
            $tracking = $this->fetchTrackingNumber($shopId, $orderSn, $packageNumber);
            if ($tracking !== '') {
                return $tracking;
            }

            if (microtime(true) >= $deadline) {
                break;
            }

            Sleep::sleep(2);
        } while (microtime(true) < $deadline);

        return '';
    }

    private function trackingWaitMaxSeconds(): int
    {
        $configured = (int) config('services.shopee_admin.awb_tracking_wait_seconds', 120);

        return max(1, min(300, $configured));
    }

    private function documentPollMaxSeconds(): int
    {
        $configured = (int) config('services.shopee_admin.awb_document_poll_seconds', 0);
        if ($configured > 0) {
            return max(1, min(300, $configured));
        }

        return self::POLL_MAX_SECONDS;
    }

    /**
     * @param  list<array{order_sn: string, package_number?: string, tracking_number?: string}>  $orderList
     * @return list<array{order_sn: string, package_number?: string, tracking_number?: string, tracking_verified?: bool}>
     */
    private function refreshVerifiedTrackingOnOrderList(int $shopId, array $orderList): array
    {
        return array_map(function (array $row) use ($shopId) {
            unset($row['tracking_number'], $row['tracking_verified']);
            $pkg = filled($row['package_number'] ?? null) ? trim((string) $row['package_number']) : null;
            if ($pkg === '') {
                $pkg = null;
            }
            $tracking = $this->waitForTrackingNumber($shopId, (string) $row['order_sn'], $pkg);
            if ($tracking !== '') {
                $row['tracking_number'] = $tracking;
                $row['tracking_verified'] = true;
            }

            return $row;
        }, $orderList);
    }

    /**
     * @param  list<array{order_sn: string, package_number?: string, tracking_number?: string}>  $orderList
     * @return list<array{order_sn: string, package_number?: string, tracking_number?: string, tracking_verified?: bool}>
     */
    private function applyFreshTrackingFromApi(int $shopId, array $orderList): array
    {
        return array_map(function (array $row) use ($shopId) {
            unset($row['tracking_number'], $row['tracking_verified']);
            $pkg = filled($row['package_number'] ?? null) ? trim((string) $row['package_number']) : null;
            if ($pkg === '') {
                $pkg = null;
            }
            $tracking = $this->waitForTrackingNumber($shopId, (string) $row['order_sn'], $pkg);
            if ($tracking !== '') {
                $row['tracking_number'] = $tracking;
                $row['tracking_verified'] = true;
            }

            return $row;
        }, $orderList);
    }

    /**
     * @param  list<array{order_sn: string, package_number?: string, tracking_number?: string, tracking_verified?: bool}>  $orderList
     */
    private function orderListHasVerifiedTracking(array $orderList): bool
    {
        foreach ($orderList as $row) {
            if (($row['tracking_verified'] ?? false) && filled($row['tracking_number'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{order_sn: string, package_number?: string, tracking_number?: string}>  $orderList
     */
    private function arrangeShipmentsBeforeCreate(int $shopId, array $orderList, bool $force = false): void
    {
        $seen = [];
        foreach ($orderList as $row) {
            $orderSn = trim((string) ($row['order_sn'] ?? ''));
            if ($orderSn === '' || isset($seen[$orderSn])) {
                continue;
            }
            $seen[$orderSn] = true;

            $packageNumber = filled($row['package_number'] ?? null)
                ? trim((string) $row['package_number'])
                : null;
            if ($packageNumber === '') {
                $packageNumber = null;
            }

            if (! $force && ! $this->orderSnNeedsArrangeShipment($shopId, $orderSn)) {
                continue;
            }

            $this->arrangeShipmentForOrder($shopId, $orderSn, $packageNumber);
        }
    }

    private function orderSnNeedsArrangeShipment(int $shopId, string $orderSn): bool
    {
        $detail = $this->shopeeApi->getOrderDetail(
            $orderSn,
            $shopId,
            ShopeeFlexiOrderDetailFields::OPTIONAL,
        );
        $orderRow = $this->firstOrderRow($detail);
        if ($orderRow === null) {
            return true;
        }

        $status = strtoupper(trim((string) ($orderRow['order_status'] ?? '')));

        return in_array($status, self::ORDER_STATUSES_NEED_ARRANGE, true);
    }

    private function arrangeShipmentForOrder(int $shopId, string $orderSn, ?string $packageNumber): void
    {
        $query = ['order_sn' => $orderSn];
        if ($packageNumber !== null) {
            $query['package_number'] = $packageNumber;
        }

        $parameterResponse = $this->shopeeApi->shopApiGet('/api/v2/logistics/get_shipping_parameter', $query, $shopId);
        $parameters = $this->shopeeApi->decodeShopResponse($parameterResponse, 'get_shipping_parameter');
        if ($parameters === null) {
            Log::warning('Shopee arrange shipment skipped — get_shipping_parameter failed', [
                'shop_id' => $shopId,
                'order_sn' => $orderSn,
            ]);

            return;
        }

        $shipBody = $this->buildShipOrderBody($orderSn, $packageNumber, $parameters);
        if ($shipBody === null) {
            Log::info('Shopee arrange shipment skipped — no ship_order payload', [
                'shop_id' => $shopId,
                'order_sn' => $orderSn,
            ]);

            return;
        }

        $shipResponse = $this->shopeeApi->shopApiPost('/api/v2/logistics/ship_order', $shipBody, $shopId);
        try {
            $this->decodeLogisticsBatchResponse($shipResponse, 'ship_order');
        } catch (\RuntimeException $e) {
            if ($this->exceptionIsIgnorableShipOrderFailure($e)) {
                Log::info('Shopee ship_order benign failure', [
                    'shop_id' => $shopId,
                    'order_sn' => $orderSn,
                    'message' => $e->getMessage(),
                ]);

                return;
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>|null
     */
    private function buildShipOrderBody(string $orderSn, ?string $packageNumber, array $parameters): ?array
    {
        $response = $parameters['response'] ?? [];
        if (! is_array($response)) {
            return null;
        }

        $infoNeeded = $response['info_needed'] ?? [];
        if (! is_array($infoNeeded)) {
            return null;
        }

        $body = ['order_sn' => $orderSn];
        if ($packageNumber !== null && $packageNumber !== '') {
            $body['package_number'] = $packageNumber;
        }

        $pickupFields = $infoNeeded['pickup'] ?? null;
        if (is_array($pickupFields) && $pickupFields !== []) {
            $pickup = $response['pickup'] ?? [];
            if (! is_array($pickup)) {
                return null;
            }
            $addressList = $pickup['address_list'] ?? [];
            if (! is_array($addressList) || $addressList === []) {
                return null;
            }
            $address = $addressList[0];
            if (! is_array($address)) {
                return null;
            }
            $addressId = $address['address_id'] ?? null;
            $pickupTimeId = null;
            $timeSlots = $address['time_slot_list'] ?? [];
            if (is_array($timeSlots) && $timeSlots !== [] && is_array($timeSlots[0])) {
                $pickupTimeId = $timeSlots[0]['pickup_time_id'] ?? null;
            }
            if ($addressId === null || $pickupTimeId === null) {
                return null;
            }

            $body['pickup'] = [
                'address_id' => (int) $addressId,
                'pickup_time_id' => (string) $pickupTimeId,
            ];

            return $body;
        }

        if (array_key_exists('dropoff', $infoNeeded)) {
            $dropoff = new \stdClass;
            $dropoffFields = $infoNeeded['dropoff'];
            if (is_array($dropoffFields) && in_array('branch_id', $dropoffFields, true)) {
                $branches = $response['dropoff']['branch_list'] ?? [];
                if (is_array($branches) && $branches !== [] && is_array($branches[0])) {
                    $branchId = $branches[0]['branch_id'] ?? null;
                    if ($branchId !== null) {
                        $dropoff = ['branch_id' => (int) $branchId];
                    }
                }
            }

            $body['dropoff'] = $dropoff;

            return $body;
        }

        return null;
    }

    private function exceptionSuggestsArrangeShipmentFirst(\RuntimeException $exception): bool
    {
        $haystack = strtolower($exception->getMessage());

        return str_contains($haystack, 'ship the order first')
            || str_contains($haystack, 'ship_order')
            || str_contains($haystack, 'package_should_ship')
            || str_contains($haystack, 'logistics.package_not_ready')
            || str_contains($haystack, self::ERROR_SHOULD_PRINT_FIRST)
            || str_contains($haystack, 'should print first');
    }

    private function exceptionIsIgnorableShipOrderFailure(\RuntimeException $exception): bool
    {
        $haystack = strtolower($exception->getMessage());

        return str_contains($haystack, 'already')
            || str_contains($haystack, 'shipped')
            || str_contains($haystack, 'ready_to_ship');
    }

    /**
     * Batch logistics responses may set top-level error to common.batch_api_all_failed while still
     * returning per-order result_list — decodeShopResponse treats that as a hard failure.
     *
     * @return array<string, mixed>
     */
    private function decodeLogisticsBatchResponse(
        Response $response,
        string $context,
        bool $allowMissingDocumentTask = false,
    ): array {
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

        if ($allowMissingDocumentTask && $error === self::ERROR_SHOULD_PRINT_FIRST) {
            return [
                'error' => '',
                'response' => ['result_list' => is_array($data['response']['result_list'] ?? null)
                    ? $data['response']['result_list']
                    : []],
                'request_id' => $data['request_id'] ?? null,
            ];
        }

        if ($error === '' || ($error === self::BATCH_ALL_FAILED_ERROR && $hasResultList)) {
            return $data;
        }

        $message = trim((string) ($data['message'] ?? ''));
        $detail = $this->formatLogisticsErrorForUser($error, $message);
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

    private function rawResponseIndicatesShouldPrintFirst(mixed $data): bool
    {
        if (! is_array($data)) {
            return false;
        }

        if (isset($data[0]) && is_array($data[0]) && array_key_exists('error', $data[0])) {
            $data = $data[0];
        }

        $error = strtolower(trim((string) ($data['error'] ?? '')));
        $message = strtolower(trim((string) ($data['message'] ?? '')));

        if ($error === strtolower(self::ERROR_SHOULD_PRINT_FIRST)
            || str_contains($message, 'create shipping document first')
            || str_contains($message, 'should print first')) {
            return true;
        }

        return $this->resultListHasShouldPrintFirst($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resultListHasShouldPrintFirst(array $data): bool
    {
        $results = $data['response']['result_list'] ?? [];
        if (! is_array($results)) {
            return false;
        }

        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fail = strtolower(trim((string) ($row['fail_error'] ?? '')));
            $message = strtolower(trim((string) ($row['fail_message'] ?? '')));
            if ($fail === strtolower(self::ERROR_SHOULD_PRINT_FIRST)
                || str_contains($message, 'create shipping document first')
                || str_contains($message, 'should print first')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{order_sn: string, package_number?: string, tracking_number?: string}>  $orderList
     */
    private function formatTrackingResiHint(array $orderList): string
    {
        $lines = [];
        foreach ($orderList as $row) {
            $sn = trim((string) ($row['order_sn'] ?? ''));
            if ($sn === '') {
                continue;
            }
            $tracking = trim((string) ($row['tracking_number'] ?? ''));
            $lines[] = $tracking !== ''
                ? "{$sn}: resi {$tracking} (dari get_order_detail/get_tracking_number)"
                : "{$sn}: resi tidak ditemukan di Aria";
        }

        if ($lines === []) {
            return '';
        }

        return ' — '.implode('; ', $lines);
    }

    /**
     * @param  list<array{order_sn: string, package_number?: string, tracking_number?: string}>  $orderList
     */
    private function formatTrackingTimeoutRetryHint(array $orderList): string
    {
        foreach ($orderList as $row) {
            if (trim((string) ($row['tracking_number'] ?? '')) !== '') {
                return ' Dokumen mungkin masih diproses Shopee — tunggu 1–2 menit lalu cetak AWB lagi (resi sudah ada).';
            }
        }

        return '';
    }

    private function formatLogisticsErrorForUser(string $error, string $message): string
    {
        $errorLower = strtolower($error);
        $messageLower = strtolower($message);

        if ($error === self::ERROR_SHOULD_PRINT_FIRST
            || str_contains($messageLower, 'create shipping document first')
            || str_contains($messageLower, 'should print first')) {
            return self::ERROR_SHOULD_PRINT_FIRST.' — Belum ada task AWB di Shopee setelah percobaan arrange + create. Pastikan order READY_TO_SHIP/PROCESSED dan punya resi di Seller Center.';
        }

        if (str_contains($errorLower, 'package_should_ship')
            || str_contains($messageLower, 'ship the order first')) {
            return $error.' — Order belum di-arrange (ship_order). Aria mencoba arrange otomatis; jika gagal, atur pengiriman di Shopee Seller Center lalu cetak AWB lagi.';
        }

        return $message !== '' ? "{$error} — {$message}" : $error;
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
