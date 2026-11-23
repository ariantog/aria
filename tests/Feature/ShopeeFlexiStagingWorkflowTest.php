<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubeliosync;
use App\Models\Setting;
use App\Models\ShopeeFlexiShop;
use App\Models\Shopeesync;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Marketplace\OmnichannelSettingStore;
use App\Services\Marketplace\ShopeeFlexiSellPrefillService;
use App\Services\Marketplace\ShopeeFlexiStagingWarehouse;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('shopee-stock-sync', 'web');
    Permission::findOrCreate('transactions-type-sell', 'web');
    Permission::findOrCreate('transactions-type-move', 'web');
    Permission::findOrCreate('transactions-create', 'web');

    config([
        'services.shopee_admin.partner_id' => '2053195',
        'services.shopee_admin.partner_key' => 'test-admin-partner-key',
        'services.shopee_admin.base_url' => 'https://partner.shopeemobile.com',
        'services.shopee_admin.primary_shop_id' => 100,
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
                    '9999' => [
                        'shop_id' => 9999,
                        'access_token' => 'access-flexi',
                        'refresh_token' => 'refresh-flexi',
                        'expires_at' => now()->addHours(2)->toIso8601String(),
                    ],
                ],
            ],
        ]
    );
});

/**
 * @return array{pickup: Addrbook, staging: Addrbook, customer: Addrbook, item: Item}
 */
function flexiStagingWorkflowSetup(): array
{
    ShopeeFlexiShop::query()->create([
        'shop_id' => 9999,
        'invoice_prefix' => 'SPMY',
        'direct_order_ingest' => true,
    ]);

    $pickup = Addrbook::factory()->warehouse()->create(['name' => 'MY Pickup']);
    $staging = Addrbook::factory()->warehouse()->create([
        'name' => 'FLEXI Staging',
        'arrangement_enabled' => true,
    ]);
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create(['code' => 'SKU-FLEXI-STG-S', 'price' => 99000]);

    app(OmnichannelSettingStore::class)->set(
        OmnichannelSettingStore::KEY_FLEXI_STAGING_WAREHOUSE_ID,
        (string) $staging->id,
    );
    config(['shopee_flexi.staging_warehouse_id' => 0]);

    foreach ([$pickup, $staging] as $wh) {
        WarehouseItem::create([
            'warehouse_id' => $wh->id,
            'warehouse_type' => $wh->type,
            'item_id' => $item->id,
            'quantity' => 50,
        ]);
    }

    Shopeesync::create([
        'warehouse_id' => $pickup->id,
        'shop_id' => 9999,
        'shopee_location_id' => 'MY-WH-STG',
        'shopee_warehouse_id' => 701,
        'shopee_warehouse_name' => 'MY',
    ]);

    Jubeliosync::create([
        'jubelio_store_id' => 77,
        'jubelio_store_name' => 'MY Store',
        'jubelio_location_id' => 8,
        'jubelio_location_name' => 'Online',
        'warehouse_id' => $pickup->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    return compact('pickup', 'staging', 'customer', 'item');
}

function flexiStagingOrderDetail(string $orderSn, string $status = 'PROCESSED'): array
{
    return [
        'error' => '',
        'response' => [
            'order_list' => [
                [
                    'order_sn' => $orderSn,
                    'order_status' => $status,
                    'currency' => 'MYR',
                    'total_amount' => 200,
                    'update_time' => now()->timestamp,
                    'item_list' => [
                        [
                            'model_sku' => 'SKU-FLEXI-STG-S',
                            'model_quantity_purchased' => 2,
                            'model_discounted_price' => 100,
                            'product_location_id' => ['MY-WH-STG'],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

function flexiStagingEscrowResponse(float $rate = 3600): array
{
    return [
        'error' => '',
        'response' => [
            'order_income' => [
                'currency' => 'MYR',
                'aff_currency' => 'MYR',
                'pri_currency' => 'IDR',
                'exchange_rate' => $rate,
                'escrow_amount' => 200,
            ],
        ],
    ];
}

it('redirects move to staging to prefilled move create form', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['shopee-stock-sync', 'transactions-type-move', 'transactions-create']);
    $setup = flexiStagingWorkflowSetup();

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response(
            flexiStagingOrderDetail('MOVE-STG-01', 'PROCESSED'),
        ),
        'partner.shopeemobile.com/api/v2/payment/get_escrow_detail*' => Http::response(flexiStagingEscrowResponse()),
    ]);

    $response = $this->actingAs($user)
        ->post(route('shopee.flexi.orders.move-to-staging'), [
            'order_ref' => 'MOVE-STG-01',
            'shop_id' => 9999,
        ]);

    $response->assertRedirect(route('transactions.create', ['type' => 'move']));

    $follow = $this->actingAs($user)->get($response->headers->get('Location'));
    $follow->assertOk()
        ->assertSee('SKU-FLEXI-STG-S', false)
        ->assertSee((string) $setup['pickup']->name, false)
        ->assertSee((string) $setup['staging']->name, false);
});

it('blocks sell button when order status is not completed', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['shopee-stock-sync']);
    flexiStagingWorkflowSetup();

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response(
            flexiStagingOrderDetail('NOT-DONE-01', 'SHIPPED'),
        ),
        'partner.shopeemobile.com/api/v2/payment/get_escrow_detail*' => Http::response(flexiStagingEscrowResponse()),
    ]);

    $this->actingAs($user)
        ->post(route('shopee.flexi.orders.search'), [
            'order_ref' => 'NOT-DONE-01',
            'shop_id' => 9999,
            'load_detail' => 1,
        ])
        ->assertOk()
        ->assertDontSee('data-testid="shopee-flexi-create-sell"', false)
        ->assertSee('COMPLETED', false)
        ->assertSee('data-testid="shopee-manual-sell-block"', false);
});

it('blocks sell button when escrow is missing', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['shopee-stock-sync']);
    flexiStagingWorkflowSetup();

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response(
            flexiStagingOrderDetail('NO-ESCROW-01', 'COMPLETED'),
        ),
        'partner.shopeemobile.com/api/v2/payment/get_escrow_detail*' => Http::response(null, 500),
    ]);

    $this->actingAs($user)
        ->post(route('shopee.flexi.orders.search'), [
            'order_ref' => 'NO-ESCROW-01',
            'shop_id' => 9999,
            'load_detail' => 1,
        ])
        ->assertOk()
        ->assertDontSee('data-testid="shopee-flexi-create-sell"', false)
        ->assertSee('Escrow', false);
});

