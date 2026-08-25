<?php

use App\Models\ShopeeAdsSetting;
use App\Services\ShopeeAds\ShopeeAdsSpecialRulesService;
use Carbon\Carbon;

beforeEach(function () {
    $this->rules = app(ShopeeAdsSpecialRulesService::class);
});

it('detects double dates when day equals month', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-08 10:00:00', 'Asia/Jakarta'));

    expect($this->rules->isDoubleDate($this->rules->jakartaNow()))->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2026-08-09 10:00:00', 'Asia/Jakarta'));

    expect($this->rules->isDoubleDate($this->rules->jakartaNow()))->toBeFalse();

    Carbon::setTestNow();
});

it('applies double date multipliers when enabled', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-08 10:00:00', 'Asia/Jakarta'));

    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'double_date_enabled' => true,
        'double_date_gmv_multiplier' => 2,
        'double_date_item_ads_multiplier' => 1.5,
        'double_date_item_budget_multiplier' => 2,
    ]);

    $multipliers = $this->rules->resolveForToday($settings);

    expect($multipliers->gmv)->toBe(2.0)
        ->and($multipliers->itemBudget)->toBe(2.0)
        ->and($multipliers->itemAdsCount)->toBe(1.5)
        ->and($multipliers->scaledGmvAmount(100000))->toBe(200000)
        ->and($multipliers->scaledMaxItemAds(10))->toBe(15);

    Carbon::setTestNow();
});

it('stacks payday multipliers with double date', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00', 'Asia/Jakarta'));

    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'double_date_enabled' => true,
        'double_date_gmv_multiplier' => 2,
        'double_date_item_budget_multiplier' => 2,
        'payday_enabled' => true,
        'payday_day' => 25,
        'payday_gmv_multiplier' => 1.5,
        'payday_item_multiplier' => 1.3,
    ]);

    $multipliers = $this->rules->resolveForToday($settings);

    expect($multipliers->gmv)->toBe(2.0)
        ->and($multipliers->itemBudget)->toBe(2.0);

    Carbon::setTestNow(Carbon::parse('2026-01-25 10:00:00', 'Asia/Jakarta'));

    $paydayMultipliers = $this->rules->resolveForToday($settings->fresh());

    expect($paydayMultipliers->gmv)->toBe(1.5)
        ->and($paydayMultipliers->itemBudget)->toBe(1.3);

    Carbon::setTestNow();
});

it('scales daily reset budgets on double date', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-08 00:01:00', 'Asia/Jakarta'));

    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'starting_budget_gmv_max' => 100000,
        'item_ad_starting_budget' => 30000,
        'double_date_enabled' => true,
        'double_date_gmv_multiplier' => 2,
        'double_date_item_budget_multiplier' => 2,
        'gms_campaign_id' => 'gmv-dd',
        'gms_current_budget' => 50000,
    ]);

    \App\Models\ShopeeAdsItemAd::query()->create([
        'campaign_id' => 'item-dd',
        'item_id' => 99,
        'budget' => 10000,
        'status' => 'ongoing',
    ]);

    $api = Mockery::mock(\App\Services\ShopeeAds\ShopeeAdsApiService::class);
    $api->shouldReceive('setGmsBudget')->once()->with('gmv-dd', 200000)->andReturn(true);
    $api->shouldReceive('setItemAdBudget')->once()->with('item-dd', 60000)->andReturn(true);

    $engine = new \App\Services\ShopeeAds\ShopeeAdsEngineService($api, app(ShopeeAdsSpecialRulesService::class));
    $engine->dailyReset($settings);

    expect($settings->fresh()->gms_current_budget)->toBe(200000)
        ->and(\App\Models\ShopeeAdsItemAd::find('item-dd')->budget)->toBe(60000);

    Carbon::setTestNow();
});

it('applies manual budget boost with configured multiplier', function () {
    $settings = ShopeeAdsSetting::current();
    $settings->update([
        'daily_max_budget' => 500000,
        'manual_boost_multiplier' => 1.5,
        'gms_campaign_id' => 'gmv-boost',
        'gms_current_budget' => 100000,
    ]);

    \App\Models\ShopeeAdsItemAd::query()->create([
        'campaign_id' => 'item-boost',
        'item_id' => 88,
        'budget' => 40000,
        'status' => 'ongoing',
    ]);

    $api = Mockery::mock(\App\Services\ShopeeAds\ShopeeAdsApiService::class);
    $api->shouldReceive('setGmsBudget')->once()->with('gmv-boost', 150000)->andReturn(true);
    $api->shouldReceive('listManualProductAds')->andReturn([
        ['campaign_id' => 'item-boost', 'campaign_name' => 'x', 'budget' => 40000.0, 'status' => 'ongoing', 'item_id' => 88],
    ]);
    $api->shouldReceive('setItemAdBudget')->once()->with('item-boost', 60000)->andReturn(true);

    $engine = new \App\Services\ShopeeAds\ShopeeAdsEngineService($api, app(ShopeeAdsSpecialRulesService::class));
    $result = $engine->applyManualBudgetBoost($settings);

    expect($result['gmv'])->toBeTrue()
        ->and($result['items'])->toBe(1)
        ->and($settings->fresh()->gms_current_budget)->toBe(150000)
        ->and(\App\Models\ShopeeAdsItemAd::find('item-boost')->budget)->toBe(60000);
});
