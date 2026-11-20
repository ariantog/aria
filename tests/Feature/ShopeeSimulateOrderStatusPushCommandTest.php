<?php

use App\Jobs\RecordShopeePushShadowJob;
use App\Models\Jubelioorder;
use App\Models\MarketplaceOrderShadow;
use App\Models\Transaction;
use Illuminate\Support\Facades\Queue;

it('simulate command upserts shopee test shadow without writes elsewhere', function () {
    Queue::fake();

    $beforeTxns = Transaction::count();
    $beforeJubelio = Jubelioorder::count();

    $this->artisan('shopee:simulate-order-status-push', [
        '--ordersn' => '220810QSK8S7BX',
        '--status' => 'PROCESSED',
        '--shop-id' => 727720655,
    ])->assertSuccessful();

    $shadow = MarketplaceOrderShadow::query()->where('external_order_sn', '220810QSK8S7BX')->first();
    expect($shadow)->not->toBeNull()
        ->and($shadow->compare_status)->toBe(MarketplaceOrderShadow::COMPARE_PENDING)
        ->and($shadow->channel_status)->toBe('PROCESSED')
        ->and($shadow->compare_note)->toBe('shopee_test_push')
        ->and($shadow->shop_id)->toBe(727720655);

    expect(Transaction::count())->toBe($beforeTxns)
        ->and(Jubelioorder::count())->toBe($beforeJubelio);

    Queue::assertNothingPushed();
});

it('simulate command can dispatch read-only enrich job', function () {
    Queue::fake();

    $this->artisan('shopee:simulate-order-status-push', [
        '--ordersn' => 'SIMCMD001',
        '--dispatch-job' => true,
    ])->assertSuccessful();

    Queue::assertPushed(RecordShopeePushShadowJob::class, fn ($job) => $job->orderSn === 'SIMCMD001');
});