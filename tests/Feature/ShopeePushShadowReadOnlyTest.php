<?php

use App\Jobs\RecordShopeePushShadowJob;
use App\Models\Item;
use App\Services\Marketplace\ShopeeOrderShadowRecorder;
use App\Models\Jubelioorder;
use App\Models\MarketplaceOrderShadow;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;

/**
 * Shadow pipeline must not post sells, mutate jubelioorders, or touch warehouse stock.
 */
function seedShopeeOAuthForShadow(): void
{
    config([
        'services.shopee_admin.partner_id' => 1,
        'services.shopee_admin.partner_key' => 'test-key',
        'services.shopee_admin.base_url' => 'https://partner.test',
        'services.jubelio.active' => true,
        'services.jubelio.verify_ssl' => false,
    ]);

    \App\Models\Setting::query()->updateOrCreate(
        ['slug' => \App\Services\JubelioService::TOKEN_SETTING_SLUG],
        [
            'group' => 'Jubelio',
            'name' => 'Jubelio Token',
            'value' => [
                'token' => 'test-jubelio-token',
                'expires_at' => now()->addDay()->toDateTimeString(),
            ],
        ],
    );

    \App\Models\Setting::query()->updateOrCreate(
        ['slug' => \App\Services\Shopee\ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_admin',
            'name' => 'Shopee Admin OAuth',
            'value' => [
                'access_token' => 'token',
                'shop_id' => 99,
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
        ],
    );
}

it('recordFromPush retains stub when shopee api returns no order row', function () {
    Http::fake([
        'https://partner.test/*' => Http::response([
            'error' => '',
            'response' => ['order_list' => []],
        ], 200),
    ]);

    config([
        'services.shopee_admin.partner_id' => 1,
        'services.shopee_admin.partner_key' => 'test-key',
        'services.shopee_admin.base_url' => 'https://partner.test',
    ]);

    \App\Models\Setting::query()->updateOrCreate(
        ['slug' => \App\Services\Shopee\ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_admin',
            'name' => 'Shopee Admin OAuth',
            'value' => [
                'access_token' => 'token',
                'shop_id' => 99,
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
        ],
    );

    $recorder = app(ShopeeOrderShadowRecorder::class);
    $recorder->upsertStubFromPush(77, 'APIFAIL1', 'PROCESSED', 3);

    $shadow = $recorder->recordFromPush(77, 'APIFAIL1', 'PROCESSED', 3);

    expect($shadow->external_order_sn)->toBe('APIFAIL1')
        ->and($shadow->compare_status)->toBe(MarketplaceOrderShadow::COMPARE_PENDING)
        ->and($shadow->compare_note)->toBe('shopee_api_fetch_failed');
});

it('push stub upserts pending shadow without api calls', function () {
    $recorder = app(ShopeeOrderShadowRecorder::class);

    $shadow = $recorder->upsertStubFromPush(77, 'STUBONLY1', 'READY_TO_SHIP', 3);

    expect($shadow->compare_status)->toBe(MarketplaceOrderShadow::COMPARE_PENDING)
        ->and($shadow->line_compare_status)->toBe(MarketplaceOrderShadow::LINE_COMPARE_SKIPPED)
        ->and($shadow->channel_status)->toBe('READY_TO_SHIP')
        ->and($shadow->channel_payload)->toBeNull();

    $shadow->update(['compare_status' => MarketplaceOrderShadow::COMPARE_MATCHED]);

    $recorder->upsertStubFromPush(77, 'STUBONLY1', 'SHIPPED', 3);

    $shadow->refresh();
    expect($shadow->compare_status)->toBe(MarketplaceOrderShadow::COMPARE_MATCHED)
        ->and($shadow->channel_status)->toBe('SHIPPED');
});

it('shadow job only writes marketplace_order_shadows', function () {
    Jubelioorder::create([
        'jubelio_order_id' => 'ro-1',
        'source' => 1,
        'invoice' => 'SP-READONLY1',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'status' => 0,
        'run_count' => 0,
    ]);

    $beforeTxns = Transaction::count();
    $beforeJubelio = Jubelioorder::count();

    Http::fake([
        'https://partner.test/*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => 'READONLY1',
                        'order_status' => 'SHIPPED',
                        'total_amount' => 150000,
                        'item_list' => [
                            [
                                'model_sku' => 'SKU-RO-1',
                                'model_quantity_purchased' => 1,
                                'model_discounted_price' => 150000,
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
        'https://api2.jubelio.com/*' => Http::response([
            'items' => [],
        ], 200),
    ]);

    seedShopeeOAuthForShadow();

    $job = new RecordShopeePushShadowJob(99, 'READONLY1', 'SHIPPED', 3);
    $job->handle(app(\App\Services\Marketplace\ShopeeOrderShadowRecorder::class));

    expect(Transaction::count())->toBe($beforeTxns)
        ->and(Jubelioorder::count())->toBe($beforeJubelio)
        ->and(Jubelioorder::query()->where('invoice', 'SP-READONLY1')->value('order_status'))->toBe('SHIPPED');

    $shadow = MarketplaceOrderShadow::query()->where('external_order_sn', 'READONLY1')->first();
    expect($shadow)->not->toBeNull()
        ->and($shadow->line_summary['line_count'])->toBe(1)
        ->and($shadow->line_summary['lines'][0]['sku'])->toBe('SKU-RO-1');
});

it('flags line qty mismatch versus jubelio payload without posting', function () {
    Item::factory()->create([
        'code' => 'SKU-LINE-A',
        'jubelio_item_id' => 88001,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'j-line-1',
        'source' => 1,
        'invoice' => 'SP-LINECMP1',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'status' => 0,
        'run_count' => 0,
    ]);

    $beforeTxns = Transaction::count();

    Http::fake([
        'https://partner.test/*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => 'LINECMP1',
                        'order_status' => 'SHIPPED',
                        'item_list' => [
                            [
                                'model_sku' => 'SKU-LINE-A',
                                'model_quantity_purchased' => 2,
                                'model_discounted_price' => 50000,
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
        'https://api2.jubelio.com/sales/orders/*' => Http::response([
            'items' => [
                [
                    'item_id' => 88001,
                    'item_code' => 'SKU-LINE-A',
                    'qty' => 1,
                    'price' => 50000,
                ],
            ],
        ], 200),
    ]);

    seedShopeeOAuthForShadow();

    $job = new RecordShopeePushShadowJob(99, 'LINECMP1', 'SHIPPED', 3);
    $job->handle(app(\App\Services\Marketplace\ShopeeOrderShadowRecorder::class));

    expect(Transaction::count())->toBe($beforeTxns);

    $shadow = MarketplaceOrderShadow::query()->where('external_order_sn', 'LINECMP1')->first();
    expect($shadow)->not->toBeNull()
        ->and($shadow->compare_status)->toBe(MarketplaceOrderShadow::COMPARE_MISMATCH)
        ->and($shadow->line_compare_status)->toBe(MarketplaceOrderShadow::LINE_COMPARE_MISMATCH)
        ->and($shadow->compare_note)->toContain('SKU-LINE-A');
});

it('matches shopee and jubelio lines when qty aligns', function () {
    Item::factory()->create([
        'code' => 'SKU-LINE-B',
        'jubelio_item_id' => 88002,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'j-line-2',
        'source' => 1,
        'invoice' => 'SP-LINECMP2',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'status' => 0,
        'run_count' => 0,
    ]);

    Http::fake([
        'https://partner.test/*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => 'LINECMP2',
                        'order_status' => 'SHIPPED',
                        'item_list' => [
                            [
                                'model_sku' => 'SKU-LINE-B',
                                'model_quantity_purchased' => 3,
                                'model_discounted_price' => 10000,
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
        'https://api2.jubelio.com/sales/orders/*' => Http::response([
            'items' => [
                [
                    'item_id' => 88002,
                    'qty' => 3,
                    'price' => 10000,
                ],
            ],
        ], 200),
    ]);

    seedShopeeOAuthForShadow();

    $job = new RecordShopeePushShadowJob(99, 'LINECMP2', 'SHIPPED', 3);
    $job->handle(app(\App\Services\Marketplace\ShopeeOrderShadowRecorder::class));

    $shadow = MarketplaceOrderShadow::query()->where('external_order_sn', 'LINECMP2')->first();
    expect($shadow)->not->toBeNull()
        ->and($shadow->compare_status)->toBe(MarketplaceOrderShadow::COMPARE_MATCHED)
        ->and($shadow->line_compare_status)->toBe(MarketplaceOrderShadow::LINE_COMPARE_MATCHED);
});