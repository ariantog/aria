<?php

use App\Models\Transaction;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();

    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'transactions-type-buy']);
    Permission::firstOrCreate(['name' => 'transactions-type-sell']);
    Permission::firstOrCreate(['name' => 'transactions-list']);
    $this->user->givePermissionTo('transactions-type-buy');
});

it('allows buy form access with only transactions-type-buy permission', function () {
    $this->actingAs($this->user)
        ->get(route('transactions.create', 'buy'))
        ->assertOk();
});

it('allows sell form access with sell permission only', function () {
    $this->user->syncPermissions(['transactions-type-sell']);

    $this->actingAs($this->user)
        ->get(route('transactions.create', 'sell'))
        ->assertOk();
});

it('denies buy form when user lacks buy and create permissions', function () {
    $this->user->syncPermissions(['transactions-type-sell']);

    $this->actingAs($this->user)
        ->get(route('transactions.create', 'buy'))
        ->assertForbidden();
});

it('allows transaction lookup with type permission even without list permission', function () {
    $this->actingAs($this->user)
        ->getJson(route('transactions.lookup', [
            'type' => 'buy',
            'role' => 'sender',
            'addrbook_type' => 4,
            'search' => 'a',
        ]))
        ->assertOk();
});

it('still requires list permission for the transactions index', function () {
    $this->actingAs($this->user)
        ->get(route('transactions.index'))
        ->assertForbidden();
});

it('resolves type permission keys with hyphens', function () {
    expect(Transaction::permissionNameForType('buy'))->toBe('transactions-type-buy')
        ->and(Transaction::permissionNameForType('return-supplier'))->toBe('transactions-type-return-supplier')
        ->and(Transaction::typePermissionKey('return-supplier'))->toBe('type-return-supplier');
});

it('allows item transaction forms for each type slug with only that type permission', function (string $type, string $permission) {
    Permission::firstOrCreate(['name' => $permission]);
    $this->user->syncPermissions([$permission]);

    $this->actingAs($this->user)
        ->get(route('transactions.create', $type))
        ->assertOk();
})->with([
    ['buy', 'transactions-type-buy'],
    ['sell', 'transactions-type-sell'],
    ['move', 'transactions-type-move'],
    ['return', 'transactions-type-return'],
    ['return-supplier', 'transactions-type-return-supplier'],
]);

it('allows dedicated cash and transfer pages with only the matching type permission', function (string $routeName, string $permission) {
    Permission::firstOrCreate(['name' => $permission]);
    $this->user->syncPermissions([$permission]);

    $this->actingAs($this->user)
        ->get(route($routeName))
        ->assertOk();
})->with([
    ['transactions.cash-in', 'transactions-type-cash-in'],
    ['transactions.cash-out', 'transactions-type-cash-out'],
    ['transactions.transfer', 'transactions-type-transfer'],
    ['transactions.adjust', 'transactions-type-adjust'],
]);
