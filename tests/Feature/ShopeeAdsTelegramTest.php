<?php

use App\Services\ShopeeAds\ShopeeAdsTelegramNotifier;
use Illuminate\Support\Facades\Http;

it('posts sendMessage to telegram for each configured chat id', function () {
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.chat_ids' => '123,456',
    ]);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true]),
    ]);

    app(ShopeeAdsTelegramNotifier::class)->send('hello *world*');

    Http::assertSentCount(2);
    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
            && $request['parse_mode'] === 'Markdown'
            && $request['text'] === 'hello *world*'
            && in_array($request['chat_id'], ['123', '456'], true);
    });
});

it('does not call telegram when token or chat ids are missing', function () {
    config([
        'services.telegram.bot_token' => null,
        'services.telegram.chat_ids' => null,
    ]);

    Http::fake();

    app(ShopeeAdsTelegramNotifier::class)->send('hello');

    Http::assertNothingSent();
});

it('notifies admins when GMV increment runs from schedule', function () {
    $settings = \App\Models\ShopeeAdsSetting::current();
    $settings->update([
        'starting_budget_gmv_max' => 100,
        'daily_max_budget' => 500000,
        'gms_campaign_id' => 'gmv-1',
        'gms_current_budget' => 100,
        'status' => 'active',
    ]);

    $api = Mockery::mock(\App\Services\ShopeeAds\ShopeeAdsApiService::class);
    $api->shouldReceive('addGmsBudget')
        ->once()
        ->andReturn([
            'before' => 250,
            'after' => 350,
            'applied_increment' => 100,
        ]);

    $telegram = Mockery::mock(\App\Services\ShopeeAds\ShopeeAdsTelegramNotifier::class);
    $telegram->shouldReceive('notifyGmvIncrement')
        ->once()
        ->with('09:00', 250, 350);

    $engine = new \App\Services\ShopeeAds\ShopeeAdsEngineService(
        $api,
        app(\App\Services\ShopeeAds\ShopeeAdsSpecialRulesService::class),
        $telegram,
    );

    expect($engine->applyGmvMaxIncrement($settings, 100, '09:00'))->toBeTrue();
});
