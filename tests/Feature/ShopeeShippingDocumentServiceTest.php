<?php

use App\Models\Setting;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use App\Services\Shopee\ShopeeShippingDocumentService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.shopee_admin.partner_id' => '2053195',
        'services.shopee_admin.partner_key' => 'test-admin-partner-key',
        'services.shopee_admin.base_url' => 'https://partner.shopeemobile.com',
        'services.shopee_admin.primary_shop_id' => 100,
        'services.shopee_admin.shipping_document_type' => 'NORMAL_AIR_WAYBILL',
        'services.shopee_admin.awb_tracking_wait_seconds' => 1,
        'services.shopee_admin.awb_document_poll_seconds' => 30,
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
            ],
        ]
    );
});

it('treats probe should_print_first as pending and completes create download flow', function () {
    $orderSn = 'SVC-PROBE-FIRST';

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => $orderSn,
                        'order_status' => 'READY_TO_SHIP',
                        'tracking_number' => 'SPX-SVC-1',
                        'package_list' => [],
                    ],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_tracking_number*' => Http::response([
            'error' => '',
            'response' => ['tracking_number' => 'SPX-SVC-1'],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_document_result*' => Http::sequence()
            ->push([
                'error' => 'logistics.shipping_document_should_print_first',
                'message' => 'The package should print first.',
            ])
            ->push([
                'error' => '',
                'response' => [
                    'result_list' => [
                        ['order_sn' => $orderSn, 'status' => 'READY'],
                    ],
                ],
            ]),
        'partner.shopeemobile.com/api/v2/logistics/create_shipping_document*' => Http::response([
            'error' => '',
            'response' => [
                'result_list' => [
                    ['order_sn' => $orderSn, 'fail_error' => ''],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/download_shipping_document*' => Http::response(
            '%PDF-1.4 service-layer',
            200,
            ['Content-Type' => 'application/pdf'],
        ),
    ]);

    $service = app(ShopeeShippingDocumentService::class);
    $pdfs = $service->downloadPdfBatchesForShop(100, [$orderSn]);

    expect($pdfs)->toHaveCount(1)
        ->and($pdfs[0])->toContain('%PDF');
});

it('retries arrange and create when poll returns should_print_first on ready to ship order', function () {
    $orderSn = '2611245VU2MSJP';

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => $orderSn,
                        'order_status' => 'READY_TO_SHIP',
                        'tracking_number' => 'SPX-2611245',
                        'package_list' => [],
                    ],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_parameter*' => Http::response([
            'error' => '',
            'response' => [
                'info_needed' => [
                    'dropoff' => [],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/ship_order*' => Http::response([
            'error' => '',
            'response' => [],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_tracking_number*' => Http::response([
            'error' => '',
            'response' => ['tracking_number' => 'SPX-2611245'],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_document_result*' => Http::sequence()
            ->push([
                'error' => 'logistics.shipping_document_should_print_first',
                'message' => 'The package should print first.',
            ])
            ->push([
                'error' => 'logistics.shipping_document_should_print_first',
                'message' => 'The package should print first.',
                'request_id' => 'poll-retry-should-print',
            ])
            ->push([
                'error' => '',
                'response' => [
                    'result_list' => [
                        ['order_sn' => $orderSn, 'status' => 'READY'],
                    ],
                ],
            ]),
        'partner.shopeemobile.com/api/v2/logistics/create_shipping_document*' => Http::response([
            'error' => '',
            'response' => [
                'result_list' => [
                    ['order_sn' => $orderSn, 'fail_error' => ''],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/download_shipping_document*' => Http::response(
            '%PDF-1.4 poll-retry-flow',
            200,
            ['Content-Type' => 'application/pdf'],
        ),
    ]);

    $service = app(ShopeeShippingDocumentService::class);
    $pdfs = $service->downloadPdfBatchesForShop(100, [$orderSn]);

    expect($pdfs)->toHaveCount(1)
        ->and($pdfs[0])->toContain('%PDF');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v2/logistics/ship_order');
    });
});

