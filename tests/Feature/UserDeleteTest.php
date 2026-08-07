<?php

use App\Models\Transaction;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();

    $this->admin = User::factory()->create();
    Permission::firstOrCreate(['name' => 'users-delete']);
    $this->admin->givePermissionTo('users-delete');
});

it('deletes a user and nulls their transaction references', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($this->admin)
        ->delete(route('users.destroy', $user));

    $response->assertRedirect(route('users.index'));
    expect(User::query()->find($user->id))->toBeNull();
    expect($transaction->fresh()->user_id)->toBeNull();
});

it('prevents deleting the superadmin account', function () {
    $superadmin = User::find(1);

    $response = $this->actingAs($this->admin)
        ->delete(route('users.destroy', $superadmin));

    $response->assertRedirect(route('users.index'))
        ->assertSessionHas('error');
    expect(User::query()->find(1))->not->toBeNull();
});

it('prevents deleting your own account', function () {
    $response = $this->actingAs($this->admin)
        ->delete(route('users.destroy', $this->admin));

    $response->assertRedirect(route('users.index'))
        ->assertSessionHas('error');
    expect(User::query()->find($this->admin->id))->not->toBeNull();
});
