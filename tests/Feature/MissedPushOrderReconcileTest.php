<?php

use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceMissedPushGap;
use App\Models\Setting;
use App\Models\Shopeesync;
use App\Models\User;
use App\Services\Marketplace\ShopeeMissedPushReconcileService;
use App\Services\PermissionGenerator;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    app(PermissionGenerator::class)->generateForModule('Omnichannel');
    $this->admin = User::factory()->create();

    config([
        'services.shopee_admin.partner_id' => '2053195',
        'services.shopee_admin.partner_key' => 'test-admin-partner-key',
        'services.shopee_admin.base_url' => 'https://partner.shopeemobile.com',
        'services.shopee_admin.primary_shop_id' => 100,
        'services.shopee_push.shadow_enabled' => false,
        'services.shopee_push.precreate_jubelio_queue' => false,
        'marketplace.webhook.unified_storage' => true,
        'marketplace.missed_push_reconcile.enabled' => true,
        'marketplace.missed_push_reconcile.shopee.order_statuses' => ['PROCESSED'],
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
                    '5555' => [
                        'shop_id' => 5555,
                        'access_token' => 'access-5555',
                        'refresh_token' => 'refresh-5555',
                        'expires_at' => now()->addHours(2)->toIso8601String(),
                    ],
                ],
            ],
        ]
    );
});

it('skips ingest when marketplace_channel_orders already has the order (anti-join)', function () {
    if (! Schema::hasTable('shopee_syncs') || ! Schema::hasTable('marketplace_missed_push_gaps')) {
        $this->markTestSkipped('shopee_syncs or marketplace_missed_push_gaps missing');
    }

    Shopeesync::factory()->create([
        'shop_id' => 5555,
        'warehouse_id' => 10,
    ]);

    createMarketplaceChannelOrder([
        'channel' => 'shopee',
        'order_ref' => 'KNOWN-ORDER-001',
        'shop_ref' => '5555',
        'order_status' => 'PROCESSED',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_list*' => Http::response([
            'error' => '',
            'response' => [
                'more' => false,
                'order_list' => [
                    ['order_sn' => 'KNOWN-ORDER-001', 'order_status' => 'PROCESSED', 'update_time' => 1_700_000_000],
                    ['order_sn' => 'MISSING-ORDER-002', 'order_status' => 'PROCESSED', 'update_time' => 1_700_000_100],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => 'MISSING-ORDER-002',
                        'order_status' => 'PROCESSED',
                        'item_list' => [],
                    ],
                ],
            ],
        ]),
    ]);

    $result = app(ShopeeMissedPushReconcileService::class)->reconcile(5555, 7);

    expect($result['orders_seen'])->toBe(2)
        ->and($result['skipped'])->toBe(1)
        ->and($result['ingested'])->toBe(1);

    expect(MarketplaceChannelOrder::query()->where('order_ref', 'MISSING-ORDER-002')->exists())->toBeTrue();
    expect(MarketplaceMissedPushGap::query()->where('order_ref', 'MISSING-ORDER-002')->whereNotNull('ingested_at')->exists())->toBeTrue();
    expect(MarketplaceMissedPushGap::query()->where('order_ref', 'KNOWN-ORDER-001')->exists())->toBeFalse();
});

it('reconcile command no-ops when feature flag disabled', function () {
    config(['marketplace.missed_push_reconcile.enabled' => false]);

    $this->artisan('marketplace:reconcile-missed-orders')
        ->assertSuccessful()
        ->expectsOutputToContain('MARKETPLACE_MISSED_PUSH_RECONCILE_ENABLED=false');
});

it('renders missed push reconcile queue view', function () {
    if (! Schema::hasTable('marketplace_missed_push_gaps')) {
        $this->markTestSkipped('marketplace_missed_push_gaps missing');
    }

    MarketplaceMissedPushGap::query()->create([
        'channel' => 'shopee',
        'shop_ref' => '5555',
        'order_ref' => 'GAP-QUEUE-001',
        'order_status' => 'READY_TO_SHIP',
        'detected_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('omnichannel.orders.index', ['view' => 'missed_push']))
        ->assertOk()
        ->assertSee('data-testid="omnichannel-missed-push-table"', false)
        ->assertSee('GAP-QUEUE-001', false);
});