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
