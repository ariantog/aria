<?php

use App\Actions\Jubelio\ProcessJubelioOrder;
use App\Models\Jubelioorder;
use App\Models\Transaction;
use App\Services\JubelioGetOrdersService;
use App\Services\Jubelio\JubelioOrderSyncStatus;

/**
 * Regression: legacy get-orders bug (COMPLETED / duplicate queue / duplicate post).
 */
it('does not queue completed or duplicate invoices when syncing orders', function () {
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

    expect($queued)->toBe(0)
        ->and(Jubelioorder::where('invoice', 'SP-ALREADY-QUEUED')->count())->toBe(1)
        ->and(Jubelioorder::where('invoice', 'SP-COMPLETED-SYNC')->exists())->toBeFalse();
});

it('does not post completed or duplicate sells from the jubelio cron path', function () {
    config(['services.jubelio.order_queue_max_age_days' => 30]);

    $this->mock(\App\Services\JubelioService::class, function ($mock) {
        $mock->shouldNotReceive('fetchSalesOrder');
    });

    $completed = Jubelioorder::create([
        'jubelio_order_id' => 'c-1',
        'source' => 2,
        'invoice' => 'SP-CRON-COMPLETED',
        'type' => 'SELL',
        'order_status' => 'COMPLETED',
        'status' => 0,
    ]);

    app(ProcessJubelioOrder::class)->execute($completed);

    expect(Transaction::where('invoice', 'SP-CRON-COMPLETED')->exists())->toBeFalse()
        ->and($completed->fresh()->error_type)->toBe(JubelioOrderSyncStatus::ERROR_SKIPPED);

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
