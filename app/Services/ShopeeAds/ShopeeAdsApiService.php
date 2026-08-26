<?php

namespace App\Services\ShopeeAds;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Shopee Open Platform v2 ads client — ported from bots/shopee_api.py.
 *
 * Working ad surfaces today: GMV-Max (GMS) campaign + individual manual product ads.
 */
class ShopeeAdsApiService
{
    public const OAUTH_SETTING_SLUG = 'shopee_ads_oauth';

    public const ITEM_AD_MIN_BUDGET = 25000;

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
        $redirect = $config['redirect_url'];

        return rtrim($config['base_url'], '/').$path.'?'.http_build_query([
            'partner_id' => (int) $config['partner_id'],
            'timestamp' => $timestamp,
            'sign' => $sign,
            'redirect' => $redirect,
        ]);
    }

    public function getOAuthRedirectUrl(): string
    {
        return (string) config('services.shopee_ads.redirect_url');
    }

    public function getLastOAuthError(): ?string
    {
        $oauth = $this->getOAuthPayload();

        return isset($oauth['last_error']) ? (string) $oauth['last_error'] : null;
    }

    public function formatOAuthErrorForUser(?string $detail): ?string
    {
        if ($detail === null || $detail === '') {
            return null;
        }

        if (str_contains($detail, 'source_ip_undeclared')) {
            if (preg_match('/Request Source IP \(([^)]+)\)/', $detail, $matches)) {
                return 'IP server ('.$matches[1].') belum di-whitelist. Buka Shopee Open Platform → App list → IP Address Whitelist, tambahkan IP itu, lalu klik Authorize Shopee lagi.';
            }

            return 'IP server belum di-whitelist. Shopee Open Platform → App list → IP Address Whitelist → tambahkan outbound IP server Aria, lalu authorize ulang.';
        }

        return $detail;
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

        $data = $this->parseShopeeResponse($response, 'Token exchange');
        if ($data === null) {
            return null;
        }

        $payload = $data['response'] ?? $data;

        if (! isset($payload['access_token'])) {
            $this->recordOAuthError('Token exchange missing access_token: '.json_encode($data));

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

        $data = $this->parseShopeeResponse($response, 'Token refresh');
        if ($data === null) {
            return null;
        }

        $payload = $data['response'] ?? $data;

        if (! isset($payload['access_token'])) {
            $this->recordOAuthError('Refresh missing access_token: '.json_encode($data));

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
     * @return list<int>
     */
    public function campaignIdList(string $adTypeFilter): array
    {
        $ids = [];
        $offset = 0;
        $limit = 50;

        while (true) {
            $response = $this->shopGet(
                '/api/v2/ads/get_product_level_campaign_id_list',
                ['ad_type' => $adTypeFilter, 'offset' => $offset, 'limit' => $limit],
            );

            if (! $response->successful()) {
                break;
            }

            $resp = $response->json('response') ?? [];
            $batch = $resp['campaign_list'] ?? $resp['campaign_id_list'] ?? [];

            foreach ($batch as $entry) {
                $cid = is_array($entry) ? ($entry['campaign_id'] ?? null) : $entry;
                if ($cid !== null) {
                    $ids[] = (int) $cid;
                }
            }

            if (empty($batch) || ! ($resp['has_next_page'] ?? false)) {
                break;
            }

            $offset += $limit;
        }

        return $ids;
    }

    /**
     * @param  list<int|string>  $campaignIds
     * @return list<array{campaign_id: string, campaign_name: string, budget: float, status: string, item_id: int, raw: array}>
     */
    public function campaignSettingInfo(array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }

        $out = [];
        $ids = array_map('intval', $campaignIds);

        for ($i = 0; $i < count($ids); $i += 100) {
            $chunk = array_slice($ids, $i, 100);
            $response = $this->shopGet(
                '/api/v2/ads/get_product_level_campaign_setting_info',
                [
                    'info_type_list' => '1,2,3',
                    'campaign_id_list' => implode(',', $chunk),
                ],
            );

            if (! $response->successful()) {
                continue;
            }

            $resp = $response->json('response') ?? [];
            foreach ($resp['campaign_list'] ?? [] as $c) {
                $common = $c['common_info'] ?? [];
                $budgetInfo = $c['manual_bidding_info'] ?? $c['auto_bidding_info'] ?? [];
                $itemIds = $common['item_id_list'] ?? $c['item_id_list'] ?? [];
                $itemId = 0;
                if (is_array($itemIds) && $itemIds !== []) {
                    $first = $itemIds[0];
                    $itemId = is_array($first) ? (int) ($first['item_id'] ?? 0) : (int) $first;
                }

                $out[] = [
                    'campaign_id' => (string) ($c['campaign_id'] ?? ''),
                    'campaign_name' => (string) ($common['ad_name'] ?? $c['ad_name'] ?? 'Campaign '.($c['campaign_id'] ?? '')),
                    'budget' => (float) (
                        $budgetInfo['campaign_budget']
                        ?? $common['campaign_budget']
                        ?? $c['campaign_budget']
                        ?? 0
                    ),
                    'status' => strtolower((string) ($common['campaign_status'] ?? $c['campaign_status'] ?? '')),
                    'item_id' => $itemId,
                    'raw' => $c,
                ];
            }
        }

        return $out;
    }

    public function getCampaignLiveBudget(string $campaignId): ?int
    {
        $infos = $this->campaignSettingInfo([(int) $campaignId]);

        if ($infos === []) {
            return null;
        }

        $budget = (float) ($infos[0]['budget'] ?? 0);

        return $budget > 0 ? (int) round($budget) : null;
    }

    /**
     * @return array{campaign_id: string, roas: float, expense: float}|null
     */
    public function getGmsCampaign(int $daysBack = 0): ?array
    {
        [$start, $end] = $this->wibDateRange($daysBack);
        $response = $this->shopPost(
            '/api/v2/ads/get_gms_campaign_performance',
            ['start_date' => $start, 'end_date' => $end],
        );

        if (! $response->successful()) {
            return null;
        }

        $resp = $response->json('response') ?? [];
        $cid = $resp['campaign_id'] ?? null;

        if (! $cid) {
            return null;
        }

        $rep = $resp['report'] ?? [];

        return [
            'campaign_id' => (string) $cid,
            'roas' => (float) ($rep['broad_roi'] ?? $rep['roas'] ?? 0),
            'expense' => (float) ($rep['expense'] ?? 0),
        ];
    }

    public function setGmsBudget(string $campaignId, int $dailyBudget): bool
    {
        $response = $this->shopPost(
            '/api/v2/ads/edit_gms_product_campaign',
            [
                'campaign_id' => (int) $campaignId,
                'edit_action' => 'change_budget',
                'daily_budget' => round($dailyBudget, 2),
                'reference_id' => Str::uuid()->toString(),
            ],
        );

        return $response->successful();
    }

    /**
     * @return list<array{campaign_id: string, campaign_name: string, budget: float, status: string, item_id: int}>
     */
    public function listManualProductAds(bool $onlyActive = true): array
    {
        $ids = $this->campaignIdList('manual');
        $infos = $this->campaignSettingInfo($ids);
        $activeStatuses = ['', 'ongoing', 'running', 'active', 'scheduled'];

        return collect($infos)
            ->filter(function ($c) use ($onlyActive, $activeStatuses) {
                return ! $onlyActive || in_array($c['status'], $activeStatuses, true);
            })
            ->map(fn ($c) => [
                'campaign_id' => $c['campaign_id'],
                'campaign_name' => $c['campaign_name'],
                'budget' => $c['budget'],
                'status' => $c['status'],
                'item_id' => $c['item_id'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int|string>  $campaignIds
     * @return array<string, float>
     */
    public function getItemAdsRoas(array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }

        $today = $this->wibToday();
        $roas = [];
        $ids = array_map('intval', $campaignIds);

        for ($i = 0; $i < count($ids); $i += 100) {
            $chunk = array_slice($ids, $i, 100);
            $response = $this->shopGet(
                '/api/v2/ads/get_product_campaign_daily_performance',
                [
                    'start_date' => $today,
                    'end_date' => $today,
                    'campaign_id_list' => implode(',', $chunk),
                ],
            );

            if (! $response->successful()) {
                continue;
            }

            foreach ($response->json('response.campaign_list') ?? [] as $row) {
                $cid = (string) ($row['campaign_id'] ?? '');
                $rep = $row['report'] ?? $row;
                $roas[$cid] = (float) ($rep['broad_roi'] ?? $rep['roas'] ?? 0);
            }
        }

        return $roas;
    }

    public function setItemAdBudget(string $campaignId, int $newBudget): bool
    {
        $response = $this->shopPost(
            '/api/v2/ads/edit_manual_product_ads',
            [
                'reference_id' => $campaignId,
                'campaign_id' => (int) $campaignId,
                'edit_action' => 'change_budget',
                'budget' => round($newBudget, 2),
            ],
        );

        return $response->successful();
    }

    public function stopItemAd(string $campaignId): bool
    {
        $response = $this->shopPost(
            '/api/v2/ads/edit_manual_product_ads',
            [
                'reference_id' => $campaignId,
                'campaign_id' => (int) $campaignId,
                'edit_action' => 'stop',
            ],
        );

        return $response->successful();
    }

    public function createManualProductAd(int $itemId, int $budget, float $roasTarget = 0): ?string
    {
        $body = [
            'reference_id' => 'item-'.$itemId.'-'.Str::random(12),
            'budget' => max($budget, self::ITEM_AD_MIN_BUDGET),
            'start_date' => $this->wibToday(),
            'bidding_method' => 'auto',
            'item_id' => $itemId,
        ];

        if ($roasTarget > 0) {
            $body['roas_target'] = round($roasTarget, 1);
        }

        $response = $this->shopPost('/api/v2/ads/create_manual_product_ads', $body);

        if (! $response->successful()) {
            Log::warning('create_manual_product_ads failed', ['body' => Str::limit($response->body(), 500)]);

            return null;
        }

        $cid = $response->json('response.campaign_id');

        return $cid ? (string) $cid : null;
    }

    /**
     * @return list<array{item_id: int, sku_tags: list<string>}>
     */
    public function getRecommendedItems(): array
    {
        $response = $this->shopGet('/api/v2/ads/get_recommended_item_list');

        if (! $response->successful()) {
            return [];
        }

        $resp = $response->json('response');
        if (is_array($resp) && isset($resp['item_list'])) {
            $resp = $resp['item_list'];
        }

        if (! is_array($resp)) {
            return [];
        }

        return collect($resp)
            ->map(function ($it) {
                return [
                    'item_id' => (int) ($it['item_id'] ?? 0),
                    'sku_tags' => array_map('strtolower', $it['sku_tag_list'] ?? []),
                ];
            })
            ->filter(fn ($it) => $it['item_id'] > 0)
            ->values()
            ->all();
    }

    /**
     * Add increment on top of live Shopee budget (not stale DB / starting budget).
     *
     * @return array{before: int, after: int, applied_increment: int}|null
     */
    public function addItemAdBudget(string $campaignId, int $incrementIdr, int $perAdCap): ?array
    {
        $current = $this->getCampaignLiveBudget($campaignId);

        if ($current === null) {
            $live = collect($this->listManualProductAds())->firstWhere('campaign_id', $campaignId);
            $current = $live ? (int) round((float) $live['budget']) : null;
        }

        if ($current === null) {
            return null;
        }

        return $this->applyIncrement($current, $incrementIdr, $perAdCap, function (int $after) use ($campaignId) {
            return $this->setItemAdBudget($campaignId, $after);
        });
    }

    /**
     * @return array{before: int, after: int, applied_increment: int}|null
     */
    public function addGmsBudget(string $campaignId, int $trackedBudget, int $incrementIdr, int $combinedCap): ?array
    {
        $live = $this->getCampaignLiveBudget($campaignId);
        $current = $live ?? $trackedBudget;

        if ($current <= 0) {
            return null;
        }

        $perCampaignCap = $current + max(0, $combinedCap - $current);

        return $this->applyIncrement($current, $incrementIdr, $perCampaignCap, function (int $after) use ($campaignId) {
            return $this->setGmsBudget($campaignId, $after);
        });
    }

    /**
     * @return array{before: int, after: int, applied_increment: int}
     */
    private function applyIncrement(int $current, int $incrementIdr, int $cap, callable $setter): array
    {
        $after = ShopeeAdsBudgetAllocator::addToBudget($current, $incrementIdr, $cap);
        $applied = $after - $current;

        if ($applied > 0) {
            $setter($after);
        }

        return [
            'before' => $current,
            'after' => $after,
            'applied_increment' => $applied,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function wibDateRange(int $daysBack = 0): array
    {
        $end = Carbon::now('Asia/Jakarta');
        $start = $end->copy()->subDays(max(0, $daysBack));

        return [$start->format('d-m-Y'), $end->format('d-m-Y')];
    }

    private function wibToday(): string
    {
        return Carbon::now('Asia/Jakarta')->format('d-m-Y');
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

    /**
     * Shopee often returns HTTP 200 with a non-empty `error` field on failure.
     *
     * @return array<string, mixed>|null
     */
    private function parseShopeeResponse(Response $response, string $context): ?array
    {
        if (! $response->successful()) {
            $this->recordOAuthError($context.' failed (HTTP '.$response->status().'): '.$response->body());

            return null;
        }

        $data = $response->json();
        if (! is_array($data)) {
            $this->recordOAuthError($context.' returned invalid JSON: '.$response->body());

            return null;
        }

        $error = trim((string) ($data['error'] ?? ''));
        if ($error !== '') {
            $message = trim((string) ($data['message'] ?? ''));
            $detail = $message !== '' ? "{$error} — {$message}" : $error;
            $requestId = trim((string) ($data['request_id'] ?? ''));
            if ($requestId !== '') {
                $detail .= " (request_id: {$requestId})";
            }
            $this->recordOAuthError($context.' Shopee API error: '.$detail);

            return null;
        }

        return $data;
    }
}
