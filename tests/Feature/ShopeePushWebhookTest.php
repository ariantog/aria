<?php

use App\Jobs\RecordShopeePushShadowJob;
use App\Models\ShopeePushInbox;
use App\Services\Shopee\ShopeePushSignatureVerifier;
use Illuminate\Support\Facades\Queue;

it('does not dispatch shadow job when shadow is disabled', function () {
    Queue::fake();

    config([
        'services.shopee_push.partner_key' => 'test-partner-key',
        'services.shopee_push.verify_signature' => true,
        'services.shopee_push.shadow_enabled' => false,
        'services.shopee_push.store_inbox' => false,
    ]);

    $body = '{"data":{"ordersn":"OFF123","status":"SHIPPED","update_time":1660123127},"shop_id":9,"code":3,"timestamp":1660123127}';
    $callbackUrl = url(route('shopee.push', [], false));
    $authorization = app(ShopeePushSignatureVerifier::class)->sign($callbackUrl, $body);

    $this->call('POST', route('shopee.push'), [], [], [], [
        'HTTP_AUTHORIZATION' => $authorization,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    Queue::assertNotPushed(RecordShopeePushShadowJob::class);
});

it('dispatches shadow job for order status push', function () {
    Queue::fake();

    config([
        'services.shopee_push.partner_key' => 'test-partner-key',
        'services.shopee_push.verify_signature' => true,
        'services.shopee_push.shadow_enabled' => true,
        'services.shopee_push.store_inbox' => false,
    ]);

    $body = '{"data":{"ordersn":"PUSH123","status":"PROCESSED","update_time":1660123127},"shop_id":9,"code":3,"timestamp":1660123127}';
    $callbackUrl = url(route('shopee.push', [], false));
    $authorization = app(ShopeePushSignatureVerifier::class)->sign($callbackUrl, $body);

    $this->call('POST', route('shopee.push'), [], [], [], [
        'HTTP_AUTHORIZATION' => $authorization,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    Queue::assertPushed(RecordShopeePushShadowJob::class, fn ($job) => $job->orderSn === 'PUSH123');
});

it('accepts a signed shopee push and stores inbox row', function () {
    config([
        'services.shopee_push.partner_key' => 'test-partner-key',
        'services.shopee_push.verify_signature' => true,
        'services.shopee_push.callback_url' => '',
        'services.shopee_push.store_inbox' => true,
    ]);

    $body = '{"data":{"ordersn":"220810QSK8S7BX","status":"PROCESSED","update_time":1660123127},"shop_id":727720655,"code":3,"timestamp":1660123127}';
    $callbackUrl = route('shopee.push', [], false);
    $callbackUrl = url($callbackUrl);

    $verifier = app(ShopeePushSignatureVerifier::class);
    $authorization = $verifier->sign($callbackUrl, $body);

    $this->call(
        'POST',
        route('shopee.push'),
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

    expect(ShopeePushInbox::count())->toBe(1);
    $row = ShopeePushInbox::query()->first();
    expect($row->ordersn)->toBe('220810QSK8S7BX')
        ->and($row->push_code)->toBe(3)
        ->and($row->order_status)->toBe('PROCESSED')
        ->and($row->shop_id)->toBe(727720655);
});

it('rejects push when authorization signature is invalid', function () {
    config([
        'services.shopee_push.partner_key' => 'test-partner-key',
        'services.shopee_push.verify_signature' => true,
    ]);

    $body = '{"shop_id":1,"code":1}';

    $this->call(
        'POST',
        route('shopee.push'),
        [],
        [],
        [],
        [
            'HTTP_AUTHORIZATION' => 'invalid',
            'CONTENT_TYPE' => 'application/json',
        ],
        $body,
    )->assertUnauthorized();

    expect(ShopeePushInbox::count())->toBe(0);
});