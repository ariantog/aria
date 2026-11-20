<?php

use App\Models\Jubelioorder;
use App\Models\MarketplaceOrderShadow;
use App\Models\Omnichannel;
use App\Models\ShopeePushInbox;
use App\Models\User;
use App\Services\Marketplace\OmnichannelReceivedTimes;
use App\Services\Marketplace\ShopeeJubelioShadowComparer;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => Omnichannel::getPermissions()['view'], 'guard_name' => 'web']);
});

it('renders omnichannel dashboard for superadmin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('omnichannel.index'))
        ->assertOk()
        ->assertSee('Omnichannel', false)
        ->assertSee('Tidak mengubah transaksi', false)
        ->assertSee('shopee:simulate-order-status-push', false)
        ->assertSee('code 0', false);
});

it('forbids omnichannel dashboard without permission', function () {
    User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('omnichannel.index'))
        ->assertForbidden();
});

it('renders omnichannel dashboard with omnichannel-view permission', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo(Omnichannel::getPermissions()['view']);

    $this->actingAs($user)
        ->get(route('omnichannel.index'))
        ->assertOk()
        ->assertSee('Omnichannel', false);
});

it('forbids shopee shadow detail without permission', function () {
    User::factory()->create();
    $user = User::factory()->create();

    $shadow = MarketplaceOrderShadow::query()->create([
        'channel' => MarketplaceOrderShadow::CHANNEL_SHOPEE,
        'external_order_sn' => 'FORBIDDENSN',
        'channel_status' => 'SHIPPED',
        'compare_status' => MarketplaceOrderShadow::COMPARE_MATCHED,
        'line_compare_status' => MarketplaceOrderShadow::LINE_COMPARE_MATCHED,
        'line_summary' => [
            'line_count' => 1,
            'lines' => [['sku' => 'X', 'qty' => 1, 'amount' => 100]],
        ],
        'last_event_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('omnichannel.shopee.show', $shadow->external_order_sn))
        ->assertForbidden();
});

it('renders shopee shadow detail page', function () {
    $user = User::factory()->create();

    $shadow = MarketplaceOrderShadow::query()->create([
        'channel' => MarketplaceOrderShadow::CHANNEL_SHOPEE,
        'external_order_sn' => 'DETAILSN1',
        'channel_status' => 'SHIPPED',
        'compare_status' => MarketplaceOrderShadow::COMPARE_MATCHED,
        'line_compare_status' => MarketplaceOrderShadow::LINE_COMPARE_MATCHED,
        'line_summary' => [
            'line_count' => 1,
            'lines' => [['sku' => 'X', 'qty' => 1, 'amount' => 100]],
        ],
        'last_event_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('omnichannel.shopee.show', $shadow->external_order_sn))
        ->assertOk()
        ->assertSee('DETAILSN1', false)
        ->assertSee('Ringkasan Shopee', false);
});

it('matches shopee order to jubelio invoice prefix', function () {
    Jubelioorder::create([
        'jubelio_order_id' => 'j-1',
        'source' => 1,
        'invoice' => 'SP-TESTORDER',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'status' => 0,
    ]);

    $result = app(ShopeeJubelioShadowComparer::class)->compare('TESTORDER', 'SHIPPED');

    expect($result['compare_status'])->toBe(MarketplaceOrderShadow::COMPARE_MATCHED)
        ->and($result['jubelio_invoice'])->toBe('SP-TESTORDER');
});

it('shows shopee push and jubelio inbox timestamps on the dashboard', function () {
    $user = User::factory()->create();

    $jubelioReceived = Carbon::parse('2026-11-20 09:59:15', OmnichannelReceivedTimes::TZ);
    $shopeeReceived = Carbon::parse('2026-11-20 10:00:00', OmnichannelReceivedTimes::TZ);

    $jubelio = Jubelioorder::create([
        'jubelio_order_id' => 'j-ingress-1',
        'source' => 1,
        'invoice' => 'SP-INGSN1',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'status' => 0,
        'created_at' => $jubelioReceived,
    ]);

    $shadow = MarketplaceOrderShadow::query()->create([
        'channel' => MarketplaceOrderShadow::CHANNEL_SHOPEE,
        'external_order_sn' => 'INGSN1',
        'channel_status' => 'SHIPPED',
        'compare_status' => MarketplaceOrderShadow::COMPARE_MATCHED,
        'line_compare_status' => MarketplaceOrderShadow::LINE_COMPARE_MATCHED,
        'jubelio_invoice' => 'SP-INGSN1',
        'jubelio_order_id' => $jubelio->id,
        'line_summary' => [
            'line_count' => 1,
            'lines' => [['sku' => 'X', 'qty' => 1, 'amount' => 100]],
        ],
        'last_event_at' => $shopeeReceived->copy()->addMinutes(5),
    ]);

    ShopeePushInbox::query()->create([
        'shop_id' => 1,
        'push_code' => OmnichannelReceivedTimes::PUSH_CODE_ORDER_STATUS,
        'ordersn' => 'INGSN1',
        'order_status' => 'SHIPPED',
        'signature_ok' => true,
        'payload' => ['data' => ['ordersn' => 'INGSN1']],
        'created_at' => $shopeeReceived,
    ]);

    $shopeeLabel = $shopeeReceived->format('d M Y H:i:s');
    $jubelioLabel = $jubelioReceived->format('d M Y H:i:s');

    $this->actingAs($user)
        ->get(route('omnichannel.index'))
        ->assertOk()
        ->assertSee('Shopee push', false)
        ->assertSee('Jubelio inbox', false)
        ->assertSee($shopeeLabel, false)
        ->assertSee($jubelioLabel, false)
        ->assertSee('+45s', false);

    $this->actingAs($user)
        ->get(route('omnichannel.shopee.show', $shadow->external_order_sn))
        ->assertOk()
        ->assertSee('Ingress (Aria)', false)
        ->assertSee($shopeeLabel, false)
        ->assertSee($jubelioLabel, false)
        ->assertSee('+45s', false);
});
