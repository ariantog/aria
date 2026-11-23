<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Shopee\ShopeePushSignatureVerifier;

test('fortify login store accepts POST under Laravel test harness', function () {
    $user = User::factory()->create();

    $this->withSameOriginSite()
        ->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password',
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('exempt shopee push webhook accepts POST without session csrf token', function () {
    config([
        'services.shopee_push.partner_key' => 'l13-test-key',
        'services.shopee_push.verify_signature' => true,
        'services.shopee_push.shadow_enabled' => false,
        'services.shopee_push.store_inbox' => false,
    ]);

    $body = '{"data":{"ordersn":"L13NOP","status":"PROCESSED","update_time":1660123127},"shop_id":9,"code":3,"timestamp":1660123127}';
    $callbackUrl = url(route('shopee.push', [], false));
    $authorization = app(ShopeePushSignatureVerifier::class)->sign($callbackUrl, $body);

    $this->call('POST', route('shopee.push'), [], [], [], [
        'HTTP_AUTHORIZATION' => $authorization,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();
});

test('http item sell still persists signed payable on transactions.total', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create(['ppn' => false]);
    $item = Item::factory()->create(['price' => 10_000, 'cost' => 5_000]);
    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => $warehouse->type,
        'quantity' => 50,
    ]);

    $this->actingAs($user)
        ->withSameOriginSite()
        ->post(route('transactions.store'), [
            'date' => now()->toDateString(),
            'type' => 'sell',
            'sender_id' => $warehouse->id,
            'receiver_id' => $customer->id,
            'items' => [[
                'item_id' => $item->id,
                'quantity' => 2,
                'price' => 10_000,
                'discount' => 0,
            ]],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_SELL,
        'total' => -20_000,
    ]);
});
