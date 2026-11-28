<?php

use App\Actions\Jubelio\ProcessJubelioOrder;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceOrderShadow;
use App\Models\Transaction;
use App\Models\WarehouseItem;
use App\Services\Jubelio\JubelioOrderSyncStatus;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\Marketplace\OmnichannelShippedOrderPoster;

beforeEach(function () {
    config([
        'marketplace.post_sell.enabled' => true,
        'marketplace.post_sell.dry_run' => false,
        'marketplace.post_sell.gate' => 'shipped',
        'marketplace.post_sell.max_age_days' => 30,
        'marketplace.post_sell.channels' => ['shopee'],
    ]);
});

function seedOmnichannelPostRow(string $orderRef = 'DEDUPE-001'): MarketplaceChannelOrder
{
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['code' => 'DEDUPE-SKU', 'price' => 25000]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 20,
        'on_order' => 0,
    ]);

    Jubeliosync::create([
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Shopee Dedupe',
        'jubelio_location_id' => 8,
        'jubelio_location_name' => 'Online',
        'bin_id' => 0,
    ]);

    $order = createMarketplaceChannelOrder([
        'channel' => MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
        'order_ref' => $orderRef,
        'shop_ref' => '9001',
        'order_status' => 'SHIPPED',
        'aria_warehouse_id' => $warehouse->id,
        'last_event_at' => now(),
        'first_received_at' => now()->subHour(),
        'marketplace_ordered_at' => now()->subHour(),
    ]);

    MarketplaceOrderShadow::create([
        'channel' => MarketplaceOrderShadow::CHANNEL_SHOPEE,
        'shop_id' => 9001,
        'external_order_sn' => $orderRef,
        'channel_status' => 'SHIPPED',
        'compare_status' => MarketplaceOrderShadow::COMPARE_PENDING,
        'line_summary' => [
            'lines' => [
                ['sku' => 'DEDUPE-SKU', 'qty' => 1, 'amount' => 25000],
            ],
        ],
        'last_event_at' => now(),
    ]);

    return $order;
}

it('native poster skips when jubelio sell queue is pending for the same invoice', function () {
    $order = seedOmnichannelPostRow('PENDING-JUB');

    Jubelioorder::create([
        'jubelio_order_id' => 'pending-jub',
        'source' => 2,
        'invoice' => 'SP-PENDING-JUB',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'status' => 0,
    ]);

    $result = app(OmnichannelShippedOrderPoster::class)->postOrder($order, false);

    expect($result['skipped'])->toBeTrue()
        ->and($result['posted'])->toBeFalse()
        ->and(Transaction::where('invoice', 'SP-PENDING-JUB')->count())->toBe(0);
});

it('jubelio process marks duplicate when native already posted marketplace channel order', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();

    $txn = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SP-NATIVE-LOCK',
        'submit_type' => Transaction::SUBMIT_TYPE_MARKETPLACE,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    createMarketplaceChannelOrder([
        'channel' => MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
        'order_ref' => 'NATIVE-LOCK',
        'shop_ref' => '1',
        'posted_transaction_id' => $txn->id,
        'posted_at' => now(),
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    $this->mock(\App\Services\JubelioService::class, function ($mock) {
        $mock->shouldNotReceive('fetchSalesOrder');
    });

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'native-lock',
        'source' => 2,
        'invoice' => 'SP-NATIVE-LOCK',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'status' => 0,
    ]);

    $result = app(ProcessJubelioOrder::class)->execute($order);

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toContain('Transaksi sudah ada')
        ->and(Transaction::where('invoice', 'SP-NATIVE-LOCK')->count())->toBe(1)
        ->and($order->fresh()->error_type)->toBe(JubelioOrderSyncStatus::SUCCESS)
        ->and($order->fresh()->status)->toBe(2);
});

it('serial race second native post skips after transaction already exists', function () {
    $order = seedOmnichannelPostRow('RACE-002');
    $warehouse = Addrbook::findOrFail($order->aria_warehouse_id);
    $customerId = (int) Jubeliosync::query()->where('warehouse_id', $warehouse->id)->value('customer_id');

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SP-RACE-002',
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customerId,
    ]);

    $second = app(OmnichannelShippedOrderPoster::class)->postOrder($order->fresh(), false);

    expect($second['skipped'])->toBeTrue()
        ->and(Transaction::where('invoice', 'SP-RACE-002')->count())->toBe(1);
});