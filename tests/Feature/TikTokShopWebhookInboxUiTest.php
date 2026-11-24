<?php

use App\Models\MarketplaceChannelOrder;
use App\Models\TikTokShop;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate(TikTokShop::getPermissions()['view'], 'web');
    Permission::findOrCreate(TikTokShop::getPermissions()['reply'], 'web');
});

it('redirects legacy tiktok webhook inbox to omnichannel orders', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo(TikTokShop::getPermissions()['view']);

    $this->actingAs($user)
        ->get(route('tiktok-shop.webhooks.index', ['channel' => 'tiktok']))
        ->assertRedirect(route('omnichannel.orders.index', ['channel' => 'tiktok']));
});

it('shows tiktok orders on omnichannel list for user with tiktok shop view permission', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo(TikTokShop::getPermissions()['view']);

    MarketplaceChannelOrder::query()->create([
        'channel' => 'tiktok',
        'order_ref' => '576653688135258178',
        'shop_ref' => '7495540735365777507',
        'order_status' => 'AWAITING_SHIPMENT',
        'last_event_at' => now(),
        'first_received_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('omnichannel.orders.index', ['channel' => 'tiktok', 'view' => 'orders']))
        ->assertOk()
        ->assertSee('data-testid="omnichannel-orders-table"', false)
        ->assertSee('576653688135258178', false)
        ->assertSee('AWAITING_SHIPMENT', false);
});

it('forbids legacy webhook route without view permission', function () {
    User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('marketplace.webhooks.index'))
        ->assertForbidden();
});

it('allows superadmin to reach orders via legacy webhook redirect', function () {
    $user = User::factory()->create();
    expect($user->is_superadmin)->toBeTrue();

    $this->actingAs($user)
        ->get(route('marketplace.webhooks.index'))
        ->assertRedirect(route('omnichannel.orders.index'));
});