it('omits package_number on ship_order for unsplit orders with a single package_list row', function () {
    $orderSn = '2611245VU2MSJP';
    $packageNumber = 'OFG-i947sh1';

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => $orderSn,
                        'order_status' => 'PROCESSED',
                        'tracking_number' => '',
                        'package_list' => [
                            [
                                'package_number' => $packageNumber,
                                'tracking_number' => '',
                            ],
                        ],
                    ],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_tracking_number*' => Http::response([
            'error' => '',
            'response' => [
                'tracking_number' => 'SPX-UNSPLIT',
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_parameter*' => Http::response([
            'error' => '',
            'response' => [
                'info_needed' => [
                    'dropoff' => [],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/ship_order*' => Http::response([
            'error' => '',
            'response' => [],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_document_result*' => Http::sequence()
            ->push([
                'error' => 'logistics.shipping_document_should_print_first',
                'message' => 'The package should print first.',
            ])
            ->push([
                'error' => '',
                'response' => [
                    'result_list' => [
                        ['order_sn' => $orderSn, 'status' => 'READY'],
                    ],
                ],
            ]),
        'partner.shopeemobile.com/api/v2/logistics/create_shipping_document*' => Http::response([
            'error' => '',
            'response' => [
                'result_list' => [
                    ['order_sn' => $orderSn, 'fail_error' => ''],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/download_shipping_document*' => Http::response(
            '%PDF-1.4 unsplit-ship',
            200,
            ['Content-Type' => 'application/pdf'],
        ),
    ]);

    $service = app(ShopeeShippingDocumentService::class);
    $pdfs = $service->downloadPdfBatchesForShop(100, [$orderSn]);

    expect($pdfs)->toHaveCount(1);

    $shipRequests = collect(Http::recorded())->filter(
        fn ($pair) => str_contains($pair[0]->url(), '/api/v2/logistics/ship_order')
    );
    expect($shipRequests)->not->toBeEmpty();
    foreach ($shipRequests as [$request]) {
        $body = $request->data();
        expect($body)->not->toHaveKey('package_number');
    }

    $parameterRequests = collect(Http::recorded())->filter(
        fn ($pair) => str_contains($pair[0]->url(), '/api/v2/logistics/get_shipping_parameter')
    );
    expect($parameterRequests)->not->toBeEmpty();
    foreach ($parameterRequests as [$request]) {
        expect($request->url())->not->toContain('package_number');
    }
});

it('includes package_number on ship_order for split multi-package orders', function () {
    $orderSn = 'SPLIT-2PKG';
    $pkgA = 'PKG-SPLIT-A';
    $pkgB = 'PKG-SPLIT-B';

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => $orderSn,
                        'order_status' => 'PROCESSED',
                        'tracking_number' => '',
                        'package_list' => [
                            ['package_number' => $pkgA, 'tracking_number' => 'TRK-A'],
                            ['package_number' => $pkgB, 'tracking_number' => 'TRK-B'],
                        ],
                    ],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_parameter*' => Http::response([
            'error' => '',
            'response' => [
                'info_needed' => [
                    'dropoff' => [],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/ship_order*' => Http::response([
            'error' => '',
            'response' => [],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_tracking_number*' => Http::response([
            'error' => '',
            'response' => ['tracking_number' => 'TRK-SPLIT'],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_document_result*' => Http::sequence()
            ->push([
                'error' => 'logistics.shipping_document_should_print_first',
                'message' => 'The package should print first.',
            ])
            ->push([
                'error' => 'logistics.shipping_document_should_print_first',
                'message' => 'The package should print first.',
            ])
            ->push([
                'error' => '',
                'response' => [
                    'result_list' => [
                        ['order_sn' => $orderSn, 'package_number' => $pkgA, 'status' => 'READY'],
                        ['order_sn' => $orderSn, 'package_number' => $pkgB, 'status' => 'READY'],
                    ],
                ],
            ]),
        'partner.shopeemobile.com/api/v2/logistics/create_shipping_document*' => Http::response([
            'error' => '',
            'response' => [
                'result_list' => [
                    ['order_sn' => $orderSn, 'package_number' => $pkgA, 'fail_error' => ''],
                    ['order_sn' => $orderSn, 'package_number' => $pkgB, 'fail_error' => ''],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/download_shipping_document*' => Http::response(
            '%PDF-1.4 split-ship',
            200,
            ['Content-Type' => 'application/pdf'],
        ),
    ]);

    $service = app(ShopeeShippingDocumentService::class);
    $service->downloadPdfBatchesForShop(100, [$orderSn]);

    $shipRequests = collect(Http::recorded())->filter(
        fn ($pair) => str_contains($pair[0]->url(), '/api/v2/logistics/ship_order')
    );
    expect($shipRequests)->not->toBeEmpty();
    foreach ($shipRequests as [$request]) {
        expect($request->data())->toHaveKey('package_number');
    }
});

it('omits tracking_number on create_shipping_document when get_tracking_number stays empty', function () {
    $orderSn = '2611245WYMT9ET';

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => $orderSn,
                        'order_status' => 'READY_TO_SHIP',
                        'tracking_number' => 'SPX-STALE-INVALID',
                        'package_list' => [],
                    ],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_tracking_number*' => Http::response([
            'error' => '',
            'response' => ['tracking_number' => ''],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_document_result*' => Http::sequence()
            ->push([
                'error' => 'logistics.shipping_document_should_print_first',
                'message' => 'The package should print first.',
            ])
            ->push([
                'error' => '',
                'response' => [
                    'result_list' => [
                        ['order_sn' => $orderSn, 'status' => 'READY'],
                    ],
                ],
            ]),
        'partner.shopeemobile.com/api/v2/logistics/create_shipping_document*' => Http::response([
            'error' => '',
            'response' => [
                'result_list' => [
                    ['order_sn' => $orderSn, 'fail_error' => ''],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/download_shipping_document*' => Http::response(
            '%PDF-1.4 no-tracking-create',
            200,
            ['Content-Type' => 'application/pdf'],
        ),
    ]);

    $service = app(ShopeeShippingDocumentService::class);
    $pdfs = $service->downloadPdfBatchesForShop(100, [$orderSn]);

    expect($pdfs)->toHaveCount(1);

    $createRequests = collect(Http::recorded())->filter(
        fn ($pair) => str_contains($pair[0]->url(), '/api/v2/logistics/create_shipping_document')
    );
    expect($createRequests)->not->toBeEmpty();
    foreach ($createRequests as [$request]) {
        $orderList = $request->data()['order_list'] ?? [];
        foreach ($orderList as $item) {
            expect($item)->not->toHaveKey('tracking_number');
        }
    }
});

it('retries create without tracking when Shopee returns tracking_number_invalid', function () {
    $orderSn = '2611245WYMT9ET';

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => $orderSn,
                        'order_status' => 'READY_TO_SHIP',
                        'tracking_number' => 'SPX-STALE-INVALID',
                        'package_list' => [],
                    ],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_tracking_number*' => Http::response([
            'error' => '',
            'response' => ['tracking_number' => 'SPX-STALE-INVALID'],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_document_result*' => Http::sequence()
            ->push([
                'error' => 'logistics.shipping_document_should_print_first',
                'message' => 'The package should print first.',
            ])
            ->push([
                'error' => '',
                'response' => [
                    'result_list' => [
                        ['order_sn' => $orderSn, 'status' => 'READY'],
                    ],
                ],
            ]),
        'partner.shopeemobile.com/api/v2/logistics/create_shipping_document*' => Http::sequence()
            ->push([
                'error' => 'common.batch_api_all_failed',
                'message' => 'Failed, please check result_list for more details.',
                'response' => [
                    'result_list' => [
                        [
                            'order_sn' => $orderSn,
                            'fail_error' => 'logistics.tracking_number_invalid',
                            'fail_message' => 'The tracking number is invalid.',
                        ],
                    ],
                ],
            ])
            ->push([
                'error' => '',
                'response' => [
                    'result_list' => [
                        ['order_sn' => $orderSn, 'fail_error' => ''],
                    ],
                ],
            ]),
        'partner.shopeemobile.com/api/v2/logistics/download_shipping_document*' => Http::response(
            '%PDF-1.4 tracking-invalid-retry',
            200,
            ['Content-Type' => 'application/pdf'],
        ),
    ]);

    $service = app(ShopeeShippingDocumentService::class);
    $pdfs = $service->downloadPdfBatchesForShop(100, [$orderSn]);

    expect($pdfs)->toHaveCount(1)->and($pdfs[0])->toContain('%PDF');

    $createRequests = collect(Http::recorded())->filter(
        fn ($pair) => str_contains($pair[0]->url(), '/api/v2/logistics/create_shipping_document')
    );
    expect($createRequests)->toHaveCount(2);
    $firstBody = $createRequests->first()[0]->data()['order_list'][0] ?? [];
    expect($firstBody)->toHaveKey('tracking_number');
    foreach ($createRequests->slice(1) as [$request]) {
        $orderList = $request->data()['order_list'] ?? [];
        foreach ($orderList as $item) {
            expect($item)->not->toHaveKey('tracking_number');
        }
    }
});

it('polls and downloads when create still returns tracking_number_invalid', function () {
    $orderSn = '2611245WYMT9ET';

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => $orderSn,
                        'order_status' => 'READY_TO_SHIP',
                        'tracking_number' => 'SPX-STALE-INVALID',
                        'package_list' => [],
                    ],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_tracking_number*' => Http::response([
            'error' => '',
            'response' => ['tracking_number' => 'SPX-STALE-INVALID'],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_document_result*' => Http::sequence()
            ->push([
                'error' => 'logistics.shipping_document_should_print_first',
                'message' => 'The package should print first.',
            ])
            ->push([
                'error' => '',
                'response' => [
                    'result_list' => [
                        ['order_sn' => $orderSn, 'status' => 'READY'],
                    ],
                ],
            ]),
        'partner.shopeemobile.com/api/v2/logistics/create_shipping_document*' => Http::sequence()
            ->push([
                'error' => 'common.batch_api_all_failed',
                'message' => 'Failed, please check result_list for more details.',
                'response' => [
                    'result_list' => [
                        [
                            'order_sn' => $orderSn,
                            'fail_error' => 'logistics.tracking_number_invalid',
                            'fail_message' => 'The tracking number is invalid.',
                        ],
                    ],
                ],
            ])
            ->push([
                'error' => 'common.batch_api_all_failed',
                'message' => 'Failed, please check result_list for more details.',
                'response' => [
                    'result_list' => [
                        [
                            'order_sn' => $orderSn,
                            'fail_error' => 'logistics.tracking_number_invalid',
                            'fail_message' => 'The tracking number is invalid.',
                        ],
                    ],
                ],
            ])
            ->push([
                'error' => 'common.batch_api_all_failed',
                'message' => 'Failed, please check result_list for more details.',
                'response' => [
                    'result_list' => [
                        [
                            'order_sn' => $orderSn,
                            'fail_error' => 'logistics.tracking_number_invalid',
                            'fail_message' => 'The tracking number is invalid.',
                        ],
                    ],
                ],
            ]),
        'partner.shopeemobile.com/api/v2/logistics/download_shipping_document*' => Http::response(
            '%PDF-1.4 poll-after-invalid',
            200,
            ['Content-Type' => 'application/pdf'],
        ),
    ]);

    $service = app(ShopeeShippingDocumentService::class);
    $pdfs = $service->downloadPdfBatchesForShop(100, [$orderSn]);

    expect($pdfs)->toHaveCount(1)->and($pdfs[0])->toContain('%PDF');

    $createRequests = collect(Http::recorded())->filter(
        fn ($pair) => str_contains($pair[0]->url(), '/api/v2/logistics/create_shipping_document')
    );
    expect($createRequests)->toHaveCount(3);
});