it('falls back to env staging warehouse id when omnichannel setting is empty', function () {
    $staging = Addrbook::factory()->warehouse()->create(['name' => 'Env Fallback Staging']);
    config(['shopee_flexi.staging_warehouse_id' => $staging->id]);
    app(OmnichannelSettingStore::class)->set(
        OmnichannelSettingStore::KEY_FLEXI_STAGING_WAREHOUSE_ID,
        '0',
    );

    $service = app(ShopeeFlexiStagingWarehouse::class);

    expect($service->configuredId())->toBe($staging->id);
});

it('prefers omnichannel setting over env for staging warehouse id', function () {
    $dbStaging = Addrbook::factory()->warehouse()->create(['name' => 'DB Staging']);
    $envStaging = Addrbook::factory()->warehouse()->create(['name' => 'Env Staging']);
    app(OmnichannelSettingStore::class)->set(
        OmnichannelSettingStore::KEY_FLEXI_STAGING_WAREHOUSE_ID,
        (string) $dbStaging->id,
    );
    config(['shopee_flexi.staging_warehouse_id' => $envStaging->id]);

    expect(app(ShopeeFlexiStagingWarehouse::class)->configuredId())->toBe($dbStaging->id);
});

it('allows sell when completed and escrow ok with staging as sender', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['shopee-stock-sync', 'transactions-type-sell', 'transactions-create']);
    $setup = flexiStagingWorkflowSetup();

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response(
            flexiStagingOrderDetail('SELL-STG-01', 'COMPLETED'),
        ),
        'partner.shopeemobile.com/api/v2/payment/get_escrow_detail*' => Http::response(flexiStagingEscrowResponse(3500)),
    ]);

    $this->actingAs($user)
        ->post(route('shopee.flexi.orders.search'), [
            'order_ref' => 'SELL-STG-01',
            'shop_id' => 9999,
            'load_detail' => 1,
        ])
        ->assertOk()
        ->assertSee('data-testid="shopee-flexi-create-sell"', false);

    /** @var ShopeeFlexiSellPrefillService $prefill */
    $prefill = app(ShopeeFlexiSellPrefillService::class);
    $result = $prefill->build(9999, 'SELL-STG-01');

    expect($result['success'] ?? false)->toBeTrue();
    expect((int) ($result['prefill']['sender_id'] ?? 0))->toBe($setup['staging']->id)
        ->and((int) ($result['prefill']['receiver_id'] ?? 0))->toBe($setup['customer']->id);
});