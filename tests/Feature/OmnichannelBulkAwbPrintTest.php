<?php

use App\Models\Jubelioorder;
use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceChannelOrderAwbPrint;
use App\Models\Omnichannel;
use App\Models\Setting;
use App\Models\User;
use App\Services\PermissionGenerator;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    app(PermissionGenerator::class)->generateForModule('Omnichannel');

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

function awbShippingDocumentParameterHttpResponse(string $orderSn = '', string $suggest = 'THERMAL_AIR_WAYBILL'): array
{
    $list = $orderSn !== ''
        ? [[
            'order_sn' => $orderSn,
            'suggest_shipping_document_type' => $suggest,
            'selectable_shipping_document_type_list' => [
                'NORMAL_AIR_WAYBILL',
                'THERMAL_AIR_WAYBILL',
            ],
        ]]
        : [];

    return [
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_document_parameter*' => Http::response([
            'error' => '',
            'response' => ['result_list' => $list],
        ]),
    ];
}

function awbOrderDetailResponse(string $orderSn, string $orderStatus = 'PROCESSED', string $tracking = 'SPXAWB123'): array
{
    return [
        'error' => '',
        'response' => [
            'order_list' => [
                [
                    'order_sn' => $orderSn,
                    'order_status' => $orderStatus,
                    'tracking_number' => $tracking,
                    'package_list' => [],
                ],
            ],
        ],
    ];
}

