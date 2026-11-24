<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\MarketplaceOnOrderReservation;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\WarehouseItem;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\Marketplace\ShopeeJubelioQueuePrecreator;
use App\Services\Marketplace\ShopeeJubelioSellOnOrderRelease;
use App\Services\TransactionService;

it('releases on_order when jubelio shopee sell posts with on-order queue placeholder', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create();

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 20,
        'on_order' => 5,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => ShopeeJubelioQueuePrecreator::PLACEHOLDER_ON_ORDER_PREFIX.'OMN66-TEST',
        'source' => Jubelioorder::SOURCE_SHOPEE_PUSH,
        'invoice' => 'SP-OMN66-TEST',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'status' => 0,
    ]);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-OMN66-TEST',
        'sender_id' => $warehouse->id,
        'sender_type' => $warehouse->type,
        'receiver_id' => $customer->id,
        'receiver_type' => $customer->type,
        'status' => Transaction::STATUS_COMPLETED,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'item_id' => $item->id,
        'quantity' => 3,
        'transaction_type' => Transaction::TYPE_SELL,
    ]);

    app(TransactionService::class)->handleTransaction($transaction->fresh('details'));

    $row = WarehouseItem::query()
        ->where('warehouse_id', $warehouse->id)
        ->where('item_id', $item->id)
        ->first();

    expect((float) $row->quantity)->toBe(17.0)
        ->and((float) $row->on_order)->toBe(2.0);
});

it('releases on_order when placeholder was replaced but reservations exist', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create();

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 15,
        'on_order' => 4,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => '58291002',
        'source' => Jubelioorder::SOURCE_SHOPEE_PUSH,
        'invoice' => 'SP-WEBHOOK-REAL-ID',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'status' => 0,
    ]);

    MarketplaceOnOrderReservation::create([
        'channel' => MarketplaceWebhookRecorder::CHANNEL_SHOPEE,
        'order_ref' => 'WEBHOOK-REAL-ID',
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'qty' => 4,
    ]);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-WEBHOOK-REAL-ID',
        'sender_id' => $warehouse->id,
        'sender_type' => $warehouse->type,
        'receiver_id' => $customer->id,
        'receiver_type' => $customer->type,
        'status' => Transaction::STATUS_COMPLETED,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'transaction_type' => Transaction::TYPE_SELL,
    ]);

    app(TransactionService::class)->handleTransaction($transaction->fresh('details'));

    $row = WarehouseItem::query()
        ->where('warehouse_id', $warehouse->id)
        ->where('item_id', $item->id)
        ->first();

    expect((float) $row->on_order)->toBe(2.0);
});

it('does not release on_order for legacy shopee-push placeholder without reservations', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create();

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 10,
        'on_order' => 5,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => ShopeeJubelioQueuePrecreator::PLACEHOLDER_PREFIX.'LEGACY-SN',
        'source' => Jubelioorder::SOURCE_SHOPEE_PUSH,
        'invoice' => 'SP-LEGACY-SN',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'status' => 0,
    ]);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'SP-LEGACY-SN',
        'sender_id' => $warehouse->id,
        'sender_type' => $warehouse->type,
        'receiver_id' => $customer->id,
        'receiver_type' => $customer->type,
        'status' => Transaction::STATUS_COMPLETED,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'transaction_type' => Transaction::TYPE_SELL,
    ]);

    app(TransactionService::class)->handleTransaction($transaction->fresh('details'));

    $row = WarehouseItem::query()
        ->where('warehouse_id', $warehouse->id)
        ->where('item_id', $item->id)
        ->first();

    expect((float) $row->on_order)->toBe(5.0)
        ->and(app(ShopeeJubelioSellOnOrderRelease::class)->shouldReleaseForTransaction($transaction))->toBeFalse();
});

it('does not release on_order for non-shopee jubelio invoice', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create();

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 10,
        'on_order' => 4,
    ]);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'invoice' => 'TT-5790-12345',
        'sender_id' => $warehouse->id,
        'sender_type' => $warehouse->type,
        'receiver_id' => $customer->id,
        'receiver_type' => $customer->type,
        'status' => Transaction::STATUS_COMPLETED,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'transaction_type' => Transaction::TYPE_SELL,
    ]);

    app(TransactionService::class)->handleTransaction($transaction->fresh('details'));

    $row = WarehouseItem::query()
        ->where('warehouse_id', $warehouse->id)
        ->where('item_id', $item->id)
        ->first();

    expect((float) $row->on_order)->toBe(4.0);

    expect(app(ShopeeJubelioSellOnOrderRelease::class)->shouldReleaseForTransaction($transaction))->toBeFalse();
});