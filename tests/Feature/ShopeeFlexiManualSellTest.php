<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubeliosync;
use App\Models\Setting;
use App\Models\ShopeeFlexiShop;
use App\Models\Shopeesync;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
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

function flexiManualSellSetup(): array
{
    ShopeeFlexiShop::query()->create([
        'shop_id' => 9999,
        'invoice_prefix' => 'SPMY',
        'direct_order_ingest' => true,
    ]);

    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'MY Online']);
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create(['code' => 'SKU-FLEXI-MY-S']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => $warehouse->type,
        'item_id' => $item->id,
        'quantity' => 20,
    ]);

    Shopeesync::create([
        'warehouse_id' => $warehouse->id,
        'shop_id' => 9999,
        'shopee_location_id' => 'MY-WH-1',
        'shopee_warehouse_id' => 501,
        'shopee_warehouse_name' => 'MY',
    ]);

    Jubeliosync::create([
        'jubelio_store_id' => 77,
        'jubelio_store_name' => 'MY Store',
        'jubelio_location_id' => 8,
        'jubelio_location_name' => 'Online',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => 'MANUAL-SELL-01',
                        'order_status' => 'SHIPPED',
                        'total_amount' => 150000,
                        'update_time' => now()->timestamp,
                        'item_list' => [
                            [
                                'model_sku' => 'SKU-FLEXI-MY-S',
                                'model_quantity_purchased' => 2,
                                'model_discounted_price' => 75000,
                                'product_location_id' => ['MY-WH-1'],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    return compact('warehouse', 'customer', 'item');
}

it('posts shopee flexi sell manually and redirects to transaction show', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shopee-stock-sync');
    flexiManualSellSetup();

    $response = $this->actingAs($user)
        ->post(route('shopee.flexi.lookup.create-sell'), [
            'order_ref' => 'SPMY-MANUAL-SELL-01',
            'shop_id' => 9999,
        ]);

    $txn = Transaction::query()
        ->where('type', Transaction::TYPE_SELL)
        ->where('invoice', 'SPMY-MANUAL-SELL-01')
        ->first();

    expect($txn)->not->toBeNull()
        ->and((float) $txn->total)->toBe(-150000.0);

    $response->assertRedirect(route('transactions.show', $txn));
});

it('rejects duplicate invoice on flexi manual sell', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shopee-stock-sync');
    $setup = flexiManualSellSetup();

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SPMY-MANUAL-SELL-01',
        'sender_id' => $setup['warehouse']->id,
        'receiver_id' => $setup['customer']->id,
        'total' => -1,
        'status' => Transaction::STATUS_COMPLETED,
    ]);

    $this->actingAs($user)
        ->post(route('shopee.flexi.lookup.create-sell'), [
            'order_ref' => 'MANUAL-SELL-01',
            'shop_id' => 9999,
        ])
        ->assertOk()
        ->assertSee('Transaction sudah ada', false)
        ->assertSee('data-testid="shopee-lookup-flash-error"', false);
});