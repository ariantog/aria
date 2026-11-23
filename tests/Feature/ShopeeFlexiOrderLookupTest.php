<?php

use App\Models\Addrbook;
use App\Models\Jubelioorder;
use App\Models\Setting;
use App\Models\ShopeeFlexiShop;
use App\Models\Shopeesync;
use App\Models\User;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('shopee-stock-sync', 'web');

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
                    '7777777' => [
                        'shop_id' => 7777777,
                        'access_token' => 'access-my',
                        'refresh_token' => 'refresh-my',
                        'expires_at' => now()->addHours(2)->toIso8601String(),
                    ],
                    '8888' => [
                        'shop_id' => 8888,
                        'access_token' => 'access-my-2',
                        'refresh_token' => 'refresh-my-2',
                        'expires_at' => now()->addHours(2)->toIso8601String(),
                    ],
                ],
            ],
        ]
    );
});

it('denies guests from flexi order lookup', function () {
    $this->get(route('shopee.flexi.lookup'))->assertRedirect(route('login'));
});

it('denies users without shopee sync permission', function () {
    User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('shopee.flexi.lookup'))
        ->assertForbidden();
});

it('renders lookup form for authorized users', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shopee-stock-sync');

    $this->actingAs($user)
        ->get(route('shopee.flexi.lookup'))
        ->assertOk()
        ->assertSee('data-testid="shopee-flexi-order-lookup"', false);
});

it('validates shop_id and order_ref on lookup', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shopee-stock-sync');

    $this->actingAs($user)
        ->post(route('shopee.flexi.lookup.search'), [])
        ->assertSessionHasErrors(['order_ref', 'shop_id']);
});

it('shows shop_id and order status from mocked get_order_detail', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shopee-stock-sync');

    ShopeeFlexiShop::query()->create([
        'shop_id' => 7777777,
        'invoice_prefix' => 'SPMY',
        'direct_order_ingest' => true,
        'label' => 'MY Test',
    ]);

    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Store BSD']);
    Shopeesync::create([
        'warehouse_id' => $warehouse->id,
        'shop_id' => 0,
        'shopee_location_id' => 'MYLOC1',
        'shopee_warehouse_id' => 9001,
        'shopee_warehouse_name' => 'BSD',
    ]);

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => 'MYLOOKUP99',
                        'order_status' => 'PROCESSED',
                        'region' => 'MY',
                        'item_list' => [
                            ['model_sku' => 'X', 'product_location_id' => ['MYLOC1']],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $response = $this->actingAs($user)
        ->post(route('shopee.flexi.lookup.search'), [
            'order_ref' => 'SPMY-MYLOOKUP99',
            'shop_id' => 7777777,
        ]);

    $response->assertOk()
        ->assertSee('data-testid="result-shop-id"', false)
        ->assertSee('7777777', false)
        ->assertSee('data-testid="result-order-status"', false)
        ->assertSee('PROCESSED', false)
        ->assertSee('Store BSD', false);
});

it('lists flexi mapped shops in shop_id select', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shopee-stock-sync');

    ShopeeFlexiShop::query()->create([
        'shop_id' => 1575873373,
        'invoice_prefix' => 'SPMY',
        'direct_order_ingest' => true,
        'label' => 'Malaysia',
    ]);

    $this->actingAs($user)
        ->get(route('shopee.flexi.lookup'))
        ->assertOk()
        ->assertSee('1575873373 — SPMY — Malaysia', false)
        ->assertSee('data-testid="shopee-lookup-shop-id"', false);
});

it('ingests order via lookup ingest action with same flags as pull command', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shopee-stock-sync');

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
                        'order_sn' => 'UI-INGEST-1',
                        'order_status' => 'PROCESSED',
                        'update_time' => 1_700_000_200,
                        'item_list' => [],
                    ],
                ],
            ],
        ]),
    ]);

    $this->actingAs($user)
        ->post(route('shopee.flexi.lookup.ingest'), [
            'order_ref' => 'SPMY-UI-INGEST-1',
            'shop_id' => 8888,
        ])
        ->assertOk()
        ->assertSee('data-testid="shopee-ingest-result"', false)
        ->assertSee('Ingest selesai', false);

    $order = Jubelioorder::where('invoice', 'SPMY-UI-INGEST-1')->first();
    expect($order)->not->toBeNull();
});

it('surfaces shopee api error from last_error when get_order_detail fails', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shopee-stock-sync');

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response([
            'error' => 'error_param',
            'message' => 'response_optional_fields is invalid',
            'request_id' => 'req-lookup-1',
        ], 200),
    ]);

    $this->actingAs($user)
        ->post(route('shopee.flexi.lookup.search'), [
            'order_ref' => 'TESTSN99',
            'shop_id' => 7777777,
        ])
        ->assertOk()
        ->assertSee('data-testid="shopee-lookup-error"', false)
        ->assertSee('response_optional_fields is invalid', false)
        ->assertSee('error_param', false);
});