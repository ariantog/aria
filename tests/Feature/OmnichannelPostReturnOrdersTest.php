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
use App\Services\Marketplace\OmnichannelPostReturnEligibility;
use App\Services\Marketplace\OmnichannelReturnOrderPoster;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    config([
        'marketplace.post_return.enabled' => true,
        'marketplace.post_return.dry_run' => false,
        'marketplace.post_return.require_physical_verify' => false,
        'marketplace.post_return.max_age_days' => 30,
        'marketplace.post_return.batch_size' => 10,
        'marketplace.post_return.channels' => ['shopee'],
    ]);
});

function seedNativeReturnFixture(array $orderOverrides = []): array
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

    $order = createMarketplaceChannelOrder(array_merge([
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
    ], $orderOverrides));

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

it('return eligibility accepts TO_RETURN with linked sell', function () {
    ['order' => $order] = seedNativeReturnFixture();

    $eligibility = app(OmnichannelPostReturnEligibility::class);

    expect($eligibility->passesReturnGate($order))->toBeTrue()
        ->and($eligibility->isEligibleForPosting($order))->toBeTrue();
});

it('return eligibility rejects without sell invoice', function () {
    $order = createMarketplaceChannelOrder([
        'channel' => 'shopee',
        'order_ref' => 'NO-SELL',
        'order_status' => 'CANCELLED',
        'aria_warehouse_id' => 1,
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    $eligibility = app(OmnichannelPostReturnEligibility::class);

    expect($eligibility->rejectReason($order))->toBe('Invoice jual tidak ditemukan.');
});

it('return dry run does not create transaction', function () {
    seedNativeReturnFixture();
    config(['marketplace.post_return.dry_run' => true]);

    $stats = app(OmnichannelReturnOrderPoster::class)->runBatch(null, 5, true);

    expect($stats['dry_run'])->toBe(1)
        ->and(Transaction::query()->where('invoice', 'RT-SP-241128RET')->exists())->toBeFalse();
});

it('posts return and marks channel order posted_return', function () {
    ['order' => $order] = seedNativeReturnFixture();

    $stats = app(OmnichannelReturnOrderPoster::class)->runBatch(null, 5, false);

    expect($stats['posted'])->toBe(1);

    $txn = Transaction::query()->where('invoice', 'RT-SP-241128RET')->first();
    expect($txn)->not->toBeNull()
        ->and((int) $txn->type)->toBe(Transaction::TYPE_RETURN)
        ->and((int) $txn->submit_type)->toBe(Transaction::SUBMIT_TYPE_MARKETPLACE)
        ->and($txn->description)->toBe('SP-241128RET');

    $order->refresh();
    expect((int) $order->posted_return_transaction_id)->toBe((int) $txn->id)
        ->and($order->posted_return_at)->not->toBeNull();
});

it('skips duplicate return for sell invoice', function () {
    ['warehouse' => $warehouse, 'customer' => $customer] = seedNativeReturnFixture();

    Transaction::factory()->create([
        'type' => Transaction::TYPE_RETURN,
        'invoice' => 'RT-SP-241128RET',
        'description' => 'SP-241128RET',
        'sender_id' => $customer->id,
        'receiver_id' => $warehouse->id,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
    ]);

    $stats = app(OmnichannelReturnOrderPoster::class)->runBatch(null, 5, false);

    expect($stats['skipped'])->toBeGreaterThanOrEqual(1)
        ->and($stats['posted'])->toBe(0);
});

it('artisan returns requires enable or dry-run', function () {
    config(['marketplace.post_return.enabled' => false, 'marketplace.post_return.dry_run' => false]);

    Artisan::call('omnichannel:post-shipped-orders', ['--returns' => true]);
    expect(Artisan::output())->toContain('OMNICHANNEL_POST_RETURNS');

    Artisan::call('omnichannel:post-shipped-orders', ['--returns' => true, '--dry-run' => true]);
    expect(Artisan::output())->toContain('RETURN gate=');
});

it('native return poster skips when jubelio return queue pending for sell invoice', function () {
    $order = seedNativeReturnFixture()['order'];

    Jubelioorder::create([
        'jubelio_order_id' => 'pending-ret',
        'source' => 2,
        'invoice' => 'SR-PENDING',
        'type' => 'RETURN',
        'source_sell_invoice' => 'SP-241128RET',
        'order_status' => 'RETURNED',
        'status' => 0,
    ]);

    $result = app(OmnichannelReturnOrderPoster::class)->postOrder($order, false);

    expect($result['skipped'])->toBeTrue()
        ->and($result['posted'])->toBeFalse()
        ->and(Transaction::where('invoice', 'RT-SP-241128RET')->count())->toBe(0);
});

it('jubelio process return finishes when native already posted marketplace return', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SP-NATIVE-RET',
        'submit_type' => Transaction::SUBMIT_TYPE_MARKETPLACE,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $returnTxn = Transaction::factory()->create([
        'type' => Transaction::TYPE_RETURN,
        'invoice' => 'RT-SP-NATIVE-RET',
        'description' => 'SP-NATIVE-RET',
        'submit_type' => Transaction::SUBMIT_TYPE_MARKETPLACE,
        'sender_id' => $customer->id,
        'receiver_id' => $warehouse->id,
    ]);

    createMarketplaceChannelOrder([
        'channel' => MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
        'order_ref' => 'NATIVE-RET',
        'shop_ref' => '1',
        'posted_return_transaction_id' => $returnTxn->id,
        'posted_return_at' => now(),
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    $this->mock(\App\Services\JubelioService::class, function ($mock) {
        $mock->shouldNotReceive('fetchSalesReturn');
    });

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'native-ret',
        'source' => 2,
        'invoice' => 'SR-NATIVE',
        'type' => 'RETURN',
        'source_sell_invoice' => 'SP-NATIVE-RET',
        'order_status' => 'RETURNED',
        'status' => 0,
    ]);

    $result = app(ProcessJubelioOrder::class)->execute($order);

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toContain('RETURN')
        ->and(Transaction::where('description', 'SP-NATIVE-RET')->where('type', Transaction::TYPE_RETURN)->count())->toBe(1)
        ->and($order->fresh()->error_type)->toBe(JubelioOrderSyncStatus::SUCCESS)
        ->and($order->fresh()->status)->toBe(2);
});