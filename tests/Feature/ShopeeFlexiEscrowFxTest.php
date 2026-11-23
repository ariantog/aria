<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubeliosync;
use App\Models\Setting;
use App\Models\ShopeeFlexiShop;
use App\Models\Shopeesync;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Marketplace\ShopeeFlexiOrderAmountResolver;
use App\Services\Marketplace\ShopeeFlexiSellPrefillService;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('shopee-stock-sync', 'web');
    Permission::findOrCreate('transactions-type-sell', 'web');
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
 * @return array{warehouse: Addrbook, customer: Addrbook, item: Item}
 */
function flexiEscrowBaseSetup(float $catalogPrice = 120000): array
{
    ShopeeFlexiShop::query()->create([
        'shop_id' => 9999,
        'invoice_prefix' => 'SPMY',
        'direct_order_ingest' => true,
    ]);

    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'MY Online']);
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create([
        'code' => 'SKU-FLEXI-MY-S',
        'price' => $catalogPrice,
    ]);

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

    return compact('warehouse', 'customer', 'item');
}

function myrOrderDetailResponse(string $orderSn, float $unitMyr, int $qty = 2): array
{
    return [
        'error' => '',
        'response' => [
            'order_list' => [
                [
                    'order_sn' => $orderSn,
                    'order_status' => 'COMPLETED',
                    'currency' => 'MYR',
                    'total_amount' => round($unitMyr * $qty, 2),
                    'update_time' => now()->timestamp,
                    'item_list' => [
                        [
                            'model_sku' => 'SKU-FLEXI-MY-S',
                            'model_quantity_purchased' => $qty,
                            'model_discounted_price' => $unitMyr,
                            'product_location_id' => ['MY-WH-1'],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

it('converts MYR line amounts to IDR when escrow exchange_rate is present', function () {
    flexiEscrowBaseSetup();

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response(
            myrOrderDetailResponse('ESCROW-FX-01', 50.0),
        ),
        'partner.shopeemobile.com/api/v2/payment/get_escrow_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_income' => [
                    'escrow_amount' => 100,
                    'currency' => 'MYR',
                    'aff_currency' => 'MYR',
                    'pri_currency' => 'IDR',
                    'exchange_rate' => 3500,
                    'escrow_amount_pri' => 350000,
                ],
            ],
        ]),
    ]);

    /** @var ShopeeFlexiSellPrefillService $prefill */
    $prefill = app(ShopeeFlexiSellPrefillService::class);
    $result = $prefill->build(9999, 'ESCROW-FX-01');

    expect($result['success'] ?? false)->toBeTrue();
    $items = $result['prefill']['items'] ?? [];
    expect($items)->toHaveCount(1);
    expect((float) $items[0]['price'])->toBe(175000.0)
        ->and((float) $items[0]['subtotal'])->toBe(350000.0);
    expect((string) ($result['prefill']['note'] ?? ''))->toContain('MYR');
});

it('uses catalog IDR prices when MYR order has no exchange_rate', function () {
    $catalogPrice = 120000;
    flexiEscrowBaseSetup($catalogPrice);

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response(
            myrOrderDetailResponse('ESCROW-NO-FX', 50.0),
        ),
        'partner.shopeemobile.com/api/v2/payment/get_escrow_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_income' => [
                    'escrow_amount' => 100,
                    'currency' => 'MYR',
                    'aff_currency' => 'MYR',
                ],
            ],
        ]),
    ]);

    /** @var ShopeeFlexiOrderAmountResolver $resolver */
    $resolver = app(ShopeeFlexiOrderAmountResolver::class);
    $detail = app(ShopeeAdminOpenApiService::class)->getOrderDetail('ESCROW-NO-FX', 9999);
    $orderRow = $detail['response']['order_list'][0];
    $escrow = app(ShopeeAdminOpenApiService::class)->getEscrowDetail('ESCROW-NO-FX', 9999);

    $amounts = $resolver->resolve(9999, 'ESCROW-NO-FX', $orderRow, $escrow, fetchEscrowIfMissing: false);

    expect($amounts['price_source'])->toBe(ShopeeFlexiOrderAmountResolver::PRICE_SOURCE_CATALOG)
        ->and($amounts['lines'][0]['amount'])->toBe(240000.0)
        ->and($amounts['lines'][0]['amount'])->not->toBe(100.0);

    /** @var ShopeeFlexiSellPrefillService $prefill */
    $prefill = app(ShopeeFlexiSellPrefillService::class);
    $result = $prefill->build(9999, 'ESCROW-NO-FX');
    expect($result['success'] ?? false)->toBeFalse();
    expect((string) ($result['message'] ?? ''))->toContain('escrow');
});

