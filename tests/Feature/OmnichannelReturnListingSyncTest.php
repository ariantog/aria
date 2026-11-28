<?php

use App\Jobs\PersistShopeePushInboxJob;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\MarketplaceListingStockPush;
use App\Models\MarketplaceWarehouseSync;
use App\Models\Shopeesync;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Marketplace\MarketplaceListingStockPusher;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\Marketplace\OmnichannelMarketplaceReturnWorkflow;
use App\Services\Marketplace\OmnichannelReturnShopeeListingCorrector;
use App\Services\Marketplace\OmnichannelReturnVerifyListingPush;
use App\Services\Shopee\ShopeeListingStockWriter;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function () {
    config([
        'marketplace.listing_push.allowlist_item_ids' => [],
        'marketplace.listing_push.return_shopee_listing_correct' => false,
        'marketplace.listing_push.return_verify_listing_push' => false,
        'marketplace.listing_push.return_verify_auto_post' => true,
        'marketplace.post_return.enabled' => true,
        'marketplace.post_return.dry_run' => false,
        'marketplace.post_return.require_physical_verify' => true,
    ]);
});

function seedReturnListingPilotItem(): array
{
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();

    $item = Item::factory()->create([
        'code' => 'LST-PILOT-M',
        'shopee_item_id' => 88001,
        'shopee_model_id' => 99001,
        'tiktok_shop_product_id' => 'tp1',
        'tiktok_shop_sku_id' => 'tsk1',
    ]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 12,
        'on_order' => 2,
    ]);

    Shopeesync::create([
        'warehouse_id' => $warehouse->id,
        'shop_id' => 7001,
        'shopee_location_id' => 'IDZ',
        'shopee_warehouse_id' => 1,
        'shopee_warehouse_name' => 'Online',
    ]);

    MarketplaceWarehouseSync::query()->create([
        'channel' => MarketplaceWarehouseSync::CHANNEL_SHOPEE,
        'shop_ref' => '7001',
        'platform_location_id' => 'IDZ',
        'platform_warehouse_id' => '1',
        'warehouse_id' => $warehouse->id,
    ]);

    MarketplaceWarehouseSync::query()->create([
        'channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK,
        'shop_ref' => 'tt-shop-1',
        'shop_cipher' => 'cipher-1',
        'platform_warehouse_id' => 'wh-9',
        'warehouse_id' => $warehouse->id,
    ]);

    Jubeliosync::create([
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Shopee',
        'jubelio_location_id' => 8,
        'jubelio_location_name' => 'Online',
        'bin_id' => 0,
    ]);

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SP-LSTRET',
        'submit_type' => Transaction::SUBMIT_TYPE_MARKETPLACE,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'total' => Transaction::signedAmount(Transaction::TYPE_SELL, 50000),
    ]);

    $order = createMarketplaceChannelOrder([
        'channel' => MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
        'order_ref' => 'LSTRET',
        'shop_ref' => '7001',
        'order_status' => 'TO_RETURN',
        'aria_warehouse_id' => $warehouse->id,
        'posted_transaction_id' => $sell->id,
        'last_event_at' => now(),
        'first_received_at' => now()->subDay(),
        'marketplace_ordered_at' => now()->subDay(),
    ]);

    return compact('warehouse', 'customer', 'item', 'order', 'sell');
}

it('shopee return webhook listing correct calls update_stock without changing warehouse_item', function () {
    $fixture = seedReturnListingPilotItem();
    config([
        'marketplace.listing_push.allowlist_item_ids' => [$fixture['item']->id],
        'marketplace.listing_push.return_shopee_listing_correct' => true,
    ]);

    $this->mock(ShopeeListingStockWriter::class, function ($mock) {
        $mock->shouldReceive('pushLocationStock')
            ->andReturn(['ok' => true, 'http_status' => 200, 'snippet' => 'ok', 'error' => null]);
    });

    $whRow = WarehouseItem::query()
        ->where('item_id', $fixture['item']->id)
        ->where('warehouse_id', $fixture['warehouse']->id)
        ->first();

    $beforeQty = (int) $whRow->quantity;
    $beforeOnOrder = (int) $whRow->on_order;

    $payload = [
        'code' => 3,
        'shop_id' => 7001,
        'data' => [
            'ordersn' => 'LSTRET',
            'status' => 'TO_RETURN',
            'item_list' => [
                [
                    'model_sku' => 'LST-PILOT-M',
                    'model_quantity_purchased' => 1,
                ],
            ],
        ],
    ];

    $stats = app(OmnichannelReturnShopeeListingCorrector::class)->applyFromPush($payload);

    expect($stats['pushed'])->toBeGreaterThanOrEqual(1);

    $whRow->refresh();
    expect((int) $whRow->quantity)->toBe($beforeQty)
        ->and((int) $whRow->on_order)->toBe($beforeOnOrder);

    expect(MarketplaceListingStockPush::query()
        ->where('item_id', $fixture['item']->id)
        ->where('trigger', MarketplaceListingStockPusher::TRIGGER_RETURN_WEBHOPEE_CORRECT)
        ->where('outcome', 'success')
        ->exists())->toBeTrue();
});

