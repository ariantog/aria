<?php

use App\Models\Setting;
use App\Services\ShopeeAds\ShopeeAdsApiService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.shopee_ads.partner_id' => '12345',
        'services.shopee_ads.partner_key' => 'test-partner-key',
        'services.shopee_ads.base_url' => 'https://partner.shopeemobile.com',
    ]);

    Setting::query()->updateOrCreate(
        ['slug' => ShopeeAdsApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_ads',
            'name' => 'Shopee Ads OAuth',
            'value' => [
                'access_token' => 'access-test',
                'refresh_token' => 'refresh-test',
                'shop_id' => 888,
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
        ],
    );
});

it('reads GMV Max live budget from get_gms_campaign_performance', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), 'get_gms_campaign_performance')) {
            return Http::response([
                'error' => '',
                'response' => [
                    'campaign_id' => 4242,
                    'daily_budget' => 1_000_000,
                    'report' => ['expense' => 250_000],
                ],
            ]);
        }

        return Http::response(['error' => '', 'response' => []]);
    });

    $api = app(ShopeeAdsApiService::class);

    expect($api->getGmsLiveBudget('4242'))->toBe(1_000_000);
});

it('falls back to campaign setting info when performance has no budget', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), 'get_gms_campaign_performance')) {
            return Http::response([
                'error' => '',
                'response' => [
                    'campaign_id' => 777,
                    'report' => ['expense' => 50_000],
                ],
            ]);
        }

        if (str_contains($request->url(), 'get_product_level_campaign_setting_info')) {
            return Http::response([
                'error' => '',
                'response' => [
                    'campaign_list' => [[
                        'campaign_id' => 777,
                        'gms_info' => ['daily_budget' => 850_000],
                    ]],
                ],
            ]);
        }

        return Http::response(['error' => '', 'response' => []]);
    });

    $api = app(ShopeeAdsApiService::class);

    expect($api->getGmsLiveBudget('777'))->toBe(850_000);
});

it('reads nested GMV Max budget from performance gms_info block', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), 'get_gms_campaign_performance')) {
            return Http::response([
                'error' => '',
                'response' => [
                    'campaign_id' => 5151,
                    'gms_info' => ['daily_budget' => 950_000],
                    'report' => ['expense' => 120_000],
                ],
            ]);
        }

        return Http::response(['error' => '', 'response' => []]);
    });

    $api = app(ShopeeAdsApiService::class);

    expect($api->getGmsLiveBudget('5151'))->toBe(950_000);
});
