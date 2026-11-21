<?php

use App\Models\TikTokShopWebhookInbox;
use App\Services\TikTokShop\TikTokShopWebhookSignatureVerifier;

/**
 * Official doc sample (app_key=abcdef, app_secret=123) — compact JSON matches signature base string.
 */
function ttsDocSampleWebhookBody(): string
{
    return '{"type":1,"tts_notification_id":"7380066284010030890","shop_id":"7495540735365777507","timestamp":1718305585,"data":{"is_on_hold_order":true,"order_id":"576653688135258178","order_status":"UNPAID","update_time":1718305585}}';
}

beforeEach(function () {
    config([
        'tiktok_shop.app_key' => 'abcdef',
        'tiktok_shop.app_secret' => '123',
        'tiktok_shop.webhook.enabled' => true,
        'tiktok_shop.webhook.verify_signature' => true,
        'tiktok_shop.webhook.store_inbox' => true,
    ]);
});

it('accepts a signed tiktok shop webhook and stores inbox row', function () {
    $body = ttsDocSampleWebhookBody();
    $authorization = app(TikTokShopWebhookSignatureVerifier::class)->sign($body);

    $this->call(
        'POST',
        route('tiktok-shop.webhook'),
        [],
        [],
        [],
        [
            'HTTP_AUTHORIZATION' => $authorization,
            'CONTENT_TYPE' => 'application/json',
        ],
        $body,
    )
        ->assertOk()
        ->assertContent('');

    expect(TikTokShopWebhookInbox::count())->toBe(1);
    $row = TikTokShopWebhookInbox::query()->first();
    expect($row->tts_notification_id)->toBe('7380066284010030890')
        ->and($row->event_type)->toBe(1)
        ->and($row->order_id)->toBe('576653688135258178')
        ->and($row->order_status)->toBe('UNPAID')
        ->and($row->shop_id)->toBe('7495540735365777507');
});

it('rejects tiktok shop webhook when authorization signature is invalid', function () {
    $body = ttsDocSampleWebhookBody();

    $this->call(
        'POST',
        route('tiktok-shop.webhook'),
        [],
        [],
        [],
        [
            'HTTP_AUTHORIZATION' => 'deadbeef',
            'CONTENT_TYPE' => 'application/json',
        ],
        $body,
    )->assertUnauthorized();

    expect(TikTokShopWebhookInbox::count())->toBe(0);
});

it('is idempotent on duplicate tts_notification_id', function () {
    $body = ttsDocSampleWebhookBody();
    $authorization = app(TikTokShopWebhookSignatureVerifier::class)->sign($body);

    $headers = [
        'HTTP_AUTHORIZATION' => $authorization,
        'CONTENT_TYPE' => 'application/json',
    ];

    $this->call('POST', route('tiktok-shop.webhook'), [], [], [], $headers, $body)->assertOk();
    $this->call('POST', route('tiktok-shop.webhook'), [], [], [], $headers, $body)->assertOk();

    expect(TikTokShopWebhookInbox::count())->toBe(1);
});

it('matches the partner center documentation hmac example', function () {
    $expected = '5dec0f11ec2f6783b8deee53c9ffbf8d024302f7c7e7fa55a35d17629031ac05';
    $body = ttsDocSampleWebhookBody();

    expect(app(TikTokShopWebhookSignatureVerifier::class)->sign($body))->toBe($expected);
});

it('responds 503 when webhooks are disabled by feature flag', function () {
    config(['tiktok_shop.webhook.enabled' => false]);

    $body = ttsDocSampleWebhookBody();
    $authorization = app(TikTokShopWebhookSignatureVerifier::class)->sign($body);

    $this->call(
        'POST',
        route('tiktok-shop.webhook'),
        [],
        [],
        [],
        [
            'HTTP_AUTHORIZATION' => $authorization,
            'CONTENT_TYPE' => 'application/json',
        ],
        $body,
    )->assertStatus(503);

    expect(TikTokShopWebhookInbox::count())->toBe(0);
});