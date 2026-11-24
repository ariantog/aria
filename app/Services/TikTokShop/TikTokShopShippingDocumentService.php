<?php

namespace App\Services\TikTokShop;

use App\Models\TiktokShopSync;
use App\Services\Shopee\ShopeeAwbPdfPortraitNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;

/**
 * Bulk AWB via TikTok Shop Fulfillment API (Ship Package + Get Package Shipping Document).
 *
 * Tokopedia orders on TikTok commerce_platform use the same flow and shop_cipher.
 */
class TikTokShopShippingDocumentService
{
    public const MAX_ORDERS_PER_BATCH = 50;

    public const POLL_MAX_SECONDS = 90;

    public function __construct(
        private TikTokShopOpenApiService $openApi,
        private ShopeeAwbPdfPortraitNormalizer $awbPdfPortraitNormalizer,
    ) {}

    public function resolveShopCipher(?string $shopRef): ?string
    {
        $shopRef = trim((string) $shopRef);

        if ($shopRef !== '' && Schema::hasTable('tiktok_shop_syncs')) {
            $fromSync = TiktokShopSync::query()
                ->where('shop_ref', $shopRef)
                ->value('shop_cipher');
            if (filled($fromSync)) {
                return trim((string) $fromSync);
            }
        }

        foreach ($this->openApi->listAuthorizedShops() as $shop) {
            $id = trim((string) ($shop['id'] ?? ''));
            $cipher = trim((string) ($shop['cipher'] ?? ''));
            if ($shopRef !== '' && ($id === $shopRef || $cipher === $shopRef) && $cipher !== '') {
                return $cipher;
            }
        }

        $fallback = trim((string) config('tiktok_shop.shop_cipher', ''));

        return $fallback !== '' ? $fallback : null;
    }

    /**
     * Ship package(s) per order and verify shipping document URL is available (no PDF download).
     *
     * @param  list<string>  $orderIds
     */
    public function prepareShippingDocumentsForShop(string $shopCipher, array $orderIds): void
    {
        $orderIds = $this->normalizeOrderIdList($orderIds);
        if ($orderIds === []) {
            throw new \InvalidArgumentException('Tidak ada order TikTok yang dipilih.');
        }

        $shopCipher = trim($shopCipher);
        if ($shopCipher === '') {
            throw new \InvalidArgumentException('shop_cipher tidak valid.');
        }

        $this->assertShopAuthorized($shopCipher);

        foreach (array_chunk($orderIds, self::MAX_ORDERS_PER_BATCH) as $chunk) {
            foreach ($chunk as $orderId) {
                $this->ensureShippingDocumentReadyForOrder($shopCipher, $orderId);
            }
        }
    }

    /**
     * @param  list<string>  $orderIds
     * @return list<string> One PDF binary string per order (multiple packages concatenated per order)
     */
    public function downloadPdfBatchesForShop(string $shopCipher, array $orderIds, bool $assumeDocumentsPrepared = false): array
    {
        $orderIds = $this->normalizeOrderIdList($orderIds);
        if ($orderIds === []) {
            throw new \InvalidArgumentException('Tidak ada order TikTok yang dipilih.');
        }

        $shopCipher = trim($shopCipher);
        if ($shopCipher === '') {
            throw new \InvalidArgumentException('shop_cipher tidak valid.');
        }

        $this->assertShopAuthorized($shopCipher);

        $pdfs = [];
        foreach (array_chunk($orderIds, self::MAX_ORDERS_PER_BATCH) as $chunk) {
            foreach ($chunk as $orderId) {
                if (! $assumeDocumentsPrepared) {
                    $this->ensureShippingDocumentReadyForOrder($shopCipher, $orderId);
                }
                $pdfs[] = $this->downloadShippingDocumentPdfForOrder($shopCipher, $orderId);
            }
        }

        if ($pdfs === []) {
            throw new \RuntimeException('Gagal mengunduh dokumen pengiriman TikTok — tidak ada PDF.');
        }

        return $pdfs;
    }

