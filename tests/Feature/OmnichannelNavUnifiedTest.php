<?php

use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceWebhookEvent;
use App\Models\Omnichannel;
use App\Models\User;
use App\Services\Marketplace\OmnichannelSettingStore;
use App\Services\PermissionGenerator;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    app(PermissionGenerator::class)->generateForModule('Omnichannel');
});

it('renders sidebar omnichannel group with child links for superadmin', function () {
    $admin = User::factory()->create();
    expect($admin->is_superadmin)->toBeTrue();

    $this->actingAs($admin)
        ->get(route('omnichannel.orders.index'))
        ->assertOk()
        ->assertSee('data-testid="omnichannel-orders-page"', false)
        ->assertSee(route('omnichannel.orders.index'), false)
        ->assertDontSee('Webhook Inbox', false)
        ->assertSee(route('omnichannel.index'), false)
        ->assertSee(route('omnichannel.settings.index'), false);
});

it('forbids omnichannel orders without permission', function () {
    $user = User::factory()->create(['id' => 50]);
    Permission::findOrCreate(Omnichannel::getPermissions()['orders'], 'web');

    $this->actingAs($user)
        ->get(route('omnichannel.orders.index'))
        ->assertForbidden();
});

it('renders orders list without calling shopee admin api', function () {
    $admin = User::factory()->create();
    $mock = Mockery::mock(ShopeeAdminOpenApiService::class);
    $mock->shouldNotReceive('buildAuthorizeUrl');
    $mock->shouldNotReceive('getOrderDetail');
    $this->instance(ShopeeAdminOpenApiService::class, $mock);

    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => 'LIST-SN-1',
        'shop_ref' => '100',
        'order_status' => 'PROCESSED',
        'last_event_at' => now(),
        'first_received_at' => now()->subHour(),
    ]);

    $this->actingAs($admin)
        ->get(route('omnichannel.orders.index', ['view' => 'orders']))
        ->assertOk()
        ->assertSee('LIST-SN-1', false)
        ->assertSee('data-testid="omnichannel-orders-table"', false);
});

it('returns order detail json from stored data only', function () {
    $admin = User::factory()->create();

    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => 'DETAIL-SN-9',
        'shop_ref' => '200',
        'order_status' => 'SHIPPED',
        'last_event_at' => now(),
        'first_received_at' => now()->subMinutes(5),
    ]);

    MarketplaceWebhookEvent::query()->create([
        'channel' => 'shopee',
        'idempotency_key' => 'shopee:200:3:DETAIL-SN-9:1',
        'event_type' => '3',
        'shop_ref' => '200',
        'order_ref' => 'DETAIL-SN-9',
        'order_status' => 'SHIPPED',
        'signature_ok' => true,
        'payload' => ['code' => 3],
        'received_at' => now(),
    ]);

    $this->actingAs($admin)
        ->getJson(route('omnichannel.orders.show', ['channel' => 'shopee', 'orderRef' => 'DETAIL-SN-9']))
        ->assertOk()
        ->assertJsonPath('order_ref', 'DETAIL-SN-9')
        ->assertJsonCount(1, 'webhook_events');
});

it('enriches tiktok order detail with commerce_platform when driver is mock', function () {
    config(['tiktok_shop.driver' => 'mock']);

    MarketplaceChannelOrder::query()->create([
        'channel' => 'tiktok',
        'order_ref' => 'TOKO-ENRICH-1',
        'shop_ref' => 'shop-x',
        'order_status' => 'UNPAID',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->getJson(route('omnichannel.orders.show', ['channel' => 'tiktok', 'orderRef' => 'TOKO-ENRICH-1']))
        ->assertOk()
        ->assertJsonPath('commerce_platform', 'TOKOPEDIA');

    expect(MarketplaceChannelOrder::query()->where('order_ref', 'TOKO-ENRICH-1')->value('commerce_platform'))
        ->toBe('TOKOPEDIA');
});

it('loads tiktok line summary from mock get order detail on orders expand', function () {
    config([
        'tiktok_shop.driver' => 'mock',
        'tiktok_shop.enrich_commerce_platform' => false,
    ]);

    MarketplaceChannelOrder::query()->create([
        'channel' => 'tiktok',
        'order_ref' => 'ONORDER-DETAIL-1',
        'shop_ref' => '7495540735365777507',
        'order_status' => 'AWAITING_SHIPMENT',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->getJson(route('omnichannel.orders.show', ['channel' => 'tiktok', 'orderRef' => 'ONORDER-DETAIL-1']))
        ->assertOk()
        ->assertJsonPath('api_detail_loaded', true)
        ->assertJsonPath('line_summary.0.sku', 'SKU-TTS-ON-1')
        ->assertJsonPath('line_summary.0.qty', 2)
        ->assertJsonPath('channel_order.status', 'AWAITING_SHIPMENT');

    $this->actingAs($admin)
        ->get(route('omnichannel.orders.show', ['channel' => 'tiktok', 'orderRef' => 'ONORDER-DETAIL-1']))
        ->assertOk()
        ->assertSee('data-testid="omnichannel-tiktok-line-table"', false)
        ->assertSee('SKU-TTS-ON-1', false);
});

it('reports tiktok api_error when live credentials are missing', function () {
    config([
        'tiktok_shop.driver' => 'live',
        'tiktok_shop.app_key' => '',
        'tiktok_shop.enrich_commerce_platform' => false,
    ]);

    MarketplaceChannelOrder::query()->create([
        'channel' => 'tiktok',
        'order_ref' => 'LIVE-MISSING-CRED',
        'shop_ref' => 'shop-y',
        'order_status' => 'UNPAID',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->getJson(route('omnichannel.orders.show', ['channel' => 'tiktok', 'orderRef' => 'LIVE-MISSING-CRED']))
        ->assertOk()
        ->assertJsonPath('api_detail_loaded', false)
        ->assertJson(fn ($json) => $json->where('api_error', fn ($msg) => is_string($msg) && $msg !== '')->etc());
});

it('forbids omnichannel settings without permission', function () {
    $user = User::factory()->create(['id' => 51]);
    Permission::findOrCreate(Omnichannel::getPermissions()['settings'], 'web');

    $this->actingAs($user)
        ->get(route('omnichannel.settings.index'))
        ->assertForbidden();
});

it('saves shopee low stock threshold with settings-edit permission', function () {
    $user = User::factory()->create(['id' => 52]);
    $user->givePermissionTo([
        Omnichannel::getPermissions()['settings'],
        Omnichannel::getPermissions()['settings-edit'],
    ]);

    $this->actingAs($user)
        ->post(route('omnichannel.settings.low-stock.update'), [
            'shopee_low_stock_threshold' => 12,
        ])
        ->assertRedirect(route('omnichannel.settings.index', ['tab' => 'low-stock']));

    $store = app(OmnichannelSettingStore::class);
    expect($store->getInt(OmnichannelSettingStore::KEY_SHOPEE_LOW_STOCK_THRESHOLD))->toBe(12);
});

it('forbids low stock save without settings-edit permission', function () {
    $user = User::factory()->create(['id' => 53]);
    $user->givePermissionTo(Omnichannel::getPermissions()['settings']);

    $this->actingAs($user)
        ->post(route('omnichannel.settings.low-stock.update'), [
            'shopee_low_stock_threshold' => 5,
        ])
        ->assertForbidden();
});