<?php

use App\Models\Jubelioorder;
use App\Models\MarketplaceOrderShadow;
use App\Models\Omnichannel;
use App\Models\User;
use App\Services\Marketplace\ShopeeJubelioShadowComparer;
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