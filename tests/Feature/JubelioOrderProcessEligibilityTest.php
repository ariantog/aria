<?php

use App\Actions\Jubelio\ProcessJubelioOrder;
use App\Models\Jubelioorder;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Jubelio\JubelioOrderSyncStatus;

it('does not create a transaction when processing an old completed jubelio sell', function () {
    config(['services.jubelio.order_queue_max_age_days' => 30]);

    mockJubelioSalesOrder('222945', [
        'salesorder_no' => 'SP-250302JA65X2S0',
        'store_id' => 18695,
        'location_id' => 4,
        'internal_status' => 'COMPLETED',
        'transaction_date' => '2025-03-02T15:43:41.000Z',
        'items' => [],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => '222945',
        'source' => 2,
        'invoice' => 'SP-250302JA65X2S0',
        'type' => 'SELL',
        'order_status' => 'COMPLETED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $result = app(ProcessJubelioOrder::class)->execute($order);

    expect($result['success'])->toBeFalse()
        ->and(Transaction::where('invoice', 'SP-250302JA65X2S0')->exists())->toBeFalse();

    $order->refresh();
    expect($order->status)->toBe(2)
        ->and($order->error_type)->toBe(JubelioOrderSyncStatus::ERROR_SKIPPED)
        ->and($order->isPermanentlySkipped())->toBeTrue();
});

it('marks duplicate without calling jubelio api when sell transaction already exists', function () {
    $warehouse = \App\Models\Addrbook::factory()->warehouse()->create();
    $customer = \App\Models\Addrbook::factory()->customer()->create();

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-DUP-PROCESS',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $this->mock(\App\Services\JubelioService::class, function ($mock) {
        $mock->shouldNotReceive('fetchSalesOrder');
    });

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'dup-process',
        'source' => 2,
        'invoice' => 'SP-DUP-PROCESS',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $result = app(ProcessJubelioOrder::class)->execute($order);

    expect($result['success'])->toBeFalse()
        ->and($order->fresh()->error_type)->toBe(JubelioOrderSyncStatus::ERROR_DUPLICATE);
});

it('skips from stored order_status completed before fetching jubelio api', function () {
    $this->mock(\App\Services\JubelioService::class, function ($mock) {
        $mock->shouldNotReceive('fetchSalesOrder');
    });

    $order = Jubelioorder::create([
        'jubelio_order_id' => '222945',
        'source' => 2,
        'invoice' => 'SP-STORED-COMPLETED',
        'type' => 'SELL',
        'order_status' => 'COMPLETED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $result = app(ProcessJubelioOrder::class)->execute($order);

    expect($result['success'])->toBeFalse()
        ->and($order->fresh()->error_type)->toBe(JubelioOrderSyncStatus::ERROR_SKIPPED);
});

it('skips when api payload has channel_status completed even if internal_status is shipped', function () {
    config(['services.jubelio.order_queue_max_age_days' => 30]);

    mockJubelioSalesOrder('mix-status', [
        'salesorder_no' => 'SP-MIX-STATUS',
        'store_id' => 1,
        'location_id' => 1,
        'internal_status' => 'SHIPPED',
        'channel_status' => 'COMPLETED',
        'transaction_date' => now()->subDays(2)->toIso8601String(),
        'items' => [],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'mix-status',
        'source' => 2,
        'invoice' => 'SP-MIX-STATUS',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $result = app(ProcessJubelioOrder::class)->execute($order);

    expect($result['success'])->toBeFalse()
        ->and(Transaction::where('invoice', 'SP-MIX-STATUS')->exists())->toBeFalse()
        ->and($order->fresh()->error_type)->toBe(JubelioOrderSyncStatus::ERROR_SKIPPED);
});

it('blocks manual process on permanently skipped jubelio orders', function () {
    $user = User::factory()->create();

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'skip-1',
        'source' => 2,
        'invoice' => 'SP-SKIPPED',
        'type' => 'SELL',
        'order_status' => 'COMPLETED',
        'run_count' => 1,
        'error' => 'Order tidak diproses: status Jubelio harus SHIPPED (bukan COMPLETED / status lain).',
        'error_type' => JubelioOrderSyncStatus::ERROR_SKIPPED,
        'status' => 2,
    ]);

    expect($order->canProcessManually())->toBeFalse();

    $this->actingAs($user)
        ->post(route('jubelio.process', $order))
        ->assertRedirect()
        ->assertSessionHas('error');
});
