<?php

use App\Actions\Jubelio\ProcessJubelioOrder;
use App\Models\Jubelioorder;
use App\Models\Transaction;
use App\Services\JubelioGetOrdersService;
use App\Services\Jubelio\JubelioOrderSyncStatus;

/**
 * Regression: duplicate queue / duplicate post; completed in-window orders may catch up.
 */
it('does not queue duplicate invoices and queues in-window completed sells', function () {
    config(['services.jubelio.order_queue_max_age_days' => 30]);

    Jubelioorder::create([
        'jubelio_order_id' => 'existing',
        'source' => 2,
        'invoice' => 'SP-ALREADY-QUEUED',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'status' => 0,
    ]);

    $service = app(JubelioGetOrdersService::class);
    $queued = $service->queueEligibleRows([
        [
            'salesorder_id' => 'new-1',
            'salesorder_no' => 'SP-ALREADY-QUEUED',
            'internal_status' => 'SHIPPED',
            'is_canceled' => 'N',
            'transaction_date' => now()->subDay()->toIso8601String(),
        ],
        [
            'salesorder_id' => 'new-2',
            'salesorder_no' => 'SP-COMPLETED-SYNC',
            'internal_status' => 'COMPLETED',
            'channel_status' => 'COMPLETED',
            'is_canceled' => 'N',
            'transaction_date' => now()->subDay()->toIso8601String(),
        ],
    ]);

    expect($queued)->toBe(1)
        ->and(Jubelioorder::where('invoice', 'SP-ALREADY-QUEUED')->count())->toBe(1)
        ->and(Jubelioorder::where('invoice', 'SP-COMPLETED-SYNC')->exists())->toBeTrue();
});

it('does not post duplicate sells from the jubelio cron path', function () {
    config(['services.jubelio.order_queue_max_age_days' => 30]);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SP-CRON-DUP',
    ]);

    $duplicate = Jubelioorder::create([
        'jubelio_order_id' => 'd-1',
        'source' => 2,
        'invoice' => 'SP-CRON-DUP',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'status' => 0,
    ]);

    app(ProcessJubelioOrder::class)->execute($duplicate);

    expect(Transaction::where('invoice', 'SP-CRON-DUP')->count())->toBe(1)
        ->and($duplicate->fresh()->error_type)->toBe(JubelioOrderSyncStatus::ERROR_DUPLICATE);
});

it('does not queue shipped webhook when outside the max age window', function () {
    config(['services.jubelio.webhook_secret' => 'test-secret', 'services.jubelio.order_queue_max_age_days' => 30]);

    $body = json_encode([
        'status' => 'SHIPPED',
        'salesorder_id' => 'wh-old',
        'salesorder_no' => 'SP-WEBHOOK-OLD',
        'transaction_date' => now()->subDays(60)->toDateString(),
    ]);

    $sign = hash_hmac('sha256', trim($body).'test-secret', 'test-secret', false);

    $this->call(
        'POST',
        route('jubelio.webhook.order'),
        [],
        [],
        [],
        ['HTTP_SIGN' => $sign, 'CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertSuccessful()
        ->assertJsonPath('message', 'Outside catch-up window or ineligible Jubelio status.');

    expect(Jubelioorder::where('invoice', 'SP-WEBHOOK-OLD')->exists())->toBeFalse();
});
