<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    User::factory()->create();

    $this->admin = User::factory()->create();
    Permission::firstOrCreate(['name' => 'users-edit']);
    $this->admin->givePermissionTo('users-edit');

    Role::firstOrCreate(['name' => 'Editor', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web']);
});

it('updates a user role without submitting a password', function () {
    $user = User::factory()->create(['username' => 'role_change_user']);
    $user->syncRoles(['Viewer']);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), [
            'name' => $user->name,
            'username' => $user->username,
            'role' => 'Editor',
            'location_id' => null,
            'active' => 1,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    expect($user->fresh()->roles->pluck('name')->all())->toBe(['Editor']);
});

it('validates roles against the configured permission roles table', function () {
    config(['permission.table_names.roles' => 'roles']);

    $user = User::factory()->create(['username' => 'aria_role_user']);
    Role::firstOrCreate(['name' => 'Ops', 'guard_name' => 'web']);
    $user->syncRoles(['Viewer']);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), [
            'name' => $user->name,
            'username' => $user->username,
            'role' => 'Ops',
            'location_id' => null,
            'active' => 1,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    expect($user->fresh()->roles->pluck('name')->all())->toBe(['Ops']);
});

it('updates a user password when one is provided', function () {
    $user = User::factory()->create(['username' => 'password_change_user']);
    $user->syncRoles(['Viewer']);
    $oldHash = $user->password;

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), [
            'name' => $user->name,
            'username' => $user->username,
            'role' => 'Viewer',
            'location_id' => null,
            'active' => 1,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    expect($user->fresh()->password)->not->toBe($oldHash);
});