it('loads IDR escrow from primary shop with MY-wallet order ref when affiliate escrow is empty', function () {
    flexiEscrowBaseSetup();

    $orderSn = '2611100ME4E93F';

    Http::fake(function (\Illuminate\Http\Client\Request $request) use ($orderSn) {
        $url = $request->url();
        if (str_contains($url, 'get_order_detail')) {
            return Http::response(myrOrderDetailResponse($orderSn, 29.41, 1));
        }

        if (str_contains($url, 'get_escrow_detail')) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $shopId = (int) ($query['shop_id'] ?? 0);
            $ref = (string) ($query['order_sn'] ?? '');

            if ($shopId === 9999) {
                return Http::response([
                    'error' => '',
                    'response' => [],
                ]);
            }

            if ($shopId === 100 && $ref === 'MY-'.$orderSn) {
                return Http::response([
                    'error' => '',
                    'response' => [
                        'order_income' => [
                            'currency' => 'MYR',
                            'aff_currency' => 'MYR',
                            'pri_currency' => 'IDR',
                            'escrow_amount_pri' => 58811,
                        ],
                    ],
                ]);
            }

            return Http::response([
                'error' => '',
                'response' => [],
            ]);
        }

        return Http::response([], 404);
    });

    /** @var ShopeeFlexiSellPrefillService $prefill */
    $prefill = app(ShopeeFlexiSellPrefillService::class);
    $result = $prefill->build(9999, $orderSn);

    expect($result['success'] ?? false)->toBeTrue();
    $items = $result['prefill']['items'] ?? [];
    expect($items)->toHaveCount(1);
    expect((float) $items[0]['subtotal'])->toBe(58811.0)
        ->and((float) $items[0]['price'])->toBe(58811.0);

    /** @var ShopeeFlexiOrderAmountResolver $resolver */
    $resolver = app(ShopeeFlexiOrderAmountResolver::class);
    $detail = app(ShopeeAdminOpenApiService::class)->getOrderDetail($orderSn, 9999);
    $amounts = $resolver->resolve(9999, $orderSn, $detail['response']['order_list'][0]);

    expect($amounts['price_source'])->toBe(ShopeeFlexiOrderAmountResolver::PRICE_SOURCE_ESCROW_IDR)
        ->and($amounts['escrow_fetch']['via_primary_shop'] ?? false)->toBeTrue()
        ->and((int) ($amounts['escrow_fetch']['shop_id'] ?? 0))->toBe(100)
        ->and($amounts['escrow_fetch']['order_sn'] ?? '')->toBe('MY-'.$orderSn)
        ->and($amounts['banner'] ?? '')->toContain('primary ID');
});

it('shows currency escrow snippet and price source on flexi orders lookup', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['shopee-stock-sync']);
    flexiEscrowBaseSetup();

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response(
            myrOrderDetailResponse('UI-MYR-01', 80.0),
        ),
        'partner.shopeemobile.com/api/v2/payment/get_escrow_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_income' => [
                    'currency' => 'MYR',
                    'exchange_rate' => 3200,
                    'aff_currency' => 'MYR',
                    'pri_currency' => 'IDR',
                ],
            ],
        ]),
    ]);

    $response = $this->actingAs($user)
        ->post(route('shopee.flexi.orders.search'), [
            'order_ref' => 'UI-MYR-01',
            'shop_id' => 9999,
            'load_detail' => 1,
        ]);

    $response->assertOk()
        ->assertSee('data-testid="result-order-currency"', false)
        ->assertSee('MYR', false)
        ->assertSee('data-testid="result-escrow-snippet"', false)
        ->assertSee('exchange_rate', false)
        ->assertSee('data-testid="shopee-flexi-price-source"', false)
        ->assertSee('kurs escrow', false);
});