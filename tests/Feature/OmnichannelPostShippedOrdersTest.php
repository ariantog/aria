<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubeliosync;
use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceOrderShadow;
use App\Models\Transaction;
use App\Models\WarehouseItem;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\Marketplace\OmnichannelPostSellEligibility;
use App\Services\Marketplace\OmnichannelSettingStore;
use App\Services\Marketplace\OmnichannelShippedOrderPoster;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    config([
        'marketplace.post_sell.enabled' => true,
        'marketplace.post_sell.dry_run' => false,
        'marketplace.post_sell.gate' => 'shipped',
        'marketplace.post_sell.max_age_days' => 30,
        'marketplace.post_sell.batch_size' => 10,
        'marketplace.post_sell.channels' => ['shopee'],
    ]);
});

function seedNativePostFixture(array $orderOverrides = []): array
{
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
  $item = Item::factory()->create(['code' => 'NATIVE-SKU-1', 'price' => 50000]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 10,
        'on_order' => 0,
    ]);

    Jubeliosync::create([
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Shopee Test',
        'jubelio_location_id' => 8,
        'jubelio_location_name' => 'Online',
        'bin_id' => 0,
    ]);

    $order = createMarketplaceChannelOrder(array_merge([
        'channel' => MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
        'order_ref' => '241128ABC',
        'shop_ref' => '9001',
        'order_status' => 'SHIPPED',
        'aria_warehouse_id' => $warehouse->id,
        'last_event_at' => now(),
        'first_received_at' => now()->subHour(),
        'marketplace_ordered_at' => now()->subHour(),
    ], $orderOverrides));

    MarketplaceOrderShadow::create([
        'channel' => MarketplaceOrderShadow::CHANNEL_SHOPEE,
        'shop_id' => 9001,
        'external_order_sn' => '241128ABC',
        'channel_status' => 'SHIPPED',
        'compare_status' => MarketplaceOrderShadow::COMPARE_PENDING,
        'line_summary' => [
            'lines' => [
                ['sku' => 'NATIVE-SKU-1', 'qty' => 2, 'amount' => 100000],
            ],
        ],
        'last_event_at' => now(),
    ]);

    return compact('warehouse', 'customer', 'item', 'order');
}

it('eligibility accepts shipped gate without awb_printed_at', function () {
    $order = createMarketplaceChannelOrder([
        'channel' => 'shopee',
        'order_ref' => 'SHIP-NO-AWB',
        'order_status' => 'SHIPPED',
        'aria_warehouse_id' => 1,
        'awb_printed_at' => null,
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    config(['marketplace.post_sell.gate' => 'shipped']);
    $eligibility = app(OmnichannelPostSellEligibility::class);

    expect($eligibility->passesGate($order))->toBeTrue();
});

it('gate mode prefers omnichannel_settings over config', function () {
    config(['marketplace.post_sell.gate' => 'shipped']);
    app(OmnichannelSettingStore::class)->set(
        OmnichannelSettingStore::KEY_POST_SELL_GATE,
        OmnichannelSettingStore::POST_SELL_GATE_AWB_PRINTED,
    );

    expect(app(OmnichannelPostSellEligibility::class)->gateMode())->toBe('awb_printed');
});

it('eligibility rejects unshipped shopee order under shipped gate', function () {
    $order = createMarketplaceChannelOrder([
        'channel' => 'shopee',
        'order_ref' => 'NOT-SHIP',
        'order_status' => 'PROCESSED',
        'aria_warehouse_id' => 1,
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    $eligibility = app(OmnichannelPostSellEligibility::class);
    config(['marketplace.post_sell.gate' => 'shipped']);

    expect($eligibility->passesGate($order))->toBeFalse();
});

it('eligibility accepts awb_printed gate when awb_printed_at set', function () {
    $order = createMarketplaceChannelOrder([
        'channel' => 'shopee',
        'order_ref' => 'AWB-GATE',
        'order_status' => 'READY_TO_SHIP',
        'aria_warehouse_id' => 1,
        'awb_printed_at' => now(),
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    config(['marketplace.post_sell.gate' => 'awb_printed']);
    $eligibility = app(OmnichannelPostSellEligibility::class);

    expect($eligibility->passesGate($order))->toBeTrue();
});

it('dry run does not create a transaction', function () {
    seedNativePostFixture();
    config(['marketplace.post_sell.dry_run' => true]);

    $stats = app(OmnichannelShippedOrderPoster::class)->runBatch(null, 5, true);

    expect($stats['dry_run'])->toBe(1)
        ->and(Transaction::query()->where('invoice', 'SP-241128ABC')->exists())->toBeFalse();
});

it('posts sell and marks channel order posted', function () {
    ['order' => $order] = seedNativePostFixture();

    $stats = app(OmnichannelShippedOrderPoster::class)->runBatch(null, 5, false);

    expect($stats['posted'])->toBe(1);

    $txn = Transaction::query()->where('invoice', 'SP-241128ABC')->first();
    expect($txn)->not->toBeNull()
        ->and((int) $txn->submit_type)->toBe(Transaction::SUBMIT_TYPE_MARKETPLACE);

    $order->refresh();
    expect((int) $order->posted_transaction_id)->toBe((int) $txn->id)
        ->and($order->posted_at)->not->toBeNull();
});

it('skips duplicate invoice', function () {
    ['warehouse' => $warehouse, 'customer' => $customer] = seedNativePostFixture();

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SP-241128ABC',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
    ]);

    $stats = app(OmnichannelShippedOrderPoster::class)->runBatch(null, 5, false);

    expect($stats['skipped'])->toBeGreaterThanOrEqual(1)
        ->and($stats['posted'])->toBe(0);
});

it('artisan command requires enable or dry-run', function () {
    config(['marketplace.post_sell.enabled' => false, 'marketplace.post_sell.dry_run' => false]);

    Artisan::call('omnichannel:post-shipped-orders');
    expect(Artisan::output())->toContain('OMNICHANNEL_POST_SELLS');

    Artisan::call('omnichannel:post-shipped-orders', ['--dry-run' => true]);
    expect(Artisan::output())->toContain('SELL gate=');
});