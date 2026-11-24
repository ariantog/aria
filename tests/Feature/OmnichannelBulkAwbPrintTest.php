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

function awbOrderDetailResponse(string $orderSn): array
{
    return [
        'error' => '',
        'response' => [
            'order_list' => [
                [
                    'order_sn' => $orderSn,
                    'order_status' => 'PROCESSED',
                    'tracking_number' => 'SPXAWB123',
                    'package_list' => [],
                ],
            ],
        ],
    ];
}

it('skips non-shopee rows when printing awb', function () {
    $user = User::factory()->create(['id' => 48]);
    Permission::findOrCreate(Omnichannel::getPermissions()['orders'], 'web');
    $user->givePermissionTo(Omnichannel::getPermissions()['orders']);

    Http::fake();

    $response = $this->actingAs($user)->post(route('omnichannel.orders.shipping-documents'), [
        'rows' => [
            ['channel' => 'tiktok', 'order_ref' => '5790111', 'shop_ref' => 'cipher-1'],
            ['channel' => 'tiktok', 'order_ref' => '5790222', 'shop_ref' => 'cipher-2'],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    Http::assertNothingSent();
});

it('downloads shopee awb pdf for selected orders', function () {
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
            '%PDF-1.4 fake-awb-binary',
            200,
            ['Content-Type' => 'application/pdf'],
        ),
    ]);

    $response = $this->actingAs($user)->post(route('omnichannel.orders.shipping-documents'), [
        'rows' => [
            ['channel' => 'shopee', 'order_ref' => $orderSn, 'shop_ref' => '100'],
        ],
    ]);

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->streamedContent())->toContain('%PDF');

    expect(MarketplaceChannelOrder::query()->where('order_ref', $orderSn)->value('order_status'))
        ->toBe('READY_TO_SHIP');

    $channelOrder = MarketplaceChannelOrder::query()->where('order_ref', $orderSn)->first();
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
        'last_event_at' => now(),
        'first_received_at' => now()->subHour(),
        'awb_printed_by' => $admin->id,
        'awb_printed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('omnichannel.orders.index', ['view' => 'orders']))
        ->assertOk()
        ->assertSee('data-testid="omnichannel-awb-toolbar"', false)
        ->assertSee('TikTok/Tokopedia belum didukung', false)
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
            '%PDF-1.4 fake-awb-binary',
            200,
            ['Content-Type' => 'application/pdf'],
        ),
    ]);
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