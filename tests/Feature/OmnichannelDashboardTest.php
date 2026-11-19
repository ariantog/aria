<?php

use App\Models\Jubelioorder;
use App\Models\MarketplaceOrderShadow;
use App\Models\User;
use App\Services\Marketplace\ShopeeJubelioShadowComparer;

it('renders omnichannel dashboard for superadmin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('omnichannel.index'))
        ->assertOk()
        ->assertSee('Omnichannel', false)
        ->assertSee('Tidak mengubah transaksi', false);
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