<?php

use App\Models\Jubelioorder;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ProcessJubelioOrderJob;

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

it('dispatches queue job when jubelio webhook auto process is enabled', function () {
    config([
        'services.jubelio.webhook_secret' => 'test-secret',
        'services.jubelio.webhook_auto_process' => true,
    ]);

    Queue::fake();

    $body = json_encode([
        'status' => 'SHIPPED',
        'salesorder_id' => 'wh-queue-1',
        'salesorder_no' => 'INV-QUEUE-TEST',
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

    $order = Jubelioorder::where('invoice', 'INV-QUEUE-TEST')->first();
    expect($order)->not->toBeNull();

    Queue::assertPushed(ProcessJubelioOrderJob::class, fn ($job) => $job->jubelioOrderId === $order->id);
});

it('does not dispatch queue job when jubelio webhook auto process is disabled', function () {
    config([
        'services.jubelio.webhook_secret' => 'test-secret',
        'services.jubelio.webhook_auto_process' => false,
    ]);

    Queue::fake();

    $body = json_encode([
        'status' => 'SHIPPED',
        'salesorder_id' => 'wh-noqueue-1',
        'salesorder_no' => 'INV-NOQUEUE-TEST',
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

    Queue::assertNothingPushed();
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
