<?php

use App\Models\Setting;
use App\Services\TikTokShop\TikTokShopOAuthService;
use App\Services\TikTokShop\TikTokShopShippingDocumentService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'tiktok_shop.driver' => 'live',
        'tiktok_shop.app_key' => 'test-app-key',
        'tiktok_shop.app_secret' => 'test-app-secret',
        'tiktok_shop.shop_cipher' => 'test-shop-cipher',
        'tiktok_shop.api_base_url' => 'https://open-api.tiktokglobalshop.com',
        'tiktok_shop.order_api_version' => '202507',
        'tiktok_shop.fulfillment_api_version' => '202309',
    ]);

    Setting::query()->updateOrCreate(
        ['slug' => TikTokShopOAuthService::OAUTH_SETTING_SLUG],
        [
            'group' => 'tiktok_shop',
            'name' => 'TikTok Shop OAuth',
            'value' => [
                'access_token' => 'live-access-token',
                'refresh_token' => 'refresh-token',
                'expires_at' => now()->addHours(2)->toIso8601String(),
            ],
        ]
    );
});

it('ships package then downloads shipping label pdf from doc_url', function (): void {
    $orderId = '5790123456789';
    $packageId = 'PKG-ABC-999';

    Http::fake(function (\Illuminate\Http\Client\Request $request) use ($orderId, $packageId) {
        $url = (string) $request->url();

        if (str_contains($url, '/order/202507/orders')) {
            return Http::response([
                'code' => 0,
                'data' => [
                    'orders' => [
                        [
                            'id' => $orderId,
                            'order_status' => 'AWAITING_SHIPMENT',
                            'packages' => [['id' => $packageId]],
                        ],
                    ],
                ],
            ]);
        }

        if (str_contains($url, '/packages/'.$packageId.'/ship') && $request->method() === 'POST') {
            return Http::response(['code' => 0, 'message' => 'Success']);
        }

        if (str_contains($url, '/packages/'.$packageId.'/shipping_documents')) {
            return Http::response([
                'code' => 0,
                'data' => [
                    'doc_url' => 'https://label.tiktok.test/'.$packageId.'.pdf',
                    'tracking_number' => 'JX123',
                ],
            ]);
        }

        if (str_contains($url, 'label.tiktok.test')) {
            return Http::response('%PDF-1.4 tiktok-label', 200, ['Content-Type' => 'application/pdf']);
        }

        return Http::response(['code' => 999, 'message' => 'unexpected '.$url], 400);
    });

    $service = app(TikTokShopShippingDocumentService::class);

    $service->prepareShippingDocumentsForShop('test-shop-cipher', [$orderId]);

    $pdfs = $service->downloadPdfBatchesForShop('test-shop-cipher', [$orderId], assumeDocumentsPrepared: true);

    expect($pdfs)->toHaveCount(1)
        ->and($pdfs[0])->toContain('%PDF');
});