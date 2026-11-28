<?php

use App\Models\Addrbook;
use App\Models\MarketplaceOrderShadow;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\Marketplace\OmnichannelMarketplaceReturnWorkflow;
use App\Services\Marketplace\OmnichannelReturnOrderPoster;
use App\Services\Marketplace\OmnichannelReturnQueueReconciler;
use Illuminate\Support\Facades\Artisan;
use function Pest\Laravel\actingAs;
beforeEach(function () {
    config([
        'marketplace.post_return.enabled' => true,
        'marketplace.post_return.dry_run' => false,
        'marketplace.post_return.require_physical_verify' => true,
        'marketplace.post_return.max_age_days' => 30,
        'marketplace.post_return.channels' => ['shopee', 'tiktok', 'tokopedia'],
    ]);
});

function seedReturnReconcileFixture(array $orderOverrides = []): array
{
  $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SP-RECON-01',
        'submit_type' => Transaction::SUBMIT_TYPE_MARKETPLACE,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'total' => Transaction::signedAmount(Transaction::TYPE_SELL, 50000),
    ]);

    $order = createMarketplaceChannelOrder(array_merge([
        'channel' => MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
        'order_ref' => 'RECON-01',
        'shop_ref' => '9002',
        'order_status' => 'TO_RETURN',
        'aria_warehouse_id' => $warehouse->id,
        'posted_transaction_id' => $sell->id,
        'posted_at' => now()->subDay(),
        'last_event_at' => now(),
        'first_received_at' => now()->subDays(2),
        'marketplace_ordered_at' => now()->subDays(2),
    ], $orderOverrides));

    MarketplaceOrderShadow::create([
        'channel' => MarketplaceOrderShadow::CHANNEL_SHOPEE,
        'shop_id' => 9002,
        'external_order_sn' => 'RECON-01',
        'channel_status' => 'TO_RETURN',
        'compare_status' => MarketplaceOrderShadow::COMPARE_PENDING,
        'line_summary' => [
            'lines' => [
                ['sku' => 'X', 'qty' => 1, 'amount' => 50000],
            ],
        ],
        'last_event_at' => now(),
    ]);

    return compact('warehouse', 'customer', 'sell', 'order');
}

it('links existing RETURN without creating a new transaction', function () {
    $user = User::factory()->create(['id' => 1]);
    ['sell' => $sell, 'order' => $order] = seedReturnReconcileFixture();

    $existingReturn = Transaction::factory()->create([
        'type' => Transaction::TYPE_RETURN,
        'invoice' => 'SR-SP-RECON-01',
        'description' => 'SP-RECON-01',
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'sender_id' => $sell->receiver_id,
        'receiver_id' => $sell->sender_id,
        'total' => Transaction::signedAmount(Transaction::TYPE_RETURN, 50000),
    ]);

    $beforeCount = Transaction::query()->where('type', Transaction::TYPE_RETURN)->count();

    actingAs($user)
        ->post(route('omnichannel.returns.link-existing', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    $order->refresh();
    expect((int) $order->posted_return_transaction_id)->toBe((int) $existingReturn->id)
        ->and($order->posted_return_at)->not->toBeNull()
        ->and(Transaction::query()->where('type', Transaction::TYPE_RETURN)->count())->toBe($beforeCount);

    expect(app(OmnichannelMarketplaceReturnWorkflow::class)->workflowStatus($order))
        ->toBe(OmnichannelMarketplaceReturnWorkflow::STATUS_POSTED);
});

it('dismisses a pending return without posting stock', function () {
    $user = User::factory()->create(['id' => 1]);
    ['order' => $order] = seedReturnReconcileFixture();

    $workflow = app(OmnichannelMarketplaceReturnWorkflow::class);
    expect($workflow->workflowStatus($order))->toBe(OmnichannelMarketplaceReturnWorkflow::STATUS_PENDING_VERIFICATION);

    actingAs($user)
        ->post(route('omnichannel.returns.dismiss', $order), ['note' => 'Sudah ditangani manual'])
        ->assertRedirect()
        ->assertSessionHas('success');

    $order->refresh();
    expect($order->return_dismissed_at)->not->toBeNull()
        ->and((int) $order->return_dismissed_by)->toBe((int) $user->id)
        ->and($workflow->workflowStatus($order))->toBe(OmnichannelMarketplaceReturnWorkflow::STATUS_DISMISSED);

    $pending = $workflow->listQuery(['status' => OmnichannelMarketplaceReturnWorkflow::STATUS_PENDING_VERIFICATION, 'channel' => 'all']);
    expect($pending->where('id', $order->id)->exists())->toBeFalse();

    expect(Transaction::query()->where('invoice', 'RT-SP-RECON-01')->exists())->toBeFalse();
});

it('does not double post after link-existing', function () {
    config(['marketplace.post_return.require_physical_verify' => false]);

    ['sell' => $sell, 'order' => $order] = seedReturnReconcileFixture();

    Transaction::factory()->create([
        'type' => Transaction::TYPE_RETURN,
        'invoice' => 'RT-SP-RECON-01',
        'description' => 'SP-RECON-01',
        'sender_id' => $sell->receiver_id,
        'receiver_id' => $sell->sender_id,
        'total' => Transaction::signedAmount(Transaction::TYPE_RETURN, 50000),
    ]);

    app(OmnichannelReturnQueueReconciler::class)->linkExistingReturn($order);
    $order->refresh();

    $result = app(OmnichannelReturnOrderPoster::class)->postOrder($order, false);
    $returnCount = Transaction::query()
        ->where('type', Transaction::TYPE_RETURN)
        ->where(function ($q) {
            $q->where('invoice', 'RT-SP-RECON-01')
                ->orWhere('description', 'SP-RECON-01');
        })
        ->count();

    expect($result['skipped'])->toBeTrue()
        ->and($returnCount)->toBe(1);
});

it('reconcile artisan dry-run reports linkable rows without writing', function () {
    ['order' => $order] = seedReturnReconcileFixture();

    Transaction::factory()->create([
        'type' => Transaction::TYPE_RETURN,
        'invoice' => 'RT-SP-RECON-01',
        'description' => 'SP-RECON-01',
        'total' => Transaction::signedAmount(Transaction::TYPE_RETURN, 1),
    ]);

    $stats = app(OmnichannelReturnQueueReconciler::class)->reconcileBatch(false, false, null);
    expect($stats['linked'])->toBeGreaterThanOrEqual(1)
        ->and($stats['scanned'])->toBeGreaterThanOrEqual(1);

    $order->refresh();
    expect((int) ($order->posted_return_transaction_id ?? 0))->toBe(0);

    expect(Artisan::call('omnichannel:reconcile-return-queue'))->toBe(0);
});

it('reconcile artisan apply links pending rows', function () {
    ['order' => $order] = seedReturnReconcileFixture();

    $existing = Transaction::factory()->create([
        'type' => Transaction::TYPE_RETURN,
        'invoice' => 'SR-SP-RECON-01',
        'description' => 'SP-RECON-01',
        'total' => Transaction::signedAmount(Transaction::TYPE_RETURN, 1),
    ]);

    Artisan::call('omnichannel:reconcile-return-queue', ['--apply' => true]);

    $order->refresh();
    expect((int) $order->posted_return_transaction_id)->toBe((int) $existing->id);
});