it('queues return listing correction from persist shopee push job', function () {
    $fixture = seedReturnListingPilotItem();
    config([
        'marketplace.listing_push.allowlist_item_ids' => [$fixture['item']->id],
        'marketplace.listing_push.return_shopee_listing_correct' => true,
    ]);

    $this->mock(ShopeeListingStockWriter::class, function ($mock) {
        $mock->shouldReceive('pushLocationStock')
            ->andReturn(['ok' => true, 'http_status' => 200, 'snippet' => 'ok', 'error' => null]);
    });

    $job = new PersistShopeePushInboxJob([
        'code' => 3,
        'shop_id' => 7001,
        'data' => [
            'ordersn' => 'LSTRET',
            'status' => 'TO_RETURN',
            'item_list' => [
                ['model_sku' => 'LST-PILOT-M', 'model_quantity_purchased' => 1],
            ],
        ],
    ]);

    $job->handle(
        app(\App\Services\Marketplace\ShopeeOrderStatusIngest::class),
        app(\App\Services\Marketplace\MarketplaceWebhookRecorder::class),
        app(\App\Services\Marketplace\MarketplaceWebhookStatusPresenter::class),
        app(\App\Services\Marketplace\ShopeePushWebhookOnOrderApplicator::class),
        app(OmnichannelReturnShopeeListingCorrector::class),
    );

    expect(MarketplaceListingStockPush::query()
        ->where('trigger', MarketplaceListingStockPusher::TRIGGER_RETURN_WEBHOPEE_CORRECT)
        ->exists())->toBeTrue();
});

it('verify with listing push auto-posts return and pushes shopee listing', function () {
    $user = User::factory()->create(['id' => 1]);
    $fixture = seedReturnListingPilotItem();

    config([
        'marketplace.listing_push.allowlist_item_ids' => [$fixture['item']->id],
        'marketplace.listing_push.return_verify_listing_push' => true,
        'marketplace.post_return.require_physical_verify' => true,
    ]);

    \App\Models\MarketplaceOrderShadow::create([
        'channel' => \App\Models\MarketplaceOrderShadow::CHANNEL_SHOPEE,
        'shop_id' => 7001,
        'external_order_sn' => 'LSTRET',
        'channel_status' => 'TO_RETURN',
        'compare_status' => \App\Models\MarketplaceOrderShadow::COMPARE_PENDING,
        'line_summary' => [
            'lines' => [
                ['sku' => 'LST-PILOT-M', 'qty' => 1, 'amount' => 50000],
            ],
        ],
        'last_event_at' => now(),
    ]);

    $this->mock(ShopeeListingStockWriter::class, function ($mock) {
        $mock->shouldReceive('pushLocationStock')
            ->andReturn(['ok' => true, 'http_status' => 200, 'snippet' => 'ok', 'error' => null]);
    });

    $workflow = app(OmnichannelMarketplaceReturnWorkflow::class);
    $workflow->verify($fixture['order'], $user, 'QC ok');

    $followUp = app(OmnichannelReturnVerifyListingPush::class)->afterVerify($fixture['order']->fresh(), $user);

    expect($followUp['posted'])->toBeTrue()
        ->and(Transaction::query()->where('invoice', 'RT-SP-LSTRET')->exists())->toBeTrue()
        ->and($followUp['listing']['pushed'])->toBeGreaterThanOrEqual(1);

    expect(MarketplaceListingStockPush::query()
        ->where('trigger', MarketplaceListingStockPusher::TRIGGER_RETURN_VERIFY_ALL_CHANNELS)
        ->where('channel', 'shopee')
        ->where('outcome', 'success')
        ->exists())->toBeTrue();
});

it('verify controller chains listing push messaging when flag on', function () {
    $user = User::factory()->create(['id' => 1]);
    $fixture = seedReturnListingPilotItem();

    config([
        'marketplace.listing_push.allowlist_item_ids' => [$fixture['item']->id],
        'marketplace.listing_push.return_verify_listing_push' => true,
    ]);

    \App\Models\MarketplaceOrderShadow::create([
        'channel' => \App\Models\MarketplaceOrderShadow::CHANNEL_SHOPEE,
        'shop_id' => 7001,
        'external_order_sn' => 'LSTRET',
        'channel_status' => 'TO_RETURN',
        'compare_status' => \App\Models\MarketplaceOrderShadow::COMPARE_PENDING,
        'line_summary' => [
            'lines' => [['sku' => 'LST-PILOT-M', 'qty' => 1, 'amount' => 50000]],
        ],
        'last_event_at' => now(),
    ]);

    $this->mock(ShopeeListingStockWriter::class, function ($mock) {
        $mock->shouldReceive('pushLocationStock')
            ->andReturn(['ok' => true, 'http_status' => 200, 'snippet' => 'ok', 'error' => null]);
    });

    actingAs($user)
        ->post(route('omnichannel.returns.verify', $fixture['order']))
        ->assertRedirect()
        ->assertSessionHas('success');
});