    private function ensureShippingDocumentReadyForOrder(string $shopCipher, string $orderId): void
    {
        if ($this->openApi->isMock()) {
            return;
        }

        foreach ($this->resolvePackageIdsForOrder($shopCipher, $orderId) as $packageId) {
            if ($this->fetchShippingDocumentUrl($shopCipher, $packageId) !== null) {
                continue;
            }

            $this->shipPackageWithRetry($shopCipher, $packageId);
            $this->pollShippingDocumentUrl($shopCipher, $packageId);
        }
    }

    private function downloadShippingDocumentPdfForOrder(string $shopCipher, string $orderId): string
    {
        if ($this->openApi->isMock()) {
            return $this->awbPdfPortraitNormalizer->normalize('%PDF-1.4 mock-tiktok-awb-'.$orderId, true);
        }

        $binaries = [];
        foreach ($this->resolvePackageIdsForOrder($shopCipher, $orderId) as $packageId) {
            $docUrl = $this->fetchShippingDocumentUrl($shopCipher, $packageId);
            if ($docUrl === null) {
                $this->shipPackageWithRetry($shopCipher, $packageId);
                $docUrl = $this->pollShippingDocumentUrl($shopCipher, $packageId);
            }

            $binaries[] = $this->downloadBinaryFromUrl($docUrl, $orderId, $packageId);
        }

        $body = $binaries[0] ?? '';
        if (count($binaries) > 1) {
            $body = $this->concatenatePdfBinaries($binaries);
        }

        if ($body === '') {
            throw new \RuntimeException("TikTok AWB kosong untuk order {$orderId}.");
        }

        return $this->awbPdfPortraitNormalizer->normalize($body, true);
    }

    /**
     * @return list<string>
     */
    private function resolvePackageIdsForOrder(string $shopCipher, string $orderId): array
    {
        $detail = $this->openApi->getOrderDetail($orderId, $shopCipher);
        if ($detail === null) {
            throw new \RuntimeException("TikTok Get Order Detail gagal untuk order {$orderId}.");
        }

        return $this->extractPackageIdsFromOrder($detail, $orderId);
    }

    /**
     * @return list<string>
     */
    private function extractPackageIdsFromOrder(array $response, string $orderId): array
    {
        $order = $this->openApi->extractFirstOrder($response);
        if ($order === null) {
            throw new \RuntimeException("TikTok order {$orderId} tidak ditemukan di respons API.");
        }

        $ids = [];
        $packages = $order['packages'] ?? $order['package_list'] ?? [];
        if (is_array($packages)) {
            foreach ($packages as $package) {
                if (! is_array($package)) {
                    continue;
                }
                $id = trim((string) ($package['id'] ?? $package['package_id'] ?? ''));
                if ($id !== '') {
                    $ids[] = $id;
                }
            }
        }

        if ($ids === [] && isset($order['line_items']) && is_array($order['line_items'])) {
            foreach ($order['line_items'] as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $id = trim((string) ($line['package_id'] ?? ''));
                if ($id !== '' && ! in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
        }

        if ($ids === []) {
            throw new \RuntimeException(
                "TikTok order {$orderId} tidak punya package_id — pastikan status AWAITING_SHIPMENT dan order detail API mengembalikan packages.",
            );
        }

        return array_values(array_unique($ids));
    }

    private function shipPackageWithRetry(string $shopCipher, string $packageId): void
    {
        try {
            $this->openApi->shipPackage($shopCipher, $packageId);
        } catch (\RuntimeException $e) {
            if ($this->exceptionIsIgnorableShipFailure($e)) {
                Log::info('TikTok ship package benign failure', [
                    'package_id' => $packageId,
                    'message' => $e->getMessage(),
                ]);

                return;
            }

            throw $e;
        }
    }

    private function pollShippingDocumentUrl(string $shopCipher, string $packageId): string
    {
        $deadline = microtime(true) + $this->documentPollMaxSeconds();

        do {
            $url = $this->fetchShippingDocumentUrl($shopCipher, $packageId);
            if ($url !== null) {
                return $url;
            }

            if (microtime(true) >= $deadline) {
                break;
            }

            Sleep::sleep(1);
        } while (true);

        throw new \RuntimeException(
            "Timeout menunggu label TikTok untuk package {$packageId} ({$this->documentPollMaxSeconds()} detik).",
        );
    }

    private function fetchShippingDocumentUrl(string $shopCipher, string $packageId): ?string
    {
        $json = $this->openApi->getPackageShippingDocument($shopCipher, $packageId);
        if ($json === null) {
            return null;
        }

        $url = trim((string) ($json['data']['doc_url'] ?? ''));
        if ($url === '') {
            return null;
        }

        return $url;
    }

    private function downloadBinaryFromUrl(string $docUrl, string $orderId, string $packageId): string
    {
        try {
            $response = Http::timeout(30)->get($docUrl);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Gagal mengunduh label TikTok order {$orderId} package {$packageId}: ".$e->getMessage(),
            );
        }

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Unduhan label TikTok HTTP {$response->status()} (order {$orderId}, package {$packageId}).",
            );
        }

