<?php

use App\Jobs\RecordShopeePushShadowJob;
use App\Models\Jubelioorder;
use App\Models\MarketplaceOrderShadow;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;

/**
 * Shadow pipeline must not post sells, mutate jubelioorders, or touch warehouse stock.
 */
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
        '*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => 'READONLY1',
                        'order_status' => 'SHIPPED',
                    ],
                ],
            ],
        ], 200),
    ]);

    config([
        'services.shopee_stock.partner_id' => 1,
        'services.shopee_stock.partner_key' => 'test-key',
        'services.shopee_stock.base_url' => 'https://partner.test',
    ]);

    \App\Models\Setting::query()->updateOrCreate(
        ['slug' => \App\Services\Shopee\ShopeeStockOpenApiService::OAUTH_SETTING_SLUG],
        [
            'group' => 'shopee_stock',
            'name' => 'Shopee Stock OAuth',
            'value' => [
                'access_token' => 'token',
                'shop_id' => 99,
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
        ],
    );

    $job = new RecordShopeePushShadowJob(99, 'READONLY1', 'SHIPPED', 3);
    $job->handle(app(\App\Services\Marketplace\ShopeeOrderShadowRecorder::class));

    expect(Transaction::count())->toBe($beforeTxns)
        ->and(Jubelioorder::count())->toBe($beforeJubelio)
        ->and(Jubelioorder::query()->where('invoice', 'SP-READONLY1')->value('order_status'))->toBe('SHIPPED')
        ->and(MarketplaceOrderShadow::query()->where('external_order_sn', 'READONLY1')->exists())->toBeTrue();
});