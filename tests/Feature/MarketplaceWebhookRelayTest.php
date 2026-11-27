<?php

use App\Jobs\PersistShopeePushInboxJob;
use App\Jobs\PersistTikTokShopWebhookInboxJob;
use App\Services\Shopee\ShopeePushSignatureVerifier;
use App\Services\TikTokShop\TikTokShopWebhookSignatureVerifier;
use Illuminate\Support\Facades\Queue;

it('returns 404 for shopee relay when relay is disabled', function () {
    config(['services.shopee_push.relay.enabled' => false]);

    $this->post(route('shopee.push.relay'), [], [
        'Authorization' => 'Bearer secret',
    ])->assertNotFound();
});

it('rejects shopee relay when bearer is configured and invalid', function () {
    Queue::fake();

    config([
        'services.shopee_push.relay.enabled' => true,
        'services.shopee_push.relay.bearer' => 'relay-secret',
        'services.shopee_push.partner_key' => 'test-partner-key',
        'services.shopee_push.verify_signature' => true,
    ]);

    $body = '{"shop_id":1,"code":0,"timestamp":1}';
    $callbackUrl = 'https://cdn.example/shopeepush.php';
    $platformAuth = app(ShopeePushSignatureVerifier::class)->sign($callbackUrl, $body);

    $this->call('POST', route('shopee.push.relay'), [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer wrong',
        'HTTP_X_SHOPEE_PUSH_AUTHORIZATION' => $platformAuth,
        'HTTP_X_SHOPEE_PUSH_CALLBACK_URL' => $callbackUrl,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertUnauthorized();

    Queue::assertNothingPushed();
});

it('accepts shopee relay with bearer and dispatches persist job', function () {
    Queue::fake();

    config([
        'services.shopee_push.relay.enabled' => true,
        'services.shopee_push.relay.bearer' => 'relay-secret',
        'services.shopee_push.partner_key' => 'test-partner-key',
        'services.shopee_push.verify_signature' => true,
        'services.shopee_push.callback_url' => 'https://cdn.example/shopeepush.php',
    ]);

    $body = '{"data":{"ordersn":"RELAY1","status":"PROCESSED"},"shop_id":9,"code":3,"timestamp":1}';
    $callbackUrl = 'https://cdn.example/shopeepush.php';
    $platformAuth = app(ShopeePushSignatureVerifier::class)->sign($callbackUrl, $body);

    $this->call('POST', route('shopee.push.relay'), [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer relay-secret',
        'HTTP_X_SHOPEE_PUSH_AUTHORIZATION' => $platformAuth,
        'HTTP_X_SHOPEE_PUSH_CALLBACK_URL' => $callbackUrl,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk()->assertContent('');

    Queue::assertPushed(PersistShopeePushInboxJob::class, function (PersistShopeePushInboxJob $job) use ($callbackUrl): bool {
        return ($job->payload['data']['ordersn'] ?? null) === 'RELAY1'
            && $job->callbackUrl === $callbackUrl;
    });
});

it('rejects tiktok relay when bearer is configured and invalid', function () {
    Queue::fake();

    config([
        'tiktok_shop.webhook.relay.enabled' => true,
        'tiktok_shop.webhook.relay.bearer' => 'relay-secret',
        'tiktok_shop.webhook.enabled' => true,
        'tiktok_shop.app_key' => 'test-app-key',
        'tiktok_shop.app_secret' => 'test-app-secret',
        'tiktok_shop.webhook.verify_signature' => true,
    ]);

    $body = '{"type":1,"tts_notification_id":"n1","data":{"order_id":"1"}}';
    $platformAuth = app(TikTokShopWebhookSignatureVerifier::class)->sign($body);

    $this->call('POST', route('tiktok-shop.webhook.relay'), [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer bad',
        'HTTP_X_TIKTOK_SHOP_AUTHORIZATION' => $platformAuth,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertUnauthorized();

    Queue::assertNothingPushed();
});

it('accepts tiktok relay with bearer and dispatches persist job', function () {
    Queue::fake();

    config([
        'tiktok_shop.webhook.relay.enabled' => true,
        'tiktok_shop.webhook.relay.bearer' => 'relay-secret',
        'tiktok_shop.webhook.enabled' => true,
        'tiktok_shop.app_key' => 'test-app-key',
        'tiktok_shop.app_secret' => 'test-app-secret',
        'tiktok_shop.webhook.verify_signature' => true,
    ]);

    $body = '{"type":1,"tts_notification_id":"relay-n1","data":{"order_id":"99"}}';
    $platformAuth = app(TikTokShopWebhookSignatureVerifier::class)->sign($body);

    $this->call('POST', route('tiktok-shop.webhook.relay'), [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer relay-secret',
        'HTTP_X_TIKTOK_SHOP_AUTHORIZATION' => $platformAuth,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    Queue::assertPushed(PersistTikTokShopWebhookInboxJob::class, function (PersistTikTokShopWebhookInboxJob $job): bool {
        return ($job->payload['tts_notification_id'] ?? null) === 'relay-n1';
    });
});