it('waitForTrackingNumber polls until resi is returned', function () {
    Http::fake([
        'partner.shopeemobile.com/api/v2/logistics/get_tracking_number*' => Http::sequence()
            ->push(['error' => '', 'response' => ['tracking_number' => '']])
            ->push(['error' => '', 'response' => ['tracking_number' => '004692022568']]),
    ]);

    config(['services.shopee_admin.awb_tracking_wait_seconds' => 5]);

    $service = app(ShopeeShippingDocumentService::class);
    $tracking = $service->waitForTrackingNumber(100, '2611245WYMT9ET');

    expect($tracking)->toBe('004692022568');
});

it('skips create when get_shipping_document_result already shows PROCESSING', function () {
    $orderSn = 'AWB-EXISTING-TASK';

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => $orderSn,
                        'order_status' => 'READY_TO_SHIP',
                        'tracking_number' => 'SPX-VALID-123',
                        'package_list' => [],
                    ],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_document_result*' => Http::sequence()
            ->push([
                'error' => '',
                'response' => [
                    'result_list' => [
                        ['order_sn' => $orderSn, 'status' => 'PROCESSING'],
                    ],
                ],
            ])
            ->push([
                'error' => '',
                'response' => [
                    'result_list' => [
                        ['order_sn' => $orderSn, 'status' => 'READY'],
                    ],
                ],
            ]),
        'partner.shopeemobile.com/api/v2/logistics/download_shipping_document*' => Http::response(
            '%PDF-1.4 skip-create-processing',
            200,
            ['Content-Type' => 'application/pdf'],
        ),
    ]);

    $service = app(ShopeeShippingDocumentService::class);
    $pdfs = $service->downloadPdfBatchesForShop(100, [$orderSn]);

    expect($pdfs)->toHaveCount(1)->and($pdfs[0])->toContain('%PDF');

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/api/v2/logistics/create_shipping_document');
    });
});

