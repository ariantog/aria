<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\WarehouseItem;
use App\Support\UserPreferenceRegistry;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;

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
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonStructure([
            'preferences' => [
                'appearance',
                'transaction_parties',
                'cash_in_bank',
                'cash_out_bank',
                'transfer',
                'prefill',
            ],
        ]);
});

test('mobile me preferences mirror default cash banks when set', function () {
    $user = User::factory()->create();
    $bankIn = Addrbook::factory()->bank()->create(['name' => 'Bank In']);
    $bankOut = Addrbook::factory()->bank()->create(['name' => 'Bank Out']);
    UserPreference::query()->create([
        'user_id' => $user->id,
        'slug' => 'transactions.default_cash_in_bank_id',
        'value' => (string) $bankIn->id,
    ]);
    UserPreference::query()->create([
        'user_id' => $user->id,
        'slug' => 'transactions.default_cash_out_bank_id',
        'value' => (string) $bankOut->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/mobile/v1/me');

    $response->assertOk()
        ->assertJsonPath('preferences.cash_in_bank.id', $bankIn->id)
        ->assertJsonPath('preferences.cash_out_bank.id', $bankOut->id)
        ->assertJsonPath('preferences.appearance', 'system');
});

test('mobile patch preferences appearance updates user preference', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->patchJson('/api/mobile/v1/preferences/appearance', [
        'appearance' => 'dark',
    ]);

    $response->assertOk()
        ->assertJsonPath('appearance', 'dark');

    expect(UserPreference::query()
        ->where('user_id', $user->id)
        ->where('slug', UserPreferenceRegistry::APPEARANCE_SLUG)
        ->value('value'))->toBe('dark');
});

test('mobile_actions includes buy when user has transactions-type-buy', function () {
    Permission::findOrCreate('transactions-type-buy', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('transactions-type-buy');

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/mobile/v1/me');

    $response->assertOk();
    expect($response->json('mobile_actions'))->toContain('buy');
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

test('mobile buy creates transaction', function () {
    Gate::before(fn () => true);

    $user = User::factory()->create();
    $supplier = Addrbook::factory()->supplier()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create(['cost' => 500]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/mobile/v1/transactions/item', [
        'date' => now()->toDateString(),
        'type' => 'buy',
        'sender_id' => $supplier->id,
        'receiver_id' => $warehouse->id,
        'items' => [
            [
                'item_id' => $item->id,
                'quantity' => 2,
                'price' => 500,
                'discount' => 0,
            ],
        ],
    ], ['X-Idempotency-Key' => 'mobile-buy-test-001']);

    $response->assertCreated()
        ->assertJsonStructure(['id', 'message']);

    $id = (int) $response->json('id');
    expect($id)->toBeGreaterThan(0);
    expect(Transaction::find($id)?->type)->toBe(Transaction::TYPE_BUY);
});
