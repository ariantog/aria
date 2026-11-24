<?php

use App\Jobs\PersistShopeePushInboxJob;
use App\Models\MarketplaceOrderShadow;
use App\Models\ShopeePushInbox;
use App\Services\Shopee\ShopeePushSignatureVerifier;
use Illuminate\Support\Facades\Queue;

it('returns 200 before inbox and shadow persist via queued job', function () {
    Queue::fake();

    config([
        'services.shopee_push.partner_key' => 'test-partner-key',
        'services.shopee_push.verify_signature' => true,
        'services.shopee_push.shadow_enabled' => true,
        'services.shopee_push.store_inbox' => true,
    ]);

    $body = '{"data":{"ordersn":"ASYNC1","status":"PROCESSED"},"shop_id":9,"code":3,"timestamp":1}';
    $callbackUrl = url(route('shopee.push', [], false));
    $authorization = app(ShopeePushSignatureVerifier::class)->sign($callbackUrl, $body);

    $this->call('POST', route('shopee.push'), [], [], [], [
        'HTTP_AUTHORIZATION' => $authorization,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    expect(ShopeePushInbox::count())->toBe(0)
        ->and(MarketplaceOrderShadow::count())->toBe(0);

    Queue::assertPushed(PersistShopeePushInboxJob::class, function (PersistShopeePushInboxJob $job): bool {
        $job->handle(
            app(\App\Services\Marketplace\ShopeeOrderStatusIngest::class),
            app(\App\Services\Marketplace\MarketplaceWebhookRecorder::class),
            app(\App\Services\Marketplace\MarketplaceWebhookStatusPresenter::class),
            app(\App\Services\Marketplace\ShopeePushWebhookOnOrderApplicator::class),
        );

        return $job->payload['data']['ordersn'] === 'ASYNC1';
    });

    expect(ShopeePushInbox::count())->toBe(1)
        ->and(MarketplaceOrderShadow::query()->where('external_order_sn', 'ASYNC1')->exists())->toBeTrue();
});