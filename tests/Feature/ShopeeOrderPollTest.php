<?php

use App\Models\Jubelioorder;
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
        'services.shopee_order_poll.enabled' => true,
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

it('polls direct ingest shop and creates jubelio placeholder', function () {
    if (! Schema::hasTable('shopee_flexi_shops')) {
        $this->markTestSkipped('shopee_flexi_shops missing');
    }

    ShopeeFlexiShop::query()->create([
        'shop_id' => 8888,
        'invoice_prefix' => 'SPMY',
        'direct_order_ingest' => true,
    ]);

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_list*' => Http::response([
            'error' => '',
            'response' => [
                'more' => false,
                'order_list' => [
                    ['order_sn' => 'MYFLEXI001', 'order_status' => 'PROCESSED', 'update_time' => 1_700_000_000],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => 'MYFLEXI001',
                        'order_status' => 'PROCESSED',
                        'item_list' => [],
                    ],
                ],
            ],
        ]),
    ]);

    $this->artisan('shopee:poll-orders')->assertSuccessful();

    $order = Jubelioorder::where('invoice', 'SPMY-MYFLEXI001')->first();
    expect($order)->not->toBeNull()
        ->and($order->jubelio_order_id)->toBe('shopee-push:MYFLEXI001');
});