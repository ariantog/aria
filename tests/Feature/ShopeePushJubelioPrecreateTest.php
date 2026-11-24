<?php

use App\Models\Jubelioorder;
use App\Models\Transaction;
use App\Services\Jubelio\JubelioSellInvoiceGuard;
use App\Services\Marketplace\ShopeeJubelioQueuePrecreator;
use App\Services\Shopee\ShopeePushSignatureVerifier;

it('does not create jubelioorders when precreate flag is off', function () {
    config([
        'services.shopee_push.partner_key' => 'test-partner-key',
        'services.shopee_push.verify_signature' => true,
        'services.shopee_push.shadow_enabled' => false,
        'services.shopee_push.store_inbox' => false,
        'services.shopee_push.precreate_jubelio_queue' => false,
    ]);

    $body = '{"data":{"ordersn":"FLAG_OFF_1","status":"PROCESSED","update_time":1660123127},"shop_id":9,"code":3,"timestamp":1660123127}';
    $callbackUrl = url(route('shopee.push', [], false));
    $authorization = app(ShopeePushSignatureVerifier::class)->sign($callbackUrl, $body);

    $this->call('POST', route('shopee.push'), [], [], [], [
        'HTTP_AUTHORIZATION' => $authorization,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    expect(Jubelioorder::where('invoice', 'SP-FLAG_OFF_1')->exists())->toBeFalse();
    expect(Transaction::count())->toBe(0);
});

it('upserts jubelio placeholder on paid status push and is idempotent', function () {
    config([
        'services.shopee_push.partner_key' => 'test-partner-key',
        'services.shopee_push.verify_signature' => true,
        'services.shopee_push.shadow_enabled' => false,
        'services.shopee_push.store_inbox' => false,
        'services.shopee_push.precreate_jubelio_queue' => true,
    ]);

    $body = '{"data":{"ordersn":"PAID_SN_99","status":"PROCESSED","update_time":1660123127},"shop_id":9,"code":3,"timestamp":1660123127}';
    $callbackUrl = url(route('shopee.push', [], false));
    $authorization = app(ShopeePushSignatureVerifier::class)->sign($callbackUrl, $body);

    $headers = [
        'HTTP_AUTHORIZATION' => $authorization,
        'CONTENT_TYPE' => 'application/json',
    ];

    $this->call('POST', route('shopee.push'), [], [], [], $headers, $body)->assertOk();
    $this->call('POST', route('shopee.push'), [], [], [], $headers, $body)->assertOk();

    expect(Jubelioorder::where('invoice', 'SP-PAID_SN_99')->count())->toBe(1);

    $order = Jubelioorder::where('invoice', 'SP-PAID_SN_99')->first();
    expect($order->source)->toBe(Jubelioorder::SOURCE_SHOPEE_PUSH)
        ->and($order->jubelio_order_id)->toBe('shopee-push:PAID_SN_99')
        ->and($order->order_status)->toBe('READY TO SHIP')
        ->and($order->type)->toBe('SELL')
        ->and($order->status)->toBe(0);

    expect(Transaction::count())->toBe(0);
});

it('uses on-order placeholder prefix when shopee on_order flag is on', function () {
    config([
        'services.shopee_push.partner_key' => 'test-partner-key',
        'services.shopee_push.verify_signature' => true,
        'services.shopee_push.shadow_enabled' => false,
        'services.shopee_push.store_inbox' => false,
        'services.shopee_push.precreate_jubelio_queue' => true,
        'marketplace.shopee_on_order.enabled' => true,
    ]);

    $body = '{"data":{"ordersn":"ONORD_SN_1","status":"PROCESSED","update_time":1660123127},"shop_id":9,"code":3,"timestamp":1660123127}';
    $callbackUrl = url(route('shopee.push', [], false));
    $authorization = app(ShopeePushSignatureVerifier::class)->sign($callbackUrl, $body);

    $this->call('POST', route('shopee.push'), [], [], [], [
        'HTTP_AUTHORIZATION' => $authorization,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    $order = Jubelioorder::where('invoice', 'SP-ONORD_SN_1')->first();
    expect($order)->not->toBeNull()
        ->and($order->jubelio_order_id)->toBe('shopee-push-on-order:ONORD_SN_1');
});

it('skips unpaid and console test push for precreate', function () {
    config([
        'services.shopee_push.partner_key' => 'test-partner-key',
        'services.shopee_push.verify_signature' => true,
        'services.shopee_push.shadow_enabled' => false,
        'services.shopee_push.precreate_jubelio_queue' => true,
    ]);

    $callbackUrl = url(route('shopee.push', [], false));

    $unpaidBody = '{"data":{"ordersn":"UNPAID_1","status":"UNPAID"},"shop_id":9,"code":3,"timestamp":1}';
    $authUnpaid = app(ShopeePushSignatureVerifier::class)->sign($callbackUrl, $unpaidBody);
    $this->call('POST', route('shopee.push'), [], [], [], [
        'HTTP_AUTHORIZATION' => $authUnpaid,
        'CONTENT_TYPE' => 'application/json',
    ], $unpaidBody)->assertOk();

    $testBody = '{"data":{"ordersn":"220810QSK8S7BX","status":"PROCESSED"},"shop_id":9,"code":3,"timestamp":1}';
    $authTest = app(ShopeePushSignatureVerifier::class)->sign($callbackUrl, $testBody);
    $this->call('POST', route('shopee.push'), [], [], [], [
        'HTTP_AUTHORIZATION' => $authTest,
        'CONTENT_TYPE' => 'application/json',
    ], $testBody)->assertOk();

    expect(Jubelioorder::where('invoice', 'SP-UNPAID_1')->exists())->toBeFalse()
        ->and(Jubelioorder::where('invoice', 'SP-220810QSK8S7BX')->exists())->toBeFalse();
});

it('sell invoice guard sees shopee pre-row as taken', function () {
    Jubelioorder::create([
        'jubelio_order_id' => 'shopee-push:GUARD-1',
        'source' => Jubelioorder::SOURCE_SHOPEE_PUSH,
        'invoice' => 'SP-GUARD-1',
        'type' => 'SELL',
        'order_status' => 'READY TO SHIP',
        'status' => 0,
    ]);

    $guard = app(JubelioSellInvoiceGuard::class);

    expect($guard->sellInvoiceTaken('SP-GUARD-1'))->toBeTrue()
        ->and($guard->filterInvoicesAlreadyTaken(['SP-GUARD-1', 'SP-NEW']))->toContain('SP-GUARD-1');
});

it('precreator placeholder id follows shopee on_order flag', function () {
    $service = app(ShopeeJubelioQueuePrecreator::class);

    config(['marketplace.shopee_on_order.enabled' => false]);
    expect($service->placeholderJubelioOrderId('SN1'))->toBe('shopee-push:SN1');

    config(['marketplace.shopee_on_order.enabled' => true]);
    expect($service->placeholderJubelioOrderId('SN1'))->toBe('shopee-push-on-order:SN1');
});

it('precreator service respects explicit trigger status list', function () {
    config([
        'services.shopee_push.precreate_trigger_statuses' => ['READY_TO_SHIP'],
    ]);

    $service = app(ShopeeJubelioQueuePrecreator::class);

    expect($service->shouldTriggerForStatus('READY_TO_SHIP'))->toBeTrue()
        ->and($service->shouldTriggerForStatus('PROCESSED'))->toBeFalse();
});