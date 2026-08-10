<?php

use App\Models\Addrbook;
use App\Models\Location;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    User::factory()->create();
});

it('treats user id 1 as superadmin regardless of location assignment', function () {
    $locationA = Location::create(['name' => 'Location A']);
    $locationB = Location::create(['name' => 'Location B']);

    $addrbookA = Addrbook::factory()->create();
    $addrbookB = Addrbook::factory()->create();
    $addrbookA->locations()->attach($locationA->id);
    $addrbookB->locations()->attach($locationB->id);

    $hiddenTx = Transaction::factory()->create([
        'invoice_number' => 'SUPERADMIN-TX-1',
        'sender_id' => $addrbookB->id,
        'receiver_id' => $addrbookB->id,
    ]);

    $superadmin = User::find(1);
    expect($superadmin)->not->toBeNull();
    $superadmin->update(['location_id' => $locationA->id]);

    expect(User::isSuperadmin($superadmin))->toBeTrue();

    $ids = Transaction::query()->visibleToUser($superadmin)->pluck('id');
    expect($ids)->toContain($hiddenTx->id);

    $this->actingAs($superadmin)
        ->get(route('transactions.index'))
        ->assertSuccessful()
        ->assertSee('SUPERADMIN-TX-1');
});

it('bypasses gate and spatie permission checks for user id 1', function () {
    $superadmin = User::find(1);
    expect($superadmin)->not->toBeNull();

    expect(Gate::forUser($superadmin)->allows('any-permission-name'))->toBeTrue()
        ->and($superadmin->hasPermissionTo('transactions-list'))->toBeTrue()
        ->and($superadmin->hasRole('nonexistent-role'))->toBeTrue();
});

it('grants sidebar wildcard permissions to user id 1', function () {
    $superadmin = User::find(1);
    expect($superadmin)->not->toBeNull();

    $this->actingAs($superadmin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Transactions', false)
        ->assertSee('Jubelio', false);
});
