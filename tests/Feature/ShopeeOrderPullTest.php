<?php

use App\Models\Jubelioorder;
use App\Models\MarketplaceWebhookEvent;
use App\Models\Setting;
use App\Models\ShopeeFlexiShop;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopee_admin.partner_id' => '2053195',
        'services.shopee_admin.partner_key' => 'test-admin-partner-key',
        'services.shopee_admin.base_url' => 'https://partner.shopeemobile.com',
        'services.shopee_admin.primary_shop_id' => 100,
        'services.shopee_push.shadow_enabled' => false,
        'services.shopee_push.precreate_jubelio_queue' => true,
        'marketplace.webhook.unified_storage' => true,
    ]);

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
                    '8888' => [
                        'shop_id' => 8888,
                        'access_token' => 'access-my',
                        'refresh_token' => 'refresh-my',
                        'expires_at' => now()->addHours(2)->toIso8601String(),
                    ],
                ],
            ],
        ]
    );
});

it('pulls a single shopee order by ordersn and records unified webhook', function () {
    if (! Schema::hasTable('shopee_flexi_shops')) {
        $this->markTestSkipped('shopee_flexi_shops missing');
    }

    ShopeeFlexiShop::query()->create([
        'shop_id' => 8888,
        'invoice_prefix' => 'SPMY',
        'direct_order_ingest' => true,
    ]);

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => 'MY-PULL-001',
                        'order_status' => 'PROCESSED',
                        'update_time' => 1_700_000_100,
                        'item_list' => [],
                    ],
                ],
            ],
        ]),
    ]);

    $this->artisan('shopee:pull-order', [
        'ordersn' => 'MY-PULL-001',
        '--shop-id' => 8888,
    ])->assertSuccessful();

    $order = Jubelioorder::where('invoice', 'SPMY-MY-PULL-001')->first();
    expect($order)->not->toBeNull();

    $event = MarketplaceWebhookEvent::query()
        ->where('channel', 'shopee')
        ->where('order_ref', 'MY-PULL-001')
        ->first();
    expect($event)->not->toBeNull();
});

it('fails pull when shop_id is missing', function () {
    $this->artisan('shopee:pull-order', ['ordersn' => 'X'])
        ->assertFailed();
});