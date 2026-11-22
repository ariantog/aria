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
    app(PermissionGenerator::class)->generateForModule('MarketplaceWebhook');
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
        ->get(route('omnichannel.orders.index'))
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