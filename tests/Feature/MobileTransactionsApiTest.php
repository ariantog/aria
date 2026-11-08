<?php

use App\Models\Addrbook;
use App\Models\Location;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

function mobileLimitedUser(array $attributes = []): User
{
    static $seq = 8800;
    $seq++;

    return User::factory()->create(array_merge(['id' => $seq], $attributes));
}

function actingAsMobile(User $user): void
{
    Sanctum::actingAs($user, ['*']);
}

test('mobile dashboard requires authentication', function () {
    $this->getJson('/api/mobile/v1/dashboard')->assertUnauthorized();
});

test('mobile dashboard returns permission gated kpis', function () {
    Permission::firstOrCreate(['name' => 'transactions-list', 'guard_name' => 'web']);
    $user = mobileLimitedUser();
    $user->givePermissionTo('transactions-list');

    Transaction::factory()->create([
        'date' => now()->toDateString(),
        'type' => Transaction::TYPE_SELL,
        'status' => Transaction::STATUS_COMPLETED,
        'total' => -10000,
        'sender_id' => Addrbook::factory()->warehouse()->create()->id,
        'receiver_id' => Addrbook::factory()->customer()->create()->id,
        'user_id' => $user->id,
    ]);

    actingAsMobile($user);
    $response = $this->getJson('/api/mobile/v1/dashboard');

    $response->assertOk()
        ->assertJsonPath('can.activity', true)
        ->assertJsonStructure([
            'can',
            'kpis' => ['activity_today' => ['sell_count', 'buy_count', 'sell_total']],
        ]);
});

test('mobile dashboard omits activity when user lacks transactions-list', function () {
    $user = mobileLimitedUser();

    actingAsMobile($user);
    $response = $this->getJson('/api/mobile/v1/dashboard');

    $response->assertOk()
        ->assertJsonPath('can.activity', false)
        ->assertJsonMissingPath('kpis.activity_today');
});

test('mobile transactions list requires permission', function () {
    $user = mobileLimitedUser();

    actingAsMobile($user);
    $this->getJson('/api/mobile/v1/transactions')
        ->assertForbidden();
});

test('mobile transactions list paginates and returns dto shape', function () {
    Permission::firstOrCreate(['name' => 'transactions-list', 'guard_name' => 'web']);
    $user = mobileLimitedUser();
    $user->givePermissionTo('transactions-list');

    $sender = Addrbook::factory()->warehouse()->create(['name' => 'WH Mobile']);
    $receiver = Addrbook::factory()->customer()->create(['name' => 'Cust Mobile']);

    Transaction::factory()->count(3)->create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'user_id' => $user->id,
        'type' => Transaction::TYPE_SELL,
        'total' => -5000,
    ]);

    actingAsMobile($user);
    $response = $this->getJson('/api/mobile/v1/transactions?per_page=100&page=1');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'date',
                    'type' => ['id', 'slug', 'label'],
                    'sender' => ['id', 'name'],
                    'receiver' => ['id', 'name'],
                    'total',
                ],
            ],
            'current_page',
            'last_page',
            'per_page',
            'total',
        ]);

    expect($response->json('total'))->toBeGreaterThanOrEqual(3);
});

test('mobile transactions list respects visibleToUser location filter', function () {
    Permission::firstOrCreate(['name' => 'transactions-list', 'guard_name' => 'web']);

    $locationA = Location::create(['name' => 'Mobile Loc A']);
    $locationB = Location::create(['name' => 'Mobile Loc B']);

    $addrA = Addrbook::factory()->create(['name' => 'Party A']);
    $addrB = Addrbook::factory()->create(['name' => 'Party B']);
    $addrA->locations()->attach($locationA->id);
    $addrB->locations()->attach($locationB->id);

    $user = mobileLimitedUser(['location_id' => $locationA->id]);
    $user->givePermissionTo('transactions-list');

    $visible = Transaction::factory()->create([
        'sender_id' => $addrA->id,
        'receiver_id' => $addrB->id,
        'user_id' => $user->id,
    ]);
    $hidden = Transaction::factory()->create([
        'sender_id' => $addrB->id,
        'receiver_id' => $addrB->id,
        'user_id' => $user->id,
    ]);

    actingAsMobile($user);
    $response = $this->getJson('/api/mobile/v1/transactions');

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($visible->id)
        ->not->toContain($hidden->id);
});

test('mobile transaction show requires transactions-show', function () {
    Permission::firstOrCreate(['name' => 'transactions-list', 'guard_name' => 'web']);
    $user = mobileLimitedUser();
    $user->givePermissionTo('transactions-list');

    $tx = Transaction::factory()->create([
        'user_id' => $user->id,
        'sender_id' => Addrbook::factory()->warehouse()->create()->id,
        'receiver_id' => Addrbook::factory()->customer()->create()->id,
    ]);

    actingAsMobile($user);
    $this->getJson('/api/mobile/v1/transactions/'.$tx->id)
        ->assertForbidden();
});

test('mobile transaction show returns detail without real_total', function () {
    Permission::firstOrCreate(['name' => 'transactions-list', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'transactions-show', 'guard_name' => 'web']);
    $user = mobileLimitedUser();
    $user->givePermissionTo(['transactions-list', 'transactions-show']);

    $location = Location::create(['name' => 'Show Loc']);
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $warehouse->locations()->attach($location->id);
    $customer->locations()->attach($location->id);
    $user->update(['location_id' => $location->id]);

    $tx = Transaction::factory()->create([
        'user_id' => $user->id,
        'type' => Transaction::TYPE_SELL,
        'total' => -25000,
        'real_total' => -99999,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    actingAsMobile($user);
    $response = $this->getJson('/api/mobile/v1/transactions/'.$tx->id);

    $response->assertOk()
        ->assertJsonPath('transaction.id', $tx->id)
        ->assertJsonPath('transaction.total', -25000)
        ->assertJsonMissingPath('transaction.real_total');

    expect(json_encode($response->json()))->not->toContain('real_total');
});
