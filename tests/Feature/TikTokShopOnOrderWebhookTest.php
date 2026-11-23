<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\MarketplaceOnOrderReservation;
use App\Models\TiktokShopSync;
use App\Models\WarehouseItem;
use App\Services\TikTokShop\TikTokShopWebhookSignatureVerifier;

beforeEach(function () {
    config([
        'tiktok_shop.app_key' => 'abcdef',
        'tiktok_shop.app_secret' => '123',
        'tiktok_shop.webhook.enabled' => true,
        'tiktok_shop.webhook.verify_signature' => true,
        'tiktok_shop.webhook.store_inbox' => true,
        'tiktok_shop.driver' => 'mock',
        'tiktok_shop.enrich_commerce_platform' => false,
        'marketplace.webhook.unified_storage' => false,
        'marketplace.tiktok_on_order.enabled' => true,
    ]);
});

it('increases on_order not physical qty on tiktok awaiting shipment webhook', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    TiktokShopSync::create([
        'shop_ref' => '7495540735365777507',
        'shop_cipher' => '',
        'shop_name' => 'Test',
        'warehouse_id' => $warehouse->id,
    ]);

    $item = Item::factory()->create(['code' => 'SKU-TTS-ON-1']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 40,
        'on_order' => 0,
    ]);

    $body = json_encode([
        'type' => 1,
        'tts_notification_id' => 'on-order-tts-1',
        'shop_id' => '7495540735365777507',
        'timestamp' => 1718305585,
        'data' => [
            'order_id' => 'ONORDER-TEST-1',
            'order_status' => 'AWAITING_SHIPMENT',
            'update_time' => 1718305585,
        ],
    ], JSON_THROW_ON_ERROR);

    $authorization = app(TikTokShopWebhookSignatureVerifier::class)->sign($body);

    $this->call(
        'POST',
        route('tiktok-shop.webhook'),
        [],
        [],
        [],
        [
            'HTTP_AUTHORIZATION' => $authorization,
            'CONTENT_TYPE' => 'application/json',
        ],
        $body,
    )->assertOk();

    $row = WarehouseItem::query()
        ->where('warehouse_id', $warehouse->id)
        ->where('item_id', $item->id)
        ->first();

    expect($row)->not->toBeNull()
        ->and((float) $row->quantity)->toBe(40.0)
        ->and((float) $row->on_order)->toBe(2.0);

    expect(MarketplaceOnOrderReservation::count())->toBe(1);

    $this->call(
        'POST',
        route('tiktok-shop.webhook'),
        [],
        [],
        [],
        [
            'HTTP_AUTHORIZATION' => $authorization,
            'CONTENT_TYPE' => 'application/json',
        ],
        $body,
    )->assertOk();

    $row->refresh();
    expect((float) $row->on_order)->toBe(2.0)
        ->and(MarketplaceOnOrderReservation::count())->toBe(1);
});

it('skips on_order when feature flag is off', function () {
    config(['marketplace.tiktok_on_order.enabled' => false]);

    $warehouse = Addrbook::factory()->warehouse()->create();
    TiktokShopSync::create([
        'shop_ref' => '7495540735365777507',
        'shop_cipher' => '',
        'shop_name' => 'Test',
        'warehouse_id' => $warehouse->id,
    ]);

    $item = Item::factory()->create(['code' => 'SKU-TTS-ON-1']);
    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 40,
        'on_order' => 0,
    ]);

    $body = json_encode([
        'type' => 1,
        'tts_notification_id' => 'on-order-tts-off',
        'shop_id' => '7495540735365777507',
        'timestamp' => 1718305585,
        'data' => [
            'order_id' => 'ONORDER-TEST-2',
            'order_status' => 'AWAITING_SHIPMENT',
        ],
    ], JSON_THROW_ON_ERROR);

    $authorization = app(TikTokShopWebhookSignatureVerifier::class)->sign($body);

    $this->call('POST', route('tiktok-shop.webhook'), [], [], [], [
        'HTTP_AUTHORIZATION' => $authorization,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    $row = WarehouseItem::query()->where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->first();
    expect((float) $row->on_order)->toBe(0.0);
});