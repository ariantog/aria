<?php

use App\Models\Jubelioorder;
use App\Models\Transaction;

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

it('skips shipped webhook when sell transaction already exists', function () {
    config(['services.jubelio.webhook_secret' => 'test-secret']);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => '260814A8Y3HDS7',
    ]);

    $body = json_encode([
        'status' => 'SHIPPED',
        'salesorder_id' => 'wh-dup-sell',
        'salesorder_no' => 'SP-260814A8Y3HDS7',
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
        ->assertJson(['status' => 'ok', 'message' => 'Invoice sudah ada']);

    expect(Jubelioorder::where('invoice', 'SP-260814A8Y3HDS7')->exists())->toBeFalse();
});

it('fills warehouse columns from jubelio api when webhook body lacks store location', function () {
    config(['services.jubelio.webhook_secret' => 'test-secret']);

    $warehouse = \App\Models\Addrbook::factory()->warehouse()->create(['name' => 'Gudang Webhook API']);

    \App\Models\Jubeliosync::create([
        'jubelio_store_id' => 441,
        'jubelio_store_name' => 'Tokopedia',
        'jubelio_location_id' => 442,
        'jubelio_location_name' => 'BSD - ONLINE',
        'warehouse_id' => $warehouse->id,
        'customer_id' => 0,
        'bin_id' => 0,
    ]);

    test()->mock(\App\Services\JubelioService::class, function (\Mockery\MockInterface $mock) {
        $mock->shouldReceive('fetchSalesOrder')
            ->once()
            ->with('wh-api-fill')
            ->andReturn([
                'salesorder_no' => 'INV-WH-API-FILL',
                'store_id' => 441,
                'location_id' => 442,
                'location_name' => 'BSD - ONLINE',
            ]);
    });

    $body = json_encode([
        'status' => 'SHIPPED',
        'salesorder_id' => 'wh-api-fill',
        'salesorder_no' => 'INV-WH-API-FILL',
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

    $order = Jubelioorder::where('invoice', 'INV-WH-API-FILL')->first();
    expect($order)->not->toBeNull()
        ->and($order->jubelio_store_id)->toBe(441)
        ->and($order->jubelio_location_id)->toBe(442)
        ->and($order->warehouse_id)->toBe($warehouse->id);
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
