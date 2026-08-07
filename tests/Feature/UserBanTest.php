<?php

use App\Models\Transaction;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();

    $this->admin = User::factory()->create();
    Permission::firstOrCreate(['name' => 'users-edit']);
    $this->admin->givePermissionTo('users-edit');
});

it('bans a user without removing their history', function () {
    $user = User::factory()->create(['is_active' => true]);
    $transaction = Transaction::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($this->admin)
        ->post(route('users.ban', $user));

    $response->assertRedirect(route('users.index'))
        ->assertSessionHas('success');
    expect($user->fresh()->is_active)->toBeFalse();
    expect(User::query()->find($user->id))->not->toBeNull();
    expect($transaction->fresh()->user_id)->toBe($user->id);
});

it('unbans a banned user', function () {
    $user = User::factory()->create(['is_active' => false]);

    $response = $this->actingAs($this->admin)
        ->post(route('users.unban', $user));

    $response->assertRedirect(route('users.index'))
        ->assertSessionHas('success');
    expect($user->fresh()->is_active)->toBeTrue();
});

it('prevents banning the superadmin account', function () {
    $superadmin = User::find(1);

    $response = $this->actingAs($this->admin)
        ->post(route('users.ban', $superadmin));

    $response->assertRedirect(route('users.index'))
        ->assertSessionHas('error');
    expect($superadmin->fresh()->is_active)->toBeTrue();
});

it('prevents banning your own account', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('users.ban', $this->admin));

    $response->assertRedirect(route('users.index'))
        ->assertSessionHas('error');
    expect($this->admin->fresh()->is_active)->toBeTrue();
});

it('does not allow hard-deleting users', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->delete('/users/'.$user->id)
        ->assertMethodNotAllowed();

    expect(User::query()->find($user->id))->not->toBeNull();
});