        $body = $response->body();
        if ($body === '') {
            throw new \RuntimeException("Label TikTok kosong (order {$orderId}, package {$packageId}).");
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        if (str_contains($contentType, 'json')) {
            throw new \RuntimeException('URL label TikTok mengembalikan JSON, bukan PDF.');
        }

        return $body;
    }

    private function exceptionIsIgnorableShipFailure(\RuntimeException $exception): bool
    {
        $haystack = strtolower($exception->getMessage());

        return str_contains($haystack, 'already')
            || str_contains($haystack, 'shipped')
            || str_contains($haystack, 'awaiting_collection')
            || str_contains($haystack, 'duplicate');
    }

    private function documentPollMaxSeconds(): int
    {
        $configured = (int) config('tiktok_shop.awb_document_poll_seconds', 0);
        if ($configured > 0) {
            return max(1, min(300, $configured));
        }

        return self::POLL_MAX_SECONDS;
    }

    private function assertShopAuthorized(string $shopCipher): void
    {
        if (! $this->openApi->isMock() && ! $this->openApi->liveCredentialsReady($shopCipher)) {
            throw new \RuntimeException('TikTok Shop belum diotorisasi (OAuth / shop_cipher).');
        }
    }

    /**
     * @param  list<string>  $orderIds
     * @return list<string>
     */
    private function normalizeOrderIdList(array $orderIds): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($id) => trim((string) $id),
            $orderIds,
        ), static fn ($id) => $id !== '')));
    }

    /**
     * @param  list<string>  $binaries
     */
    private function concatenatePdfBinaries(array $binaries): string
    {
        if ($binaries === []) {
            return '';
        }

        if (count($binaries) === 1) {
            return $binaries[0];
        }

        try {
            $out = new \App\Services\Shopee\Support\ShopeeAwbPortraitPdf;
            $out->SetAutoPageBreak(false);

            foreach ($binaries as $binary) {
                if (! str_starts_with($binary, '%PDF')) {
                    continue;
                }
                $pageCount = $out->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($binary));
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $tpl = $out->importPage($pageNo);
                    $size = $out->getTemplateSize($tpl);
                    $out->AddPage($size['orientation'] ?? 'P', [$size['width'], $size['height']]);
                    $out->useImportedPage($tpl, 0, 0, (float) $size['width'], (float) $size['height']);
                }
            }

            return $out->Output('S');
        } catch (\Throwable $e) {
            Log::warning('TikTok AWB PDF merge failed — returning first batch only', [
                'message' => $e->getMessage(),
            ]);

            return $binaries[0];
        }
    }
}