<?php

use App\Models\Jubelioorder;

function jubelioWebhookSign(string $body, string $secret): string
{
    return hash_hmac('sha256', trim($body).$secret, $secret, false);
}

it('rejects jubelio webhook without valid signature', function () {
    config(['services.jubelio.webhook_secret' => 'test-secret']);

    $body = json_encode([
        'status' => 'SHIPPED',
        'salesorder_id' => 1,
        'salesorder_no' => 'INV-001',
        'transaction_date' => '2026-05-10',
    ]);

    $this->call(
        'POST',
        route('jubelio.webhook.order'),
        [],
        [],
        [],
        ['HTTP_SIGN' => 'invalid', 'CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertForbidden();
});

it('accepts jubelio webhook with valid signature and stores shipped order', function () {
    config(['services.jubelio.webhook_secret' => 'test-secret']);

    $body = json_encode([
        'status' => 'SHIPPED',
        'salesorder_id' => 'wh-99901',
        'salesorder_no' => 'INV-WEBHOOK-TEST',
        'transaction_date' => '2026-05-10',
    ]);

    $sign = jubelioWebhookSign($body, 'test-secret');

    $this->call(
        'POST',
        route('jubelio.webhook.order'),
        [],
        [],
        [],
        ['HTTP_SIGN' => $sign, 'CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertSuccessful()
        ->assertJson(['status' => 'ok']);

    expect(Jubelioorder::where('invoice', 'INV-WEBHOOK-TEST')->exists())->toBeTrue();

    $order = Jubelioorder::where('invoice', 'INV-WEBHOOK-TEST')->first();
    expect($order->payload)->toBeNull();
});

it('leaves shipped webhook orders pending for cron processing', function () {
    config(['services.jubelio.webhook_secret' => 'test-secret']);

    $body = json_encode([
        'status' => 'SHIPPED',
        'salesorder_id' => 'wh-cron-1',
        'salesorder_no' => 'INV-CRON-TEST',
        'transaction_date' => '2026-05-10',
    ]);

    $sign = jubelioWebhookSign($body, 'test-secret');

    $this->call(
        'POST',
        route('jubelio.webhook.order'),
        [],
        [],
        [],
        ['HTTP_SIGN' => $sign, 'CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertSuccessful();

    $order = Jubelioorder::where('invoice', 'INV-CRON-TEST')->first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe(0);
    expect($order->run_count)->toBe(0);
});

it('allows jubelio webhook without authentication session', function () {
    config(['services.jubelio.webhook_secret' => 'test-secret']);

    $body = json_encode([
        'status' => 'UNKNOWN',
    ]);
    $sign = jubelioWebhookSign($body, 'test-secret');

    $this->call(
        'POST',
        route('jubelio.webhook.order'),
        [],
        [],
        [],
        ['HTTP_SIGN' => $sign, 'CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertSuccessful();
});