it('skips unsupported marketplace rows when printing awb', function () {
    $user = User::factory()->create(['id' => 48]);
    Permission::findOrCreate(Omnichannel::getPermissions()['orders'], 'web');
    $user->givePermissionTo(Omnichannel::getPermissions()['orders']);

    Http::fake();

    $response = $this->actingAs($user)->post(route('omnichannel.orders.shipping-documents'), [
        'rows' => [
            ['channel' => 'lazada', 'order_ref' => 'LZ-001', 'shop_ref' => '1'],
            ['channel' => 'blibli', 'order_ref' => 'BL-002', 'shop_ref' => '2'],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    Http::assertNothingSent();
});

it('requests then downloads tiktok awb pdf in mock driver', function () {
    config(['tiktok_shop.driver' => 'mock', 'tiktok_shop.shop_cipher' => 'MOCK_TTS_SHOP_CIPHER']);

    $user = User::factory()->create(['id' => 49]);
    Permission::findOrCreate(Omnichannel::getPermissions()['orders'], 'web');
    $user->givePermissionTo(Omnichannel::getPermissions()['orders']);

    $orderId = 'AWB-TT-001';

    MarketplaceChannelOrder::query()->create([
        'channel' => 'tiktok',
        'order_ref' => $orderId,
        'shop_ref' => 'MOCK_TTS_SHOP_CIPHER',
        'order_status' => 'AWAITING_SHIPMENT',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    Http::fake([
        'https://mock.tiktok.local/*' => Http::response('%PDF-1.4 mock-tiktok-label', 200, ['Content-Type' => 'application/pdf']),
    ]);

    $this->actingAs($user)->post(route('omnichannel.orders.shipping-documents.request'), [
        'rows' => [
            ['channel' => 'tiktok', 'order_ref' => $orderId, 'shop_ref' => 'MOCK_TTS_SHOP_CIPHER'],
        ],
    ])->assertRedirect()->assertSessionHas('success');

    $channelOrder = MarketplaceChannelOrder::query()->where('order_ref', $orderId)->first();
    expect($channelOrder->awb_document_ready_at)->not->toBeNull();

    $response = $this->actingAs($user)->post(route('omnichannel.orders.shipping-documents'), [
        'rows' => [
            ['channel' => 'tiktok', 'order_ref' => $orderId, 'shop_ref' => 'MOCK_TTS_SHOP_CIPHER'],
        ],
    ]);

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->streamedContent())->toContain('%PDF');

    $channelOrder->refresh();
    expect($channelOrder->order_status)->toBe('AWAITING_COLLECTION')
        ->and((int) $channelOrder->awb_printed_by)->toBe((int) $user->id)
        ->and($channelOrder->awb_printed_at)->not->toBeNull();
});

it('requests then downloads shopee awb pdf for selected orders', function () {
    $user = User::factory()->create(['id' => 42]);
    Permission::findOrCreate(Omnichannel::getPermissions()['orders'], 'web');
    $user->givePermissionTo(Omnichannel::getPermissions()['orders']);

    $orderSn = 'AWB-TEST-001';

    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => $orderSn,
        'shop_ref' => '100',
        'order_status' => 'PROCESSED',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    Http::fake(array_merge(awbShippingDocumentParameterHttpResponse($orderSn), [
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response(
            awbOrderDetailResponse($orderSn),
        ),
        'partner.shopeemobile.com/api/v2/logistics/create_shipping_document*' => Http::response([
            'error' => '',
            'response' => [
                'result_list' => [
                    ['order_sn' => $orderSn, 'fail_error' => ''],
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
            '%PDF-1.4 fake-awb-binary',
            200,
            ['Content-Type' => 'application/pdf'],
        ),
    ]));

    $this->actingAs($user)->post(route('omnichannel.orders.shipping-documents.request'), [
        'rows' => [
            ['channel' => 'shopee', 'order_ref' => $orderSn, 'shop_ref' => '100'],
        ],
    ])->assertRedirect()->assertSessionHas('success');

    $channelOrder = MarketplaceChannelOrder::query()->where('order_ref', $orderSn)->first();
    expect($channelOrder->awb_document_ready_at)->not->toBeNull();

    $response = $this->actingAs($user)->post(route('omnichannel.orders.shipping-documents'), [
        'rows' => [
            ['channel' => 'shopee', 'order_ref' => $orderSn, 'shop_ref' => '100'],
        ],
    ]);

    $response->assertOk()
        ->assertSessionMissing('error');
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->streamedContent())->toContain('%PDF');

    expect(MarketplaceChannelOrder::query()->where('order_ref', $orderSn)->value('order_status'))
        ->toBe('READY_TO_SHIP');

    $channelOrder->refresh();
    expect($channelOrder)->not->toBeNull()
        ->and((int) $channelOrder->awb_printed_by)->toBe((int) $user->id)
        ->and($channelOrder->awb_printed_at)->not->toBeNull();

    $queue = Jubelioorder::query()->where('invoice', 'SP-'.$orderSn)->first();
    expect($queue)->not->toBeNull()
        ->and($queue->type)->toBe('SELL')
        ->and($queue->source)->toBe(Jubelioorder::SOURCE_SHOPEE_PUSH)
        ->and((int) $queue->user_id)->toBe((int) $user->id);
});

it('shows awb toolbar on current orders view', function () {
    $admin = User::factory()->create();

    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => 'UI-AWB-SN',
        'shop_ref' => '100',
        'order_status' => 'PROCESSED',
        'marketplace_ordered_at' => now(),
        'last_event_at' => now(),
        'first_received_at' => now()->subHour(),
        'awb_printed_by' => $admin->id,
        'awb_printed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('omnichannel.orders.index', ['view' => 'orders']))
        ->assertOk()
        ->assertSee('data-testid="omnichannel-awb-toolbar"', false)
        ->assertSee('Minta AWB', false)
        ->assertSee('Unduh AWB', false)
        ->assertSee('data-testid="omnichannel-awb-row-', false)
        ->assertSee($admin->username, false);
});

it('does not overwrite jubelio scanner user when awb print touches queue row', function () {
    $printer = User::factory()->create(['id' => 43]);
    $scanner = User::factory()->create(['id' => 44]);
    Permission::findOrCreate(Omnichannel::getPermissions()['orders'], 'web');
    $printer->givePermissionTo(Omnichannel::getPermissions()['orders']);

    $orderSn = 'AWB-SCANNER-LOCK';

    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => $orderSn,
        'shop_ref' => '100',
        'order_status' => 'PROCESSED',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    Jubelioorder::query()->create([
        'jubelio_order_id' => 'shopee-push:'.$orderSn,
        'source' => Jubelioorder::SOURCE_SHOPEE_PUSH,
        'invoice' => 'SP-'.$orderSn,
        'type' => 'SELL',
        'order_status' => 'READY_TO_SHIP',
        'run_count' => 0,
        'user_id' => $scanner->id,
        'status' => 0,
    ]);

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response(
            awbOrderDetailResponse($orderSn),
        ),
        'partner.shopeemobile.com/api/v2/logistics/create_shipping_document*' => Http::response([
            'error' => '',
            'response' => [
                'result_list' => [
                    ['order_sn' => $orderSn, 'fail_error' => ''],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_document_result*' => Http::response([
            'error' => '',
            'response' => [
                'result_list' => [
                    ['order_sn' => $orderSn, 'status' => 'READY'],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/download_shipping_document*' => Http::response(
            '%PDF-1.4 fake',
            200,
            ['Content-Type' => 'application/pdf'],
        ),
    ]);

    awbMarkDocumentReady($orderSn);

    $this->actingAs($printer)->post(route('omnichannel.orders.shipping-documents'), [
        'rows' => [
            ['channel' => 'shopee', 'order_ref' => $orderSn, 'shop_ref' => '100'],
        ],
    ])->assertOk();

    $queue = Jubelioorder::query()->where('invoice', 'SP-'.$orderSn)->first();
    expect($queue)->not->toBeNull()
        ->and((int) $queue->user_id)->toBe((int) $scanner->id);

    $channelOrder = MarketplaceChannelOrder::query()->where('order_ref', $orderSn)->first();
    expect((int) $channelOrder->awb_printed_by)->toBe((int) $printer->id);
});

function fakeShopeeAwbHttpForOrder(string $orderSn): void
{
    Http::fake(array_merge(awbShippingDocumentParameterHttpResponse($orderSn), [
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response(
            awbOrderDetailResponse($orderSn),
        ),
        'partner.shopeemobile.com/api/v2/logistics/get_tracking_number*' => Http::response([
            'error' => '',
            'response' => ['tracking_number' => 'SPXAWB123'],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/create_shipping_document*' => Http::response([
            'error' => '',
            'response' => [
                'result_list' => [
                    ['order_sn' => $orderSn, 'fail_error' => ''],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_document_result*' => Http::response([
            'error' => '',
            'response' => [
                'result_list' => [
                    ['order_sn' => $orderSn, 'status' => 'READY'],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/download_shipping_document*' => Http::response(
            '%PDF-1.4 fake-awb-binary',
            200,
            ['Content-Type' => 'application/pdf'],
        ),
    ]));
}

function awbMarkDocumentReady(string $orderSn): void
{
    MarketplaceChannelOrder::query()
        ->where('channel', 'shopee')
        ->where('order_ref', $orderSn)
        ->update(['awb_document_ready_at' => now()]);
}

it('blocks second awb print without reprint permission', function () {
    $user = User::factory()->create(['id' => 45]);
    Permission::findOrCreate(Omnichannel::getPermissions()['orders'], 'web');
    $user->givePermissionTo(Omnichannel::getPermissions()['orders']);

    $orderSn = 'AWB-ONCE-ONLY';

    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => $orderSn,
        'shop_ref' => '100',
        'order_status' => 'PROCESSED',
        'last_event_at' => now(),
        'first_received_at' => now(),
        'awb_printed_by' => $user->id,
        'awb_printed_at' => now()->subHour(),
    ]);

    MarketplaceChannelOrderAwbPrint::query()->create([
        'channel' => 'shopee',
        'order_ref' => $orderSn,
        'user_id' => $user->id,
        'printed_at' => now()->subHour(),
        'is_reprint' => false,
    ]);

    Http::fake();

    $response = $this->actingAs($user)->post(route('omnichannel.orders.shipping-documents'), [
        'rows' => [
            ['channel' => 'shopee', 'order_ref' => $orderSn, 'shop_ref' => '100'],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(session('error'))->toContain('AWB sudah pernah dicetak');
    Http::assertNothingSent();
    expect(MarketplaceChannelOrderAwbPrint::query()->where('order_ref', $orderSn)->count())->toBe(1);
});

it('allows awb reprint with reprint permission and records audit rows', function () {
    $user = User::factory()->create(['id' => 46]);
    Permission::findOrCreate(Omnichannel::getPermissions()['orders'], 'web');
    Permission::findOrCreate(Omnichannel::getPermissions()['awb-reprint'], 'web');
    $user->givePermissionTo([
        Omnichannel::getPermissions()['orders'],
        Omnichannel::getPermissions()['awb-reprint'],
    ]);

    $orderSn = 'AWB-REPRINT-OK';

    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => $orderSn,
        'shop_ref' => '100',
        'order_status' => 'PROCESSED',
        'last_event_at' => now(),
        'first_received_at' => now(),
        'awb_printed_by' => $user->id,
        'awb_printed_at' => now()->subDay(),
    ]);

    MarketplaceChannelOrderAwbPrint::query()->create([
        'channel' => 'shopee',
        'order_ref' => $orderSn,
        'user_id' => $user->id,
        'printed_at' => now()->subDay(),
        'is_reprint' => false,
    ]);

    fakeShopeeAwbHttpForOrder($orderSn);
    awbMarkDocumentReady($orderSn);

    $this->actingAs($user)->post(route('omnichannel.orders.shipping-documents'), [
        'rows' => [
            ['channel' => 'shopee', 'order_ref' => $orderSn, 'shop_ref' => '100'],
        ],
    ])->assertOk();

    $prints = MarketplaceChannelOrderAwbPrint::query()
        ->where('order_ref', $orderSn)
        ->orderBy('id')
        ->get();

    expect($prints)->toHaveCount(2)
        ->and((bool) $prints[1]->is_reprint)->toBeTrue();
});

it('allows superadmin to reprint awb without explicit permission', function () {
    $admin = User::factory()->create(['id' => 1, 'username' => 'superadmin']);

    $orderSn = 'AWB-SUPER-REPRINT';

    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => $orderSn,
        'shop_ref' => '100',
        'order_status' => 'PROCESSED',
        'last_event_at' => now(),
        'first_received_at' => now(),
        'awb_printed_by' => 2,
        'awb_printed_at' => now()->subHours(2),
    ]);

    fakeShopeeAwbHttpForOrder($orderSn);
    awbMarkDocumentReady($orderSn);

    $this->actingAs($admin)->post(route('omnichannel.orders.shipping-documents'), [
        'rows' => [
            ['channel' => 'shopee', 'order_ref' => $orderSn, 'shop_ref' => '100'],
        ],
    ])->assertOk();

    expect(MarketplaceChannelOrderAwbPrint::query()->where('order_ref', $orderSn)->count())->toBe(1);
});

it('registers omnichannel awb reprint permission via generator', function () {
    app(PermissionGenerator::class)->generateForModule('Omnichannel');

    expect(Permission::query()->where('name', Omnichannel::getPermissions()['awb-reprint'])->exists())
        ->toBeTrue();
});

it('appends awb audit row on first successful print', function () {
    $user = User::factory()->create(['id' => 47]);
    Permission::findOrCreate(Omnichannel::getPermissions()['orders'], 'web');
    $user->givePermissionTo(Omnichannel::getPermissions()['orders']);

    $orderSn = 'AWB-AUDIT-FIRST';

    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => $orderSn,
        'shop_ref' => '100',
        'order_status' => 'PROCESSED',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    fakeShopeeAwbHttpForOrder($orderSn);
    awbMarkDocumentReady($orderSn);

    $this->actingAs($user)->post(route('omnichannel.orders.shipping-documents'), [
        'rows' => [
            ['channel' => 'shopee', 'order_ref' => $orderSn, 'shop_ref' => '100'],
        ],
    ])->assertOk();

    $audit = MarketplaceChannelOrderAwbPrint::query()->where('order_ref', $orderSn)->first();
    expect($audit)->not->toBeNull()
        ->and((int) $audit->user_id)->toBe((int) $user->id)
        ->and((bool) $audit->is_reprint)->toBeFalse();
});

it('downloads awb for ready_to_ship order when shipping document is already ready', function () {
    $user = User::factory()->create(['id' => 49]);
    Permission::findOrCreate(Omnichannel::getPermissions()['orders'], 'web');
    $user->givePermissionTo(Omnichannel::getPermissions()['orders']);

    $orderSn = '2611245VU2MSJP';

    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => $orderSn,
        'shop_ref' => '100',
        'order_status' => 'READY_TO_SHIP',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    Http::fake(array_merge(awbShippingDocumentParameterHttpResponse($orderSn), [
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response(
            awbOrderDetailResponse($orderSn, 'READY_TO_SHIP', 'SPXID2611245'),
        ),
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_document_result*' => Http::response([
            'error' => '',
            'response' => [
                'result_list' => [
                    ['order_sn' => $orderSn, 'status' => 'READY'],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/download_shipping_document*' => Http::response(
            '%PDF-1.4 ready-to-ship-existing',
            200,
            ['Content-Type' => 'application/pdf'],
        ),
    ]));

    awbMarkDocumentReady($orderSn);

    $response = $this->actingAs($user)->post(route('omnichannel.orders.shipping-documents'), [
        'rows' => [
            ['channel' => 'shopee', 'order_ref' => $orderSn, 'shop_ref' => '100'],
        ],
    ]);

    $response->assertOk();
    expect($response->streamedContent())->toContain('%PDF');

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/api/v2/logistics/create_shipping_document');
    });
});

it('completes awb when create returns tracking_number_invalid but label task becomes ready', function () {
    $user = User::factory()->create(['id' => 50]);
    Permission::findOrCreate(Omnichannel::getPermissions()['orders'], 'web');
    $user->givePermissionTo(Omnichannel::getPermissions()['orders']);

    $orderSn = 'AWB-BATCH-FAIL';

    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => $orderSn,
        'shop_ref' => '100',
        'order_status' => 'READY_TO_SHIP',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response(
            awbOrderDetailResponse($orderSn, 'READY_TO_SHIP', 'SPXFAIL001'),
        ),
        'partner.shopeemobile.com/api/v2/logistics/get_tracking_number*' => Http::response([
            'error' => '',
            'response' => ['tracking_number' => 'SPXFAIL001'],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/create_shipping_document*' => Http::sequence()
            ->push([
                'error' => 'common.batch_api_all_failed',
                'message' => 'Failed, please check result_list for more details.',
                'request_id' => 'req-batch-fail-99',
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
                'request_id' => 'req-batch-fail-99',
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
                'request_id' => 'req-batch-fail-99',
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
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_document_result*' => Http::sequence()
            ->push([
                'error' => '',
                'response' => [
                    'result_list' => [
                        [
                            'order_sn' => $orderSn,
                            'status' => 'FAILED',
                            'fail_error' => 'logistics.shipping_document_should_print_first',
                            'fail_message' => 'create shipping document first',
                        ],
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
            '%PDF-1.4 batch-invalid-fallback',
            200,
            ['Content-Type' => 'application/pdf'],
        ),
    ]);

    awbMarkDocumentReady($orderSn);

    $response = $this->actingAs($user)->post(route('omnichannel.orders.shipping-documents'), [
        'rows' => [
            ['channel' => 'shopee', 'order_ref' => $orderSn, 'shop_ref' => '100'],
        ],
    ]);

    $response->assertOk();
    expect($response->streamedContent())->toContain('%PDF');
});

it('continues awb flow when create returns duplicate package error', function () {
    $user = User::factory()->create(['id' => 51]);
    Permission::findOrCreate(Omnichannel::getPermissions()['orders'], 'web');
    $user->givePermissionTo(Omnichannel::getPermissions()['orders']);

    $orderSn = 'AWB-DUP-CREATE';

    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => $orderSn,
        'shop_ref' => '100',
        'order_status' => 'READY_TO_SHIP',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response(
            awbOrderDetailResponse($orderSn, 'READY_TO_SHIP', 'SPXDUP001'),
        ),
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
        'partner.shopeemobile.com/api/v2/logistics/create_shipping_document*' => Http::response([
            'error' => 'common.batch_api_all_failed',
            'message' => 'Failed, please check result_list for more details.',
            'response' => [
                'result_list' => [
                    [
                        'order_sn' => $orderSn,
                        'fail_error' => 'logistics.package_can_not_print',
                        'fail_message' => 'Failed, duplicated',
                    ],
                ],
            ],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/download_shipping_document*' => Http::response(
            '%PDF-1.4 dup-recovered',
            200,
            ['Content-Type' => 'application/pdf'],
        ),
    ]);

    awbMarkDocumentReady($orderSn);

    $this->actingAs($user)->post(route('omnichannel.orders.shipping-documents'), [
        'rows' => [
            ['channel' => 'shopee', 'order_ref' => $orderSn, 'shop_ref' => '100'],
        ],
    ])->assertOk();
});

it('continues awb flow when probe returns should_print_first as top-level error', function () {
    $user = User::factory()->create(['id' => 52]);
    Permission::findOrCreate(Omnichannel::getPermissions()['orders'], 'web');
    $user->givePermissionTo(Omnichannel::getPermissions()['orders']);

    $orderSn = '2611245VU2MSJP';

    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => $orderSn,
        'shop_ref' => '100',
        'order_status' => 'READY_TO_SHIP',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response(
            awbOrderDetailResponse($orderSn, 'READY_TO_SHIP', 'SPXID2611245'),
        ),
        'partner.shopeemobile.com/api/v2/logistics/get_tracking_number*' => Http::response([
            'error' => '',
            'response' => ['tracking_number' => 'SPXID2611245'],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_document_result*' => Http::sequence()
            ->push([
                'error' => 'logistics.shipping_document_should_print_first',
                'message' => 'The package should print first. Detail: these orders: '.$orderSn.' should print',
                'request_id' => 'e3e3e7f361006cda1f5527c2afbec000',
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
            '%PDF-1.4 probe-should-print-first',
            200,
            ['Content-Type' => 'application/pdf'],
        ),
    ]);

    $response = $this->actingAs($user)->post(route('omnichannel.orders.shipping-documents.request'), [
        'rows' => [
            ['channel' => 'shopee', 'order_ref' => $orderSn, 'shop_ref' => '100'],
        ],
    ]);

    $response->assertRedirect()->assertSessionHas('success');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v2/logistics/create_shipping_document');
    });
});

it('calls ship_order before create for processed orders without tracking', function () {
    $user = User::factory()->create(['id' => 53]);
    Permission::findOrCreate(Omnichannel::getPermissions()['orders'], 'web');
    $user->givePermissionTo(Omnichannel::getPermissions()['orders']);

    $orderSn = 'AWB-SHIP-FIRST';

    MarketplaceChannelOrder::query()->create([
        'channel' => 'shopee',
        'order_ref' => $orderSn,
        'shop_ref' => '100',
        'order_status' => 'PROCESSED',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    Http::fake([
        'partner.shopeemobile.com/api/v2/order/get_order_detail*' => Http::response([
            'error' => '',
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => $orderSn,
                        'order_status' => 'PROCESSED',
                        'tracking_number' => '',
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
            'response' => ['tracking_number' => 'SPX-SHIP-FIRST'],
        ]),
        'partner.shopeemobile.com/api/v2/logistics/get_shipping_document_result*' => Http::sequence()
            ->push([
                'error' => 'logistics.shipping_document_should_print_first',
                'message' => 'create shipping document first',
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
            '%PDF-1.4 ship-first-flow',
            200,
            ['Content-Type' => 'application/pdf'],
        ),
    ]);

    $this->actingAs($user)->post(route('omnichannel.orders.shipping-documents.request'), [
        'rows' => [
            ['channel' => 'shopee', 'order_ref' => $orderSn, 'shop_ref' => '100'],
        ],
    ])->assertRedirect()->assertSessionHas('success');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v2/logistics/ship_order');
    });
});

it('forbids awb download without omnichannel orders permission', function () {
    $user = User::factory()->create(['id' => 88]);

    $this->actingAs($user)
        ->post(route('omnichannel.orders.shipping-documents'), [
            'rows' => [
                ['channel' => 'shopee', 'order_ref' => 'X', 'shop_ref' => '100'],
            ],
        ])
        ->assertForbidden();
});
