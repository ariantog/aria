<?php

namespace App\Services\TikTokShop;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Read-only TikTok Shop Order Open API (Get Order Detail).
 */
class TikTokShopOpenApiService
{
    /** @var array{http_status: int|null, code: int|string|null, message: string|null, path: string}|null */
    private ?array $lastOpenApiError = null;

    public function __construct(
        protected TikTokShopOAuthService $oauthService,
    ) {}

    /**
     * Last failure from {@see liveSignedGet()} (HTTP status, API code, message, path).
     *
     * @return array{http_status: int|null, code: int|string|null, message: string|null, path: string}|null
     */
    public function getLastOpenApiError(): ?array
    {
        return $this->lastOpenApiError;
    }

    public function isMock(): bool
    {
        return (string) config('tiktok_shop.driver', 'mock') !== 'live';
    }

    /**
     * @return array<string, mixed>|null Decoded API JSON on success.
     */
    public function getOrderDetail(string $orderId, ?string $shopCipher = null, ?string $shopRef = null): ?array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            return null;
        }

        if ($this->isMock()) {
            return $this->mockOrderDetail($orderId);
        }

        return $this->liveOrderDetail($orderId, $shopCipher);
    }

    /**
     * @return array<string, mixed>|null First order object from Get Order Detail JSON.
     */
    public function extractFirstOrder(?array $response): ?array
    {
        if ($response === null) {
            return null;
        }

        $orders = $response['data']['orders'] ?? null;
        if (! is_array($orders) || $orders === []) {
            $single = $response['data']['order'] ?? null;

            return is_array($single) ? $single : null;
        }

        $first = $orders[0] ?? null;

        return is_array($first) ? $first : null;
    }

    /**
     * Safe display fields for omnichannel order detail (no PII dump).
     *
     * @return array<string, mixed>
     */
    public function buildDisplaySnippet(?array $response): array
    {
        $order = $this->extractFirstOrder($response);
        if ($order === null) {
            return [];
        }

        $snippet = [];

        foreach (['status', 'order_status'] as $key) {
            if (filled($order[$key] ?? null)) {
                $snippet['status'] = (string) $order[$key];
                break;
            }
        }

        if (filled($order['commerce_platform'] ?? null)) {
            $snippet['commerce_platform'] = strtoupper((string) $order['commerce_platform']);
        }

        foreach (['payment_method', 'payment_method_name'] as $key) {
            if (filled($order[$key] ?? null)) {
                $snippet['payment_method'] = (string) $order[$key];
                break;
            }
        }

        foreach (['total_amount', 'payment_total_amount', 'order_amount', 'sub_total'] as $key) {
            if (isset($order[$key]) && $order[$key] !== '' && $order[$key] !== null) {
                $snippet['total_amount'] = $order[$key];
                break;
            }
        }

        if (filled($order['currency'] ?? null)) {
            $snippet['currency'] = (string) $order['currency'];
        }

        return $snippet;
    }

    /**
     * Compact JSON-safe subset for optional UI expand (strips buyer/shipping PII keys).
     *
     * @return array<string, mixed>|null
     */
    public function redactDetailForDisplay(?array $response): ?array
    {
        if ($response === null) {
            return null;
        }

        $order = $this->extractFirstOrder($response);
        if ($order === null) {
            return [
                'code' => $response['code'] ?? null,
                'message' => $response['message'] ?? null,
            ];
        }

        $stripKeys = [
            'buyer_email', 'buyer_message', 'recipient_address', 'shipping_address',
            'full_address', 'phone_number', 'buyer_phone', 'name', 'buyer_name',
        ];

        $redactedOrder = $order;
        foreach ($stripKeys as $key) {
            unset($redactedOrder[$key]);
        }

        if (isset($redactedOrder['line_items']) && is_array($redactedOrder['line_items'])) {
            $redactedOrder['line_items'] = array_map(function ($line) {
                if (! is_array($line)) {
                    return $line;
                }
                foreach (['product_image', 'product_name', 'sku_image'] as $drop) {
                    unset($line[$drop]);
                }

                return $line;
            }, $redactedOrder['line_items']);
        }

        return [
            'code' => $response['code'] ?? null,
            'order' => $redactedOrder,
        ];
    }

    public function liveAccessTokenAvailable(): bool
    {
        if ($this->isMock()) {
            return true;
        }

        return filled($this->oauthService->getAccessToken());
    }

    public function partnerListReady(): bool
    {
        if ($this->isMock()) {
            return true;
        }

        $appKey = (string) config('tiktok_shop.app_key', '');
        $appSecret = (string) config('tiktok_shop.app_secret', '');

        return $appKey !== '' && $appSecret !== '' && $this->liveAccessTokenAvailable();
    }

    /**
     * Authorized shops for the seller token (Partner GET /authorization/{version}/shops).
     *
     * @return list<array{id: string, cipher: string, name: string}>
     */
    public function listAuthorizedShops(): array
    {
        if ($this->isMock()) {
            return $this->mockAuthorizedShops();
        }

        $version = (string) config('tiktok_shop.api_version', '202309');
        $json = $this->liveSignedGet('/authorization/'.$version.'/shops', []);

        return $this->normalizeAuthorizedShops($json);
    }

    /**
     * Seller warehouses for a shop cipher (read-only helper for mapping UI).
     *
     * @return list<array{id: string, name: string, type: string, effect_status: string}>
     */
    public function listSellerWarehouses(?string $shopCipher): array
    {
        $shopCipher = trim((string) $shopCipher);
        if ($shopCipher === '') {
            return [];
        }

        if ($this->isMock()) {
            return $this->mockSellerWarehouses($shopCipher);
        }

        $version = (string) config('tiktok_shop.api_version', '202309');
        $json = $this->liveSignedGet('/logistics/'.$version.'/warehouses', [
            'shop_cipher' => $shopCipher,
        ]);

        return $this->normalizeSellerWarehouses($json);
    }

    /**
     * @return array<string, list<array{id: string, name: string, type: string, effect_status: string}>>
     */
    public function listSellerWarehousesByShopCipher(array $shops): array
    {
        $map = [];
        foreach ($shops as $shop) {
            $cipher = trim((string) ($shop['cipher'] ?? ''));
            if ($cipher === '') {
                continue;
            }
            $map[$cipher] = $this->listSellerWarehouses($cipher);
        }

        return $map;
    }

    public function liveCredentialsReady(?string $shopCipher = null, ?string $shopRef = null): bool
    {
        $appKey = (string) config('tiktok_shop.app_key', '');
        $appSecret = (string) config('tiktok_shop.app_secret', '');
        $accessToken = (string) ($this->oauthService->getAccessToken() ?? '');
        $cipher = filled($shopCipher)
            ? trim((string) $shopCipher)
            : (string) config('tiktok_shop.shop_cipher', '');

        return $appKey !== '' && $appSecret !== '' && $accessToken !== '' && $cipher !== '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function extractCommercePlatform(?array $response): ?string
    {
        if ($response === null) {
            return null;
        }

        $orders = $response['data']['orders'] ?? null;
        if (! is_array($orders) || $orders === []) {
            $single = $response['data']['order'] ?? null;
            $orders = is_array($single) ? [$single] : [];
        }

        $first = $orders[0] ?? null;
        if (! is_array($first)) {
            return null;
        }

        $platform = $first['commerce_platform'] ?? null;
        if (! filled($platform)) {
            return null;
        }

        return strtoupper((string) $platform);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mockOrderDetail(string $orderId): array
    {
        $platform = str_starts_with(strtoupper($orderId), 'TOKO-') ? 'TOKOPEDIA' : 'TIKTOK_SHOP';

        $order = [
            'id' => $orderId,
            'commerce_platform' => $platform,
        ];

        if (str_starts_with(strtoupper($orderId), 'ONORDER-')) {
            $order['order_status'] = 'AWAITING_SHIPMENT';
            $order['payment_method_name'] = 'COD';
            $order['total_amount'] = '128000';
            $order['currency'] = 'IDR';
            $order['line_items'] = [
                [
                    'seller_sku' => 'SKU-TTS-ON-1',
                    'quantity' => 2,
                ],
            ];
        }

        return [
            'code' => 0,
            'data' => [
                'orders' => [$order],
            ],
        ];
    }

    /**
     * @param  array<string, string>  $extraQuery
     * @return array<string, mixed>|null
     */
    protected function liveSignedGet(string $path, array $extraQuery = []): ?array
    {
        $this->lastOpenApiError = null;

        $appKey = (string) config('tiktok_shop.app_key', '');
        $appSecret = (string) config('tiktok_shop.app_secret', '');
        $accessToken = (string) ($this->oauthService->getAccessToken() ?? '');

        if ($appKey === '' || $appSecret === '' || $accessToken === '') {
            Log::info('TikTok Shop Open API GET skipped — missing credentials', ['path' => $path]);
            $this->lastOpenApiError = [
                'http_status' => null,
                'code' => 'missing_credentials',
                'message' => 'App key, app secret, atau access token OAuth kosong.',
                'path' => $path,
            ];

            return null;
        }

        $timestamp = time();
        $query = array_merge([
            'app_key' => $appKey,
            'timestamp' => (string) $timestamp,
        ], $extraQuery);

        $sign = $this->signOpenApiRequest($path, $query, $appSecret);
        $query['sign'] = $sign;

        $base = rtrim((string) config('tiktok_shop.api_base_url'), '/');
        $url = $base.$path;

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withHeaders(['x-tts-access-token' => $accessToken])
                ->get($url, $query);

            if (! $response->successful()) {
                $json = $response->json();
                $this->recordOpenApiGetFailure($path, $response->status(), $json);

                Log::warning('TikTok Shop Open API GET HTTP error', [
                    'path' => $path,
                    'status' => $response->status(),
                    'code' => is_array($json) ? ($json['code'] ?? null) : null,
                    'message' => is_array($json) ? ($json['message'] ?? null) : null,
                ]);

                return null;
            }

            $json = $response->json();
            if (! is_array($json)) {
                $this->lastOpenApiError = [
                    'http_status' => $response->status(),
                    'code' => 'invalid_json',
                    'message' => 'Respons bukan JSON.',
                    'path' => $path,
                ];

                return null;
            }

            $apiCode = $json['code'] ?? null;
            if ($apiCode !== null && (int) $apiCode !== 0) {
                $this->recordOpenApiGetFailure($path, $response->status(), $json);

                Log::warning('TikTok Shop Open API GET API error', [
                    'path' => $path,
                    'code' => $apiCode,
                    'message' => $json['message'] ?? null,
                ]);

                return null;
            }

            return $json;
        } catch (\Throwable $e) {
            $this->lastOpenApiError = [
                'http_status' => null,
                'code' => 'exception',
                'message' => $e->getMessage(),
                'path' => $path,
            ];

            Log::warning('TikTok Shop Open API GET failed', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function liveOrderDetail(string $orderId, ?string $shopCipherOverride = null): ?array
    {
        $appKey = (string) config('tiktok_shop.app_key', '');
        $appSecret = (string) config('tiktok_shop.app_secret', '');
        $shopCipher = filled($shopCipherOverride)
            ? trim((string) $shopCipherOverride)
            : (string) config('tiktok_shop.shop_cipher', '');
        $accessToken = (string) ($this->oauthService->getAccessToken() ?? '');

        if ($appKey === '' || $appSecret === '' || $shopCipher === '' || $accessToken === '') {
            Log::info('TikTok Shop getOrderDetail skipped — missing credentials', ['order_id' => $orderId]);

            return null;
        }

        $version = (string) config('tiktok_shop.order_api_version', '202507');
        $path = '/order/'.$version.'/orders';

        return $this->liveSignedGet($path, [
            'shop_cipher' => $shopCipher,
            'ids' => $orderId,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $response
     * @return list<array{id: string, cipher: string, name: string}>
     */
    protected function normalizeAuthorizedShops(?array $response): array
    {
        if ($response === null) {
            return [];
        }

        $shops = $response['data']['shops'] ?? $response['data']['shop_list'] ?? [];
        if (! is_array($shops)) {
            return [];
        }

        $normalized = [];
        foreach ($shops as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = (string) ($row['id'] ?? $row['shop_id'] ?? '');
            $cipher = (string) ($row['cipher'] ?? $row['shop_cipher'] ?? '');
            $name = (string) ($row['name'] ?? $row['shop_name'] ?? $row['region'] ?? '');
            if ($id === '' && $cipher === '') {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'cipher' => $cipher,
                'name' => $name !== '' ? $name : ($id !== '' ? $id : $cipher),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $response
     * @return list<array{id: string, name: string, type: string, effect_status: string}>
     */
    protected function normalizeSellerWarehouses(?array $response): array
    {
        if ($response === null) {
            return [];
        }

        $data = $response['data'] ?? null;
        if (is_array($data) && array_is_list($data)) {
            $rows = $data;
        } else {
            $rows = $response['data']['warehouses'] ?? $response['data']['warehouse_list'] ?? [];
        }
        if (! is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = (string) ($row['id'] ?? $row['warehouse_id'] ?? '');
            if ($id === '') {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'name' => (string) ($row['name'] ?? $row['warehouse_name'] ?? $id),
                'type' => (string) ($row['type'] ?? $row['warehouse_type'] ?? ''),
                'effect_status' => (string) ($row['effect_status'] ?? $row['status'] ?? ''),
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array{id: string, cipher: string, name: string}>
     */
    protected function mockAuthorizedShops(): array
    {
        return [
            [
                'id' => '7490000000000000123',
                'cipher' => 'MOCK_TTS_SHOP_CIPHER',
                'name' => 'Mock TikTok Shop (dev)',
            ],
            [
                'id' => '7490000000000000456',
                'cipher' => 'MOCK_TOKO_SHOP_CIPHER',
                'name' => 'Mock Tokopedia via TikTok (dev)',
            ],
        ];
    }

    /**
     * @return list<array{id: string, name: string, type: string, effect_status: string}>
     */
    protected function mockSellerWarehouses(string $shopCipher): array
    {
        return [
            [
                'id' => 'WH-MOCK-'.$shopCipher,
                'name' => 'Seller warehouse (mock)',
                'type' => 'SALES_WAREHOUSE',
                'effect_status' => 'ENABLED',
            ],
            [
                'id' => 'WH-MOCK-RETURN-'.$shopCipher,
                'name' => 'Return warehouse (mock)',
                'type' => 'RETURN_WAREHOUSE',
                'effect_status' => 'ENABLED',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    protected function recordOpenApiGetFailure(string $path, ?int $httpStatus, ?array $json): void
    {
        $message = null;
        $code = null;
        if (is_array($json)) {
            $message = isset($json['message']) ? (string) $json['message'] : null;
            $code = $json['code'] ?? null;
        }

        $this->lastOpenApiError = [
            'http_status' => $httpStatus,
            'code' => $code,
            'message' => $message,
            'path' => $path,
        ];
    }

    /**
     * TikTok Shop Open API request sign (common partner pattern).
     *
     * @param  array<string, string>  $query
     */
    protected function signOpenApiRequest(string $path, array $query, string $appSecret): string
    {
        unset($query['sign'], $query['access_token']);
        ksort($query);

        $input = $path;
        foreach ($query as $key => $value) {
            $input .= $key.$value;
        }

        $wrapped = $appSecret.$input.$appSecret;

        return hash_hmac('sha256', $wrapped, $appSecret);
    }
}