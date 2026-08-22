<?php

namespace App\Services\ShopeeAds;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShopeeAdsApiService
{
    public const OAUTH_SETTING_SLUG = 'shopee_ads_oauth';

    public function isConfigured(): bool
    {
        $config = config('services.shopee_ads');

        return filled($config['partner_id']) && filled($config['partner_key']);
    }

    public function hasShopAuthorization(): bool
    {
        $oauth = $this->getOAuthPayload();

        return filled($oauth['access_token'] ?? null) && filled($oauth['shop_id'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConnectionStatus(): array
    {
        $oauth = $this->getOAuthPayload();
        $expiresAt = isset($oauth['expires_at']) ? Carbon::parse($oauth['expires_at']) : null;

        return [
            'configured' => $this->isConfigured(),
            'has_token' => filled($oauth['access_token'] ?? null),
            'shop_id' => $oauth['shop_id'] ?? null,
            'expires_at' => $expiresAt?->toIso8601String(),
            'is_expired' => $expiresAt?->isPast() ?? true,
            'last_error' => $oauth['last_error'] ?? null,
        ];
    }

    public function buildAuthorizeUrl(): string
    {
        $config = config('services.shopee_ads');
        $path = '/api/v2/shop/auth_partner';
        $timestamp = time();
        $sign = $this->signPublic($path, $timestamp);
        $redirect = $config['redirect_url'] ?? route('shopee-ads.oauth.callback');

        return rtrim($config['base_url'], '/').$path.'?'.http_build_query([
            'partner_id' => (int) $config['partner_id'],
            'timestamp' => $timestamp,
            'sign' => $sign,
            'redirect' => $redirect,
        ]);
    }

  /**
     * @return array<string, mixed>|null
     */
    public function exchangeAuthCode(string $code, int $shopId): ?array
    {
        $path = '/api/v2/auth/token/get';
        $timestamp = time();
        $body = [
            'code' => $code,
            'shop_id' => $shopId,
            'partner_id' => (int) config('services.shopee_ads.partner_id'),
        ];

        $response = $this->postPublic($path, $timestamp, $body);

        if (! $response->successful()) {
            $this->recordOAuthError('Token exchange failed: '.$response->body());

            return null;
        }

        $data = $response->json();
        $payload = $data['response'] ?? $data;

        if (! isset($payload['access_token'])) {
            $this->recordOAuthError('Token exchange missing access_token');

            return null;
        }

        $this->persistOAuth([
            'access_token' => $payload['access_token'],
            'refresh_token' => $payload['refresh_token'] ?? null,
            'shop_id' => $shopId,
            'expires_at' => now()->addSeconds((int) ($payload['expire_in'] ?? 14400))->toIso8601String(),
            'last_error' => null,
        ]);

        return $payload;
    }

    public function refreshAccessToken(): ?string
    {
        $oauth = $this->getOAuthPayload();
        $refreshToken = $oauth['refresh_token'] ?? null;
        $shopId = (int) ($oauth['shop_id'] ?? 0);

        if (! $refreshToken || $shopId <= 0) {
            return null;
        }

        $path = '/api/v2/auth/access_token/get';
        $timestamp = time();
        $body = [
            'refresh_token' => $refreshToken,
            'shop_id' => $shopId,
            'partner_id' => (int) config('services.shopee_ads.partner_id'),
        ];

        $response = $this->postPublic($path, $timestamp, $body);

        if (! $response->successful()) {
            $this->recordOAuthError('Refresh failed: '.$response->body());

            return null;
        }

        $data = $response->json();
        $payload = $data['response'] ?? $data;

        if (! isset($payload['access_token'])) {
            $this->recordOAuthError('Refresh missing access_token');

            return null;
        }

        $this->persistOAuth([
            'access_token' => $payload['access_token'],
            'refresh_token' => $payload['refresh_token'] ?? $refreshToken,
            'shop_id' => $shopId,
            'expires_at' => now()->addSeconds((int) ($payload['expire_in'] ?? 14400))->toIso8601String(),
            'last_error' => null,
        ]);

        return $payload['access_token'];
    }

    public function getAccessToken(): ?string
    {
        $oauth = $this->getOAuthPayload();
        $token = $oauth['access_token'] ?? null;
        $expiresAt = isset($oauth['expires_at']) ? Carbon::parse($oauth['expires_at']) : null;

        if ($token && $expiresAt && $expiresAt->isFuture()) {
            return $token;
        }

        return $this->refreshAccessToken();
    }

    /**
     * Read the campaign's current daily budget from Shopee (live value).
     */
    public function getCurrentBudget(string $adType, string $campaignId): ?int
    {
        $path = $this->budgetReadPath($adType);
        if ($path === null) {
            return null;
        }

        $response = $this->shopGet($path, $this->budgetReadParams($adType, $campaignId));

        if (! $response->successful()) {
            Log::warning('Shopee Ads budget read failed', [
                'ad_type' => $adType,
                'campaign_id' => $campaignId,
                'body' => Str::limit($response->body(), 500),
            ]);

            return null;
        }

        $data = $response->json();
        $responseBody = $data['response'] ?? $data;

        return $this->extractBudgetFromResponse($adType, $responseBody, $campaignId);
    }

    /**
     * Set absolute daily budget on Shopee.
     */
    public function setBudget(string $adType, string $campaignId, int $budget): bool
    {
        $path = $this->budgetWritePath($adType);
        if ($path === null) {
            return false;
        }

        $body = $this->budgetWriteBody($adType, $campaignId, $budget);
        $response = $this->shopPost($path, $body);

        if (! $response->successful()) {
            Log::warning('Shopee Ads budget write failed', [
                'ad_type' => $adType,
                'campaign_id' => $campaignId,
                'budget' => $budget,
                'body' => Str::limit($response->body(), 500),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Add increment to live budget (fixes stale starting-budget bug).
     *
     * @return array{before: int, after: int, applied_increment: int}|null
     */
    public function addBudget(string $adType, string $campaignId, int $incrementIdr, int $dailyMaxBudget): ?array
    {
        $current = $this->getCurrentBudget($adType, $campaignId);

        if ($current === null) {
            return null;
        }

        $after = ShopeeAdsBudgetAllocator::addToBudget($current, $incrementIdr, $dailyMaxBudget);
        $applied = $after - $current;

        if ($applied <= 0) {
            return [
                'before' => $current,
                'after' => $current,
                'applied_increment' => 0,
            ];
        }

        if (! $this->setBudget($adType, $campaignId, $after)) {
            return null;
        }

        return [
            'before' => $current,
            'after' => $after,
            'applied_increment' => $applied,
        ];
    }

    /**
     * @return list<array{campaign_id: string, roas: float, budget: int, status: string}>
     */
    public function listActiveAdGroups(): array
    {
        $path = '/api/v2/ads/get_product_level_campaign_id_list';
        $response = $this->shopGet($path, []);

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json();
        $ids = $data['response']['campaign_id_list'] ?? $data['response']['campaign_ids'] ?? [];

        $groups = [];

        foreach ($ids as $campaignId) {
            $info = $this->getProductLevelCampaignInfo((string) $campaignId);
            if ($info === null) {
                continue;
            }

            $status = strtolower((string) ($info['campaign_status'] ?? $info['status'] ?? 'active'));

            if (in_array($status, ['deleted', 'ended'], true)) {
                continue;
            }

            $groups[] = [
                'campaign_id' => (string) $campaignId,
                'roas' => (float) ($info['roi'] ?? $info['roas'] ?? $info['roas_target'] ?? 0),
                'budget' => (int) round((float) ($info['budget'] ?? $info['daily_budget'] ?? 0)),
                'status' => $status,
            ];
        }

        return $groups;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getProductLevelCampaignInfo(string $campaignId): ?array
    {
        $path = '/api/v2/ads/get_product_level_campaign_setting_info';
        $response = $this->shopGet($path, ['campaign_id' => (int) $campaignId]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return $data['response'] ?? $data;
    }

    /**
     * @return list<array{item_id: int, tag: string}>
     */
    public function getRecommendedItems(): array
    {
        $path = '/api/v2/ads/get_recommended_item_list';
        $response = $this->shopGet($path, []);

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json();
        $items = $data['response']['item_list'] ?? $data['response']['items'] ?? [];

        return collect($items)->map(function ($item) {
            return [
                'item_id' => (int) ($item['item_id'] ?? 0),
                'tag' => (string) ($item['tag'] ?? $item['recommendation_type'] ?? 'recommended'),
            ];
        })->filter(fn ($item) => $item['item_id'] > 0)->values()->all();
    }

    public function turnOffGroupCampaign(string $campaignId): bool
    {
        return $this->setBudget('group', $campaignId, 0);
    }

    public function createManualProductAd(int $itemId, int $budget, float $roasTarget = 0): ?string
    {
        $path = '/api/v2/ads/create_manual_product_ads';
        $body = [
            'reference_id' => Str::uuid()->toString(),
            'budget' => $budget,
            'start_date' => now()->format('d-m-Y'),
            'bidding_method' => 'auto',
            'item_id' => $itemId,
            'roas_target' => $roasTarget,
        ];

        $response = $this->shopPost($path, $body);

        if (! $response->successful()) {
            Log::warning('Shopee create_manual_product_ads failed', ['body' => Str::limit($response->body(), 500)]);

            return null;
        }

        $data = $response->json();
        $responseBody = $data['response'] ?? $data;

        return isset($responseBody['campaign_id']) ? (string) $responseBody['campaign_id'] : null;
    }

    private function budgetReadPath(string $adType): ?string
    {
        return match ($adType) {
            'toko_auto', 'booster' => '/api/v2/ads/get_gms_campaign_performance',
            'toko_manual', 'produk_auto' => '/api/v2/ads/get_product_level_campaign_setting_info',
            'group' => '/api/v2/ads/get_product_level_campaign_setting_info',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function budgetReadParams(string $adType, string $campaignId): array
    {
        if (in_array($adType, ['toko_auto', 'booster'], true)) {
            return [
                'campaign_id' => (int) $campaignId,
                'start_date' => now()->format('d-m-Y'),
                'end_date' => now()->format('d-m-Y'),
            ];
        }

        return ['campaign_id' => (int) $campaignId];
    }

    private function budgetWritePath(string $adType): ?string
    {
        return match ($adType) {
            'toko_auto', 'booster' => '/api/v2/ads/edit_gms_product_campaign',
            'produk_auto' => '/api/v2/ads/edit_auto_product_ads',
            'toko_manual', 'group' => '/api/v2/ads/edit_manual_product_ads',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function budgetWriteBody(string $adType, string $campaignId, int $budget): array
    {
        if (in_array($adType, ['toko_auto', 'booster'], true)) {
            return [
                'campaign_id' => (int) $campaignId,
                'budget' => $budget,
            ];
        }

        if ($adType === 'produk_auto') {
            return [
                'campaign_id' => (int) $campaignId,
                'budget' => $budget,
            ];
        }

        return [
            'campaign_id' => (int) $campaignId,
            'budget' => $budget,
        ];
    }

    private function extractBudgetFromResponse(string $adType, array $responseBody, string $campaignId): ?int
    {
        if (isset($responseBody['budget'])) {
            return (int) round((float) $responseBody['budget']);
        }

        if (isset($responseBody['daily_budget'])) {
            return (int) round((float) $responseBody['daily_budget']);
        }

        if (isset($responseBody['campaign_list'])) {
            foreach ($responseBody['campaign_list'] as $campaign) {
                if ((string) ($campaign['campaign_id'] ?? '') === $campaignId) {
                    return (int) round((float) ($campaign['budget'] ?? $campaign['daily_budget'] ?? 0));
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function shopGet(string $path, array $query = []): Response
    {
        return $this->shopRequest('get', $path, $query);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function shopPost(string $path, array $body = []): Response
    {
        return $this->shopRequest('post', $path, [], $body);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     */
    private function shopRequest(string $method, string $path, array $query = [], array $body = []): Response
    {
        $token = $this->getAccessToken();
        $oauth = $this->getOAuthPayload();
        $shopId = (int) ($oauth['shop_id'] ?? 0);

        if (! $token || $shopId <= 0) {
            return new Response(new \GuzzleHttp\Psr7\Response(401, [], '{"error":"not_authorized"}'));
        }

        $timestamp = time();
        $sign = $this->signShop($path, $timestamp, $token, $shopId);

        $baseQuery = [
            'partner_id' => (int) config('services.shopee_ads.partner_id'),
            'timestamp' => $timestamp,
            'access_token' => $token,
            'shop_id' => $shopId,
            'sign' => $sign,
        ];

        $url = rtrim(config('services.shopee_ads.base_url'), '/').$path;

        $request = Http::timeout(30);

        if ($method === 'get') {
            return $request->get($url, array_merge($baseQuery, $query));
        }

        return $request->post($url.'?'.http_build_query($baseQuery), $body);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function postPublic(string $path, int $timestamp, array $body): Response
    {
        $sign = $this->signPublic($path, $timestamp);
        $url = rtrim(config('services.shopee_ads.base_url'), '/').$path;

        return Http::timeout(30)->post($url.'?'.http_build_query([
            'partner_id' => (int) config('services.shopee_ads.partner_id'),
            'timestamp' => $timestamp,
            'sign' => $sign,
        ]), $body);
    }

    private function signPublic(string $path, int $timestamp): string
    {
        $partnerId = (string) config('services.shopee_ads.partner_id');
        $baseString = $partnerId.$path.$timestamp;

        return hash_hmac('sha256', $baseString, (string) config('services.shopee_ads.partner_key'));
    }

    private function signShop(string $path, int $timestamp, string $accessToken, int $shopId): string
    {
        $partnerId = (string) config('services.shopee_ads.partner_id');
        $baseString = $partnerId.$path.$timestamp.$accessToken.$shopId;

        return hash_hmac('sha256', $baseString, (string) config('services.shopee_ads.partner_key'));
    }

    /**
     * @return array<string, mixed>
     */
    private function getOAuthPayload(): array
    {
        $value = Setting::getValue(self::OAUTH_SETTING_SLUG, []);

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistOAuth(array $payload): void
    {
        Setting::query()->updateOrCreate(
            ['slug' => self::OAUTH_SETTING_SLUG],
            [
                'group' => 'shopee_ads',
                'name' => 'Shopee Ads OAuth',
                'value' => $payload,
            ]
        );
    }

    private function recordOAuthError(string $message): void
    {
        $oauth = $this->getOAuthPayload();
        $oauth['last_error'] = $message;
        $this->persistOAuth($oauth);
        Log::error('Shopee Ads OAuth error: '.$message);
    }
}
