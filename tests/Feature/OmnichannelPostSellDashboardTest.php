<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\MarketplaceOrderShadow;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Jubelio\JubelioOrderSyncStatus;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\Marketplace\OmnichannelPostSellDashboardStats;
use App\Services\Marketplace\OmnichannelShippedOrderPoster;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'marketplace.post_sell.enabled' => true,
        'marketplace.post_sell.dry_run' => false,
        'marketplace.post_sell.gate' => 'shipped',
        'marketplace.post_sell.max_age_days' => 30,
        'marketplace.post_sell.channels' => ['shopee'],
    ]);
});

it('dashboard stats count posted eligible and errors', function () {
    createMarketplaceChannelOrder([
        'channel' => 'shopee',
        'order_ref' => 'STAT-POSTED',
        'order_status' => 'SHIPPED',
        'aria_warehouse_id' => 1,
        'posted_transaction_id' => 42,
        'posted_at' => now(),
        'last_event_at' => now(),
        'first_received_at' => now(),
        'marketplace_ordered_at' => now(),
    ]);

    createMarketplaceChannelOrder([
        'channel' => 'shopee',
        'order_ref' => 'STAT-ELIGIBLE',
        'order_status' => 'SHIPPED',
        'aria_warehouse_id' => 5,
        'last_event_at' => now(),
        'first_received_at' => now(),
        'marketplace_ordered_at' => now(),
    ]);

    MarketplaceOrderShadow::create([
        'channel' => MarketplaceOrderShadow::CHANNEL_SHOPEE,
        'shop_id' => 1,
        'external_order_sn' => 'STAT-ELIGIBLE',
        'channel_status' => 'SHIPPED',
        'compare_status' => MarketplaceOrderShadow::COMPARE_PENDING,
        'line_summary' => ['lines' => [['sku' => 'X', 'qty' => 1]]],
        'last_event_at' => now(),
    ]);

    createMarketplaceChannelOrder([
        'channel' => 'shopee',
        'order_ref' => 'STAT-ERR',
        'order_status' => 'SHIPPED',
        'aria_warehouse_id' => 0,
        'last_event_at' => now(),
        'first_received_at' => now(),
        'marketplace_ordered_at' => now(),
    ]);

    $stats = app(OmnichannelPostSellDashboardStats::class)->counts();

    expect($stats['posted'])->toBe(1)
        ->and($stats['eligible_to_post'])->toBe(1)
        ->and($stats['errors'])->toBe(1);
});

it('native skip backfills posted_transaction_id and finishes jubelio sell queue', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();

    $txn = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SP-BACKFILL-1',
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $order = createMarketplaceChannelOrder([
        'channel' => MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
        'order_ref' => 'BACKFILL-1',
        'shop_ref' => '9001',
        'order_status' => 'SHIPPED',
        'aria_warehouse_id' => $warehouse->id,
        'last_event_at' => now(),
        'first_received_at' => now(),
        'marketplace_ordered_at' => now(),
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'bf-jub',
        'source' => 2,
        'invoice' => 'SP-BACKFILL-1',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'status' => 0,
    ]);

    $result = app(OmnichannelShippedOrderPoster::class)->postOrder($order, false);

    expect($result['skipped'])->toBeTrue();
    expect((int) $order->fresh()->posted_transaction_id)->toBe((int) $txn->id);

    $jubelio = Jubelioorder::query()->where('invoice', 'SP-BACKFILL-1')->first();
    expect($jubelio->status)->toBe(2)
        ->and($jubelio->error_type)->toBe(JubelioOrderSyncStatus::SUCCESS);
});

it('order detail shows sell submit source label', function () {
    $user = User::factory()->create(['id' => 1]);

    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $txn = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SP-LABEL-1',
        'submit_type' => Transaction::SUBMIT_TYPE_MARKETPLACE,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    createMarketplaceChannelOrder([
        'channel' => 'shopee',
        'order_ref' => 'LABEL-1',
        'shop_ref' => '1',
        'order_status' => 'SHIPPED',
        'posted_transaction_id' => $txn->id,
        'posted_at' => now(),
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('omnichannel.orders.show', ['channel' => 'shopee', 'orderRef' => 'LABEL-1']));

    $response->assertOk()
        ->assertSee('data-testid="omnichannel-order-sell-submit-source"', false)
        ->assertSee('Omnichannel cron');
});

it('orders page renders native post sell stat cards', function () {
    $user = User::factory()->create(['id' => 1]);

    if (! Schema::hasTable('marketplace_channel_orders')) {
        $this->markTestSkipped('marketplace_channel_orders missing');
    }

    $response = $this->actingAs($user)
        ->get(route('omnichannel.orders.index', ['view' => 'orders']));

    $response->assertOk()
        ->assertSee('data-testid="omnichannel-native-post-sell-stats"', false);
});