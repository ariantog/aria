<?php

use App\Models\Item;
use App\Models\ShopeeAdsItemPerformanceSnapshot;
use App\Models\ShopeeAdsSetting;
use App\Models\User;
use App\Services\ShopeeAds\ShopeeAdsApiService;
use App\Services\ShopeeAds\ShopeeAdsEngineService;
use App\Services\ShopeeAds\ShopeeAdsSpecialRulesService;
use App\Services\ShopeeAds\ShopeeAdsTelegramNotifier;

function mockGroupSuggestionApi(array $recommended = [], array $gmsPerf = []): ShopeeAdsApiService
{
    $api = Mockery::mock(ShopeeAdsApiService::class);
    $api->shouldReceive('listManualProductAds')->andReturn([]);
    $api->shouldReceive('getRecommendedItems')->andReturn($recommended);
    $api->shouldReceive('getGmsItemPerformance')->andReturn($gmsPerf);

    return $api;
}

function groupSuggestionEngine(ShopeeAdsApiService $api): ShopeeAdsEngineService
{
    return new ShopeeAdsEngineService(
        $api,
        app(ShopeeAdsSpecialRulesService::class),
        Mockery::mock(ShopeeAdsTelegramNotifier::class)->shouldIgnoreMissing(),
    );
}

it('ranks group suggestions by ROAS history and enriches local item name/code', function () {
    $item = Item::factory()->create([
        'name' => 'Kaos Premium',
        'code' => 'KP-001',
        'jubelio_item_id' => 6601,
    ]);

    ShopeeAdsItemPerformanceSnapshot::query()->create([
        'item_id' => 6601,
        'snapshot_date' => now()->toDateString(),
        'roas' => 7.2,
        'spend' => 5000,
        'budget' => 25000,
    ]);

    $engine = groupSuggestionEngine(mockGroupSuggestionApi());

    $suggestions = $engine->suggestGroupAds(ShopeeAdsSetting::current(), 5, 'roas');

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]['item_id'])->toBe(6601)
        ->and($suggestions[0]['source'])->toBe('performance_history')
        ->and($suggestions[0]['item_name'])->toBe('Kaos Premium')
        ->and($suggestions[0]['item_code'])->toBe('KP-001')
        ->and($suggestions[0]['aria_item_id'])->toBe($item->id);
});

it('ranks group suggestions by GMS sales when strategy is sales', function () {
    Item::factory()->create([
        'name' => 'Best Seller',
        'code' => 'BS-99',
        'jubelio_item_id' => 7701,
    ]);

    $engine = groupSuggestionEngine(mockGroupSuggestionApi(gmsPerf: [
        [
            'item_id' => 7701,
            'roas' => 4.5,
            'expense' => 10000,
            'gmv' => 250000,
            'clicks' => 10,
            'impression' => 100,
            'orders' => 12,
        ],
    ]));

    $suggestions = $engine->suggestGroupAds(ShopeeAdsSetting::current(), 5, 'sales');

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]['source'])->toBe('gms_sales')
        ->and($suggestions[0]['sales_score'])->toBeGreaterThan(0)
        ->and($suggestions[0]['item_name'])->toBe('Best Seller');
});

it('labels Shopee recommended tags by ROI/selling/search instead of generic recommended', function () {
    $engine = groupSuggestionEngine(mockGroupSuggestionApi(recommended: [
        [
            'item_id' => 8801,
            'sku_tags' => ['best roi'],
            'item_status' => ['normal'],
            'ongoing_ad_types' => [],
        ],
        [
            'item_id' => 8802,
            'sku_tags' => ['best selling'],
            'item_status' => ['normal'],
            'ongoing_ad_types' => [],
        ],
    ]));

    $suggestions = $engine->suggestGroupAds(ShopeeAdsSetting::current(), 5, 'recommended');

    expect($suggestions)->toHaveCount(2)
        ->and($suggestions[0]['source'])->toBe('best_roi')
        ->and($suggestions[1]['source'])->toBe('best_selling');
});

it('posts group suggestions with selected strategy from the shopee ads page', function () {
    $user = User::factory()->create();

    ShopeeAdsItemPerformanceSnapshot::query()->create([
        'item_id' => 9901,
        'snapshot_date' => now()->toDateString(),
        'roas' => 8.0,
        'spend' => 3000,
        'budget' => 25000,
    ]);

    Item::factory()->create([
        'name' => 'Snapshot Item',
        'code' => 'SI-01',
        'jubelio_item_id' => 9901,
    ]);

    $this->mock(ShopeeAdsApiService::class, function ($mock) {
        $mock->shouldReceive('getConnectionStatus')->andReturn([
            'has_token' => true,
            'is_expired' => false,
            'shop_id' => 123,
            'last_error' => null,
        ]);
        $mock->shouldReceive('hasShopAuthorization')->andReturn(true);
        $mock->shouldReceive('formatOAuthErrorForUser')->andReturn(null);
        $mock->shouldReceive('getLastOAuthError')->andReturn(null);
        $mock->shouldReceive('listManualProductAds')->andReturn([]);
        $mock->shouldReceive('getRecommendedItems')->andReturn([]);
        $mock->shouldReceive('getGmsItemPerformance')->andReturn([]);
    });

    $response = $this->actingAs($user)
        ->post(route('shopee-ads.suggest-group-ads'), ['strategy' => 'roas']);

    $response->assertRedirect()
        ->assertSessionHas('group_ad_suggestions')
        ->assertSessionHas('group_ad_strategy', 'roas');

    $suggestions = $response->getSession()->get('group_ad_suggestions');
    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]['item_name'])->toBe('Snapshot Item');
});
