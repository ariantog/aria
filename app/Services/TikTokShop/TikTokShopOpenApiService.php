<?php

namespace App\Services\TikTokShop;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Read-only TikTok Shop Order Open API (Get Order Detail).
 */
class TikTokShopOpenApiService
{
    public function isMock(): bool
    {
        return (string) config('tiktok_shop.driver', 'mock') !== 'live';
    }

    /**
     * @return array<string, mixed>|null Decoded API JSON on success.
     */
    public function getOrderDetail(string $orderId): ?array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            return null;
        }

        if ($this->isMock()) {
            return $this->mockOrderDetail($orderId);
        }

        return $this->liveOrderDetail($orderId);
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
    protected function liveOrderDetail(string $orderId): ?array
    {
        $appKey = (string) config('tiktok_shop.app_key', '');
        $appSecret = (string) config('tiktok_shop.app_secret', '');
        $shopCipher = (string) config('tiktok_shop.shop_cipher', '');
        $accessToken = (string) config('tiktok_shop.access_token', '');

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