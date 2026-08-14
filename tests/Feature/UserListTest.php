<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();

    $this->admin = User::factory()->create(['active' => true]);
    Permission::firstOrCreate(['name' => 'users-list']);
    $this->admin->givePermissionTo('users-list');
});

it('shows only active users by default', function () {
    User::factory()->create(['username' => 'active_user', 'active' => true]);
    User::factory()->create(['username' => 'banned_user', 'active' => false]);

    $response = $this->actingAs($this->admin)->get(route('users.index'));

    $response->assertOk()
        ->assertSee('active_user')
        ->assertDontSee('banned_user');
});

it('can list banned users when filtered', function () {
    User::factory()->create(['username' => 'active_user', 'active' => true]);
    User::factory()->create(['username' => 'banned_user', 'active' => false]);

    $response = $this->actingAs($this->admin)->get(route('users.index', ['status' => 'banned']));

    $response->assertOk()
        ->assertSee('banned_user')
        ->assertDontSee('active_user');
});

it('can list all users when filtered', function () {
    User::factory()->create(['username' => 'active_user', 'active' => true]);
    User::factory()->create(['username' => 'banned_user', 'active' => false]);

    $response = $this->actingAs($this->admin)->get(route('users.index', ['status' => 'all']));

    $response->assertOk()
        ->assertSee('active_user')
        ->assertSee('banned_user');
});

it('can search banned users by username', function () {
    User::factory()->create(['username' => 'active_user', 'active' => true]);
    User::factory()->create(['username' => 'banned_user', 'active' => false]);

    $response = $this->actingAs($this->admin)->get(route('users.index', [
        'status' => 'banned',
        'q' => 'banned_user',
    ]));

    $response->assertOk()
        ->assertSee('banned_user')
        ->assertDontSee('active_user');
});

it('links role and location to their edit pages when permitted', function () {
    $location = \App\Models\Location::factory()->create(['name' => 'Surabaya HQ']);
    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Editor', 'guard_name' => 'web']);
    $listedUser = User::factory()->create([
        'username' => 'linked_user',
        'location_id' => $location->id,
    ]);
    $listedUser->syncRoles([$role->name]);

    Permission::firstOrCreate(['name' => 'users-roles-edit']);
    Permission::firstOrCreate(['name' => 'users-locations-edit']);
    $this->admin->givePermissionTo(['users-roles-edit', 'users-locations-edit']);

    $response = $this->actingAs($this->admin)->get(route('users.index', ['status' => 'all']));

    $response->assertOk()
        ->assertSee(route('roles.edit', $role->id), false)
        ->assertSee(route('locations.edit', $location->id), false)
        ->assertSee('Editor')
        ->assertSee('Surabaya HQ');
});
