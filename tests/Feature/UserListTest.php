<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();

    $this->admin = User::factory()->create(['is_active' => true]);
    Permission::firstOrCreate(['name' => 'users-list']);
    $this->admin->givePermissionTo('users-list');
});

it('shows only active users by default', function () {
    User::factory()->create(['username' => 'active_user', 'is_active' => true]);
    User::factory()->create(['username' => 'banned_user', 'is_active' => false]);

    $response = $this->actingAs($this->admin)->get(route('users.index'));

    $response->assertOk()
        ->assertSee('active_user')
        ->assertDontSee('banned_user');
});

it('can list banned users when filtered', function () {
    User::factory()->create(['username' => 'active_user', 'is_active' => true]);
    User::factory()->create(['username' => 'banned_user', 'is_active' => false]);

    $response = $this->actingAs($this->admin)->get(route('users.index', ['status' => 'banned']));

    $response->assertOk()
        ->assertSee('banned_user')
        ->assertDontSee('active_user');
});

it('can list all users when filtered', function () {
    User::factory()->create(['username' => 'active_user', 'is_active' => true]);
    User::factory()->create(['username' => 'banned_user', 'is_active' => false]);

    $response = $this->actingAs($this->admin)->get(route('users.index', ['status' => 'all']));

    $response->assertOk()
        ->assertSee('active_user')
        ->assertSee('banned_user');
});

it('can search banned users by username', function () {
    User::factory()->create(['username' => 'active_user', 'is_active' => true]);
    User::factory()->create(['username' => 'banned_user', 'is_active' => false]);

    $response = $this->actingAs($this->admin)->get(route('users.index', [
        'status' => 'banned',
        'q' => 'banned_user',
    ]));

    $response->assertOk()
        ->assertSee('banned_user')
        ->assertDontSee('active_user');
});
