<?php

use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceWebhookEvent;
use App\Models\Setting;
use App\Models\Shopeesync;
use App\Models\TiktokShopSync;
use App\Models\User;
use App\Services\Marketplace\MarketplaceOrderRefPullService;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'marketplace.webhook.unified_storage' => true,
        'services.shopee_admin.partner_id' => '2053195',
        'services.shopee_admin.partner_key' => 'test-admin-partner-key',
        'services.shopee_admin.base_url' => 'https://partner.shopeemobile.com',
        'services.shopee_push.shadow_enabled' => false,
        'services.shopee_push.precreate_jubelio_queue' => false,
        'tiktok_shop.driver' => 'mock',
    ]);

    $this->user = User::factory()->create();

    Setting::query()->updateOrCreate(
        ['slug' => ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_admin',
            'name' => 'Shopee Admin OAuth',
            'value' => [
                'shop_id' => 100,
                'access_token' => 'access-main',
                'refresh_token' => 'refresh-main',
                'expires_at' => now()->addHours(2)->toIso8601String(),
                'shops' => [
                    '501' => [
                        'shop_id' => 501,
                        'access_token' => 'access-501',
                        'refresh_token' => 'refresh-501',
                        'expires_at' => now()->addHours(2)->toIso8601String(),
                    ],
                ],
            ],
        ]
    );
});

it('renders fetch order page for omnichannel orders permission', function () {
    $this->actingAs($this->user)
        ->get(route('omnichannel.fetch-order.index'))
        ->assertOk()
        ->assertSee('data-testid="omnichannel-fetch-order-page"', false);
});

it('pulls shopee order by ref via service and records unified webhook', function () {
  if (! Schema::hasTable('shopee_syncs')) {
    $this->markTestSkipped('shopee_syncs missing');
  }

    Shopeesync::query()->create([
        'shop_id' => 501,
        'warehouse_id' => 1,
    ]);

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => 'FETCH-SHP-001',
                        'order_status' => 'PROCESSED',
                        'update_time' => 1_700_000_200,
                        'item_list' => [],
                    ],
                ],
            ],
        ]),
    ]);

    $result = app(MarketplaceOrderRefPullService::class)->pull('shopee', 'FETCH-SHP-001', '501');

    expect($result['ok'])->toBeTrue()
        ->and($result['webhook_event'])->toBeTrue();

    expect(MarketplaceWebhookEvent::query()
        ->where('channel', 'shopee')
        ->where('order_ref', 'FETCH-SHP-001')
        ->exists())->toBeTrue();

    expect(MarketplaceChannelOrder::query()
        ->where('channel', 'shopee')
        ->where('order_ref', 'FETCH-SHP-001')
        ->value('order_status'))->toBe('PROCESSED');
});

it('pulls tiktok order by ref via mock api', function () {
    if (! Schema::hasTable('tiktok_shop_syncs')) {
        $this->markTestSkipped('tiktok_shop_syncs missing');
    }

    TiktokShopSync::query()->create([
        'shop_ref' => '7490000000000000123',
        'shop_cipher' => 'MOCK_TTS_SHOP_CIPHER',
        'warehouse_id' => 1,
    ]);

    $result = app(MarketplaceOrderRefPullService::class)->pull(
        'tiktok',
        'ONORDER-PULL-001',
        '7490000000000000123',
    );

    expect($result['ok'])->toBeTrue()
        ->and($result['webhook_event'])->toBeTrue();

    $order = MarketplaceChannelOrder::query()
        ->where('channel', MarketplaceWebhookRecorder::CHANNEL_TIKTOK)
        ->where('order_ref', 'ONORDER-PULL-001')
        ->first();

    expect($order)->not->toBeNull()
        ->and($order->order_status)->toBe('AWAITING_SHIPMENT');
});

it('posts fetch order form and redirects to order detail', function () {
    if (! Schema::hasTable('shopee_syncs')) {
        $this->markTestSkipped('shopee_syncs missing');
    }

    Shopeesync::query()->create([
        'shop_id' => 501,
        'warehouse_id' => 1,
    ]);

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => 'FORM-PULL-001',
                        'order_status' => 'SHIPPED',
                        'update_time' => 1_700_000_300,
                    ],
                ],
            ],
        ]),
    ]);

    $this->actingAs($this->user)
        ->post(route('omnichannel.fetch-order.store'), [
            'channel' => 'shopee',
            'order_ref' => 'FORM-PULL-001',
            'shop_ref' => '501',
        ])
        ->assertRedirect(route('omnichannel.orders.show', [
            'channel' => 'shopee',
            'orderRef' => 'FORM-PULL-001',
        ]));
});