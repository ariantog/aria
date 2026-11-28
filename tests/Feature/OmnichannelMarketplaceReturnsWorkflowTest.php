<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\MarketplaceOrderShadow;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\Marketplace\OmnichannelMarketplaceReturnWorkflow;
use App\Services\Marketplace\OmnichannelPostReturnEligibility;
use App\Services\Marketplace\OmnichannelReturnOrderPoster;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function () {
    config([
        'marketplace.post_return.enabled' => true,
        'marketplace.post_return.dry_run' => false,
        'marketplace.post_return.require_physical_verify' => true,
        'marketplace.post_return.max_age_days' => 30,
        'marketplace.post_return.channels' => ['shopee', 'tiktok', 'tokopedia'],
    ]);
});

function seedMarketplaceReturnVerifyFixture(): array
{
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['code' => 'RET-SKU-1', 'price' => 50000]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 5,
        'on_order' => 0,
    ]);

    Jubeliosync::create([
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Shopee Return',
        'jubelio_location_id' => 8,
        'jubelio_location_name' => 'Online',
        'bin_id' => 0,
    ]);

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SP-241128RET',
        'submit_type' => Transaction::SUBMIT_TYPE_MARKETPLACE,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'total' => Transaction::signedAmount(Transaction::TYPE_SELL, 100000),
    ]);

    $order = createMarketplaceChannelOrder([
        'channel' => MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
        'order_ref' => '241128RET',
        'shop_ref' => '9001',
        'order_status' => 'TO_RETURN',
        'aria_warehouse_id' => $warehouse->id,
        'posted_transaction_id' => $sell->id,
        'posted_at' => now()->subDay(),
        'last_event_at' => now(),
        'first_received_at' => now()->subDays(2),
        'marketplace_ordered_at' => now()->subDays(2),
    ]);

    MarketplaceOrderShadow::create([
        'channel' => MarketplaceOrderShadow::CHANNEL_SHOPEE,
        'shop_id' => 9001,
        'external_order_sn' => '241128RET',
        'channel_status' => 'TO_RETURN',
        'compare_status' => MarketplaceOrderShadow::COMPARE_PENDING,
        'line_summary' => [
            'lines' => [
                ['sku' => 'RET-SKU-1', 'qty' => 2, 'amount' => 100000],
            ],
        ],
        'last_event_at' => now(),
    ]);

    return compact('warehouse', 'customer', 'item', 'order', 'sell');
}

it('returns list page renders for omnichannel orders permission', function () {
    $user = User::factory()->create(['id' => 1]);

    actingAs($user)
        ->get(route('omnichannel.returns.index'))
        ->assertOk()
        ->assertSee('data-testid="omnichannel-returns-page"', false);
});

it('blocks cron post before physical verify', function () {
    ['order' => $order] = seedMarketplaceReturnVerifyFixture();

    $stats = app(OmnichannelReturnOrderPoster::class)->runBatch(null, 5, false);

    expect($stats['processed'])->toBe(0)
        ->and($stats['posted'])->toBe(0)
        ->and(Transaction::query()->where('invoice', 'RT-SP-241128RET')->exists())->toBeFalse();

    expect(app(OmnichannelPostReturnEligibility::class)->rejectReason($order->fresh()))
        ->toBe('Belum diverifikasi penerimaan fisik.');
});

it('verify then post return from workflow', function () {
    $user = User::factory()->create(['id' => 1]);
    ['order' => $order] = seedMarketplaceReturnVerifyFixture();

    $workflow = app(OmnichannelMarketplaceReturnWorkflow::class);
    $workflow->verify($order, $user, 'Barang diterima gudang');

    $order->refresh();
    expect($order->return_verified_at)->not->toBeNull()
        ->and((int) $order->return_verified_by)->toBe((int) $user->id);

    $stats = app(OmnichannelReturnOrderPoster::class)->runBatch(null, 5, false);
    expect($stats['posted'])->toBe(1);

    expect(Transaction::query()->where('invoice', 'RT-SP-241128RET')->exists())->toBeTrue();
});

it('verify button on returns page then manual post', function () {
    $user = User::factory()->create(['id' => 1]);
    ['order' => $order] = seedMarketplaceReturnVerifyFixture();

    actingAs($user)
        ->get(route('omnichannel.returns.index', ['status' => 'pending_verification']))
        ->assertOk()
        ->assertSee('data-testid="omnichannel-returns-verify-'.$order->id.'"', false);

    post(route('omnichannel.returns.verify', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    $order->refresh();
    expect($order->return_verified_at)->not->toBeNull();

    post(route('omnichannel.returns.post', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Transaction::query()->where('invoice', 'RT-SP-241128RET')->exists())->toBeTrue();
});

it('detects shopee TO_RETURN and tiktok cancel as return signals', function () {
    $eligibility = app(OmnichannelPostReturnEligibility::class);
    $workflow = app(OmnichannelMarketplaceReturnWorkflow::class);

    $shopee = createMarketplaceChannelOrder([
        'channel' => MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
        'order_ref' => 'SIG-SHOPEE',
        'order_status' => 'TO_RETURN',
        'last_event_at' => now(),
        'first_received_at' => now()->subDay(),
        'marketplace_ordered_at' => now()->subDay(),
    ]);

    $tiktok = createMarketplaceChannelOrder([
        'channel' => MarketplaceWebhookRecorder::CHANNEL_TIKTOK,
        'order_ref' => 'SIG-TT',
        'order_status' => 'CANCELLED',
        'last_event_at' => now(),
        'first_received_at' => now()->subDay(),
        'marketplace_ordered_at' => now()->subDay(),
    ]);

    $tokopedia = createMarketplaceChannelOrder([
        'channel' => MarketplaceWebhookRecorder::CHANNEL_TIKTOK,
        'commerce_platform' => 'TOKOPEDIA',
        'order_ref' => 'SIG-TKP',
        'order_status' => 'CANCEL',
        'last_event_at' => now(),
        'first_received_at' => now()->subDay(),
        'marketplace_ordered_at' => now()->subDay(),
    ]);

    expect($workflow->hasReturnSignal($shopee))->toBeTrue()
        ->and($eligibility->passesReturnGate($shopee))->toBeTrue()
        ->and($workflow->hasReturnSignal($tiktok))->toBeTrue()
        ->and($workflow->hasReturnSignal($tokopedia))->toBeTrue()
        ->and($eligibility->passesChannelAllowlist($tokopedia))->toBeTrue();
});

it('existing jubelio return guard regression still blocks duplicate native post', function () {
    config(['marketplace.post_return.require_physical_verify' => false]);

    $order = seedMarketplaceReturnVerifyFixture()['order'];

    Jubelioorder::create([
        'jubelio_order_id' => 'wf-pending',
        'source' => 2,
        'invoice' => 'SR-WF',
        'type' => 'RETURN',
        'source_sell_invoice' => 'SP-241128RET',
        'order_status' => 'RETURNED',
        'status' => 0,
    ]);

    $result = app(OmnichannelReturnOrderPoster::class)->postOrder($order, false);

    expect($result['skipped'])->toBeTrue()
        ->and(Transaction::where('invoice', 'RT-SP-241128RET')->count())->toBe(0);
});