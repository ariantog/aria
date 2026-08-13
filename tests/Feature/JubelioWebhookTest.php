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

it('does not create duplicate shipped webhook when order already processed', function () {
    config(['services.jubelio.webhook_secret' => 'test-secret']);

    Jubelioorder::create([
        'jubelio_order_id' => 'dup-1',
        'source' => 1,
        'invoice' => 'INV-DUP-TEST',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 1,
        'payload' => '{}',
        'status' => 2,
        'error_type' => 10,
    ]);

    $body = json_encode([
        'status' => 'SHIPPED',
        'salesorder_id' => 'dup-1',
        'salesorder_no' => 'INV-DUP-TEST',
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
        ->assertJson(['message' => 'Already exists']);

    expect(Jubelioorder::where('invoice', 'INV-DUP-TEST')->where('status', 0)->exists())->toBeFalse();
});

it('resets processed order to pending when webhook is forwarded from production', function () {
    config(['services.jubelio.webhook_secret' => 'test-secret']);

    $existing = Jubelioorder::create([
        'jubelio_order_id' => 'fwd-1',
        'source' => 1,
        'invoice' => 'INV-FWD-TEST',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 1,
        'payload' => '{"old":true}',
        'status' => 2,
        'error_type' => 10,
    ]);

    $body = json_encode([
        'status' => 'SHIPPED',
        'salesorder_id' => 'fwd-1',
        'salesorder_no' => 'INV-FWD-TEST',
        'transaction_date' => '2026-05-10',
        'source_name' => 'Tokopedia',
    ]);
    $sign = jubelioWebhookSign($body, 'test-secret');

    $this->call(
        'POST',
        route('jubelio.webhook.order'),
        [],
        [],
        [],
        [
            'HTTP_SIGN' => $sign,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Jubelio-Forwarded-From' => 'production',
        ],
        $body,
    )->assertSuccessful()
        ->assertJson(['message' => 'Reset to pending']);

    $existing->refresh();
    expect($existing->status)->toBe(0);
    expect($existing->run_count)->toBe(0);
    expect($existing->payloadArray())->toHaveKey('source_name', 'Tokopedia');
});