it('throws actionable message when create still fails after arrange hint', function () {
    $orderSn = 'SVC-SHIP-ERR';

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => $orderSn,
                        'order_status' => 'READY_TO_SHIP',
                        'tracking_number' => 'SPX-ERR',
                        'package_list' => [],
                    ],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_tracking_number*' => Http::response([
            'error' => '',
            'response' => ['tracking_number' => 'SPX-ERR'],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_document_result*' => Http::response([
            'error' => 'logistics.shipping_document_should_print_first',
            'message' => 'The package should print first.',
        ]),
        'partner.shopeemobile.com/api/v2/logistics/create_shipping_document*' => Http::response([
            'error' => 'common.batch_api_all_failed',
            'message' => 'Failed, please check result_list for more details.',
            'response' => [
                'result_list' => [
                    [
                        'order_sn' => $orderSn,
                        'fail_error' => 'logistics.package_should_ship',
                        'fail_message' => 'Please ship the order first before printing the shipping document.',
                    ],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_parameter*' => Http::response([
            'error' => '',
            'response' => [
                'info_needed' => [
                    'dropoff' => [],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/ship_order*' => Http::response([
            'error' => '',
            'response' => [],
        ]),
    ]);

    $service = app(ShopeeShippingDocumentService::class);

    try {
        $service->downloadPdfBatchesForShop(100, [$orderSn]);
        expect(false)->toBeTrue('Expected RuntimeException');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('ship the order first');
    }
});
