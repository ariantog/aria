<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Support\Facades\Gate;

test('mobile login rejects invalid credentials', function () {
    User::factory()->create([
        'username' => 'mobuser',
    ]);

    $response = $this->postJson('/api/mobile/v1/auth/login', [
        'username' => 'mobuser',
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['username']);
});

test('mobile login returns token', function () {
    User::factory()->create([
        'username' => 'mobuser',
    ]);

    $response = $this->postJson('/api/mobile/v1/auth/login', [
        'username' => 'mobuser',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'username']]);

    expect($response->json('token'))->not->toBeEmpty();
});

test('mobile me requires authentication', function () {
    $this->getJson('/api/mobile/v1/me')->assertUnauthorized();
});

test('mobile me returns user payload', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/mobile/v1/me');

    $response->assertOk()
        ->assertJsonPath('user.id', $user->id);
});

test('mobile barcode lookup finds item by code', function () {
    Gate::before(fn () => true);

    $user = User::factory()->create();
    $item = Item::factory()->create([
        'code' => 'MOBILE-LOOKUP-01',
        'name' => 'Mobile Lookup Item',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/mobile/v1/items/barcode-lookup?barcode='.urlencode('MOBILE-LOOKUP-01'));

    $response->assertOk()
        ->assertJsonPath('item.id', $item->id)
        ->assertJsonPath('item.code', 'MOBILE-LOOKUP-01');
});

test('mobile sell creates transaction', function () {
    Gate::before(fn () => true);

    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create(['ppn' => false]);
    $item = Item::factory()->create(['price' => 1000]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => $warehouse->type,
        'quantity' => 100,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/mobile/v1/transactions/item', [
        'date' => now()->toDateString(),
        'type' => 'sell',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'items' => [
            [
                'item_id' => $item->id,
                'quantity' => 1,
                'price' => 1000,
                'discount' => 0,
            ],
        ],
    ], ['X-Idempotency-Key' => 'mobile-sell-test-001']);

    $response->assertCreated()
        ->assertJsonStructure(['id', 'message']);

    $id = (int) $response->json('id');
    expect($id)->toBeGreaterThan(0);
    expect(Transaction::find($id)?->type)->toBe(Transaction::TYPE_SELL);
});
