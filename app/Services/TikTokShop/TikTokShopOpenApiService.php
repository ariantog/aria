<?php

namespace App\Services\TikTokShop;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Read-only TikTok Shop Order Open API (Get Order Detail).
 */
class TikTokShopOpenApiService
{
    public function __construct(
        protected TikTokShopAccessTokenResolver $accessTokenResolver,
    ) {}

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

        return $this->liveOrderDetail($orderId, $shopCipher, $shopRef);
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

    public function liveAccessTokenAvailable(?string $shopRef = null): bool
    {
        if ($this->isMock()) {
            return true;
        }

        return $this->accessTokenResolver->hasAccessToken($shopRef);
    }

    public function liveCredentialsReady(?string $shopCipher = null, ?string $shopRef = null): bool
    {
        $appKey = (string) config('tiktok_shop.app_key', '');
        $appSecret = (string) config('tiktok_shop.app_secret', '');
        $accessToken = (string) ($this->accessTokenResolver->resolveAccessToken($shopRef) ?? '');
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
     * @return array<string, mixed>|null
     */
    protected function liveOrderDetail(string $orderId, ?string $shopCipherOverride = null, ?string $shopRef = null): ?array
    {
        $appKey = (string) config('tiktok_shop.app_key', '');
        $appSecret = (string) config('tiktok_shop.app_secret', '');
        $shopCipher = filled($shopCipherOverride)
            ? trim((string) $shopCipherOverride)
            : (string) config('tiktok_shop.shop_cipher', '');
        $accessToken = (string) ($this->accessTokenResolver->resolveAccessToken($shopRef) ?? '');

        if ($appKey === '' || $appSecret === '' || $shopCipher === '' || $accessToken === '') {
            Log::info('TikTok Shop getOrderDetail skipped — missing credentials', ['order_id' => $orderId]);

            return null;
        }

        $version = (string) config('tiktok_shop.order_api_version', '202507');
        $path = '/order/'.$version.'/orders/'.$orderId;
        $timestamp = time();

        $query = [
            'access_token' => $accessToken,
            'app_key' => $appKey,
            'shop_cipher' => $shopCipher,
            'timestamp' => (string) $timestamp,
            'ids' => $orderId,
        ];

        $sign = $this->signOpenApiRequest($path, $query, $appSecret);
        $query['sign'] = $sign;

        $base = rtrim((string) config('tiktok_shop.api_base_url'), '/');
        $url = $base.$path;

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->get($url, $query);

            if (! $response->successful()) {
                Log::warning('TikTok Shop getOrderDetail HTTP error', [
                    'order_id' => $orderId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $json = $response->json();
            if (! is_array($json)) {
                return null;
            }

            return $json;
        } catch (\Throwable $e) {
            Log::warning('TikTok Shop getOrderDetail failed', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
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