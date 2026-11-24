<?php

use App\Models\Jubelioorder;
use App\Models\MarketplaceChannelOrder;
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
    $user = User::factory()->create();
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
    $user = User::factory()->create();
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

    $queue = Jubelioorder::query()->where('invoice', 'SP-'.$orderSn)->first();
    expect($queue)->not->toBeNull()
        ->and($queue->type)->toBe('SELL')
        ->and($queue->source)->toBe(Jubelioorder::SOURCE_SHOPEE_PUSH);
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
    ]);

    $this->actingAs($admin)
        ->get(route('omnichannel.orders.index', ['view' => 'orders']))
        ->assertOk()
        ->assertSee('data-testid="omnichannel-awb-toolbar"', false)
        ->assertSee('TikTok/Tokopedia belum didukung', false)
        ->assertSee('data-testid="omnichannel-awb-row-', false);
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