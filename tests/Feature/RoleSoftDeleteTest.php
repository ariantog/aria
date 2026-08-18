<?php

use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();

    $this->admin = User::factory()->create(['active' => true]);
    Permission::firstOrCreate(['name' => 'users-roles-list']);
    Permission::firstOrCreate(['name' => 'users-roles-delete']);
    $this->admin->givePermissionTo(['users-roles-list', 'users-roles-delete']);
});

it('soft deletes a role instead of removing it permanently', function () {
    $role = Role::create(['name' => 'Temporary', 'guard_name' => 'web']);

    $this->actingAs($this->admin)
        ->delete(route('roles.destroy', $role))
        ->assertRedirect();

    expect(Role::find($role->id))->toBeNull()
        ->and(Role::onlyTrashed()->find($role->id))->not->toBeNull();
});

it('lists deleted roles on the deleted index page', function () {
    $role = Role::create(['name' => 'Archived', 'guard_name' => 'web']);
    $role->delete();

    $this->actingAs($this->admin)
        ->get(route('roles.deleted.index'))
        ->assertOk()
        ->assertSee('Archived', false)
        ->assertSee('Deleted Roles', false);
});

it('restores a soft deleted role', function () {
    $role = Role::create(['name' => 'Restorable', 'guard_name' => 'web']);
    $role->delete();

    $this->actingAs($this->admin)
        ->post(route('roles.restore', $role->id))
        ->assertRedirect(route('roles.deleted.index'));

    expect(Role::find($role->id))->not->toBeNull()
        ->and(Role::onlyTrashed()->find($role->id))->toBeNull();
});

it('excludes soft deleted roles from the active index', function () {
    $role = Role::create(['name' => 'Hidden Role', 'guard_name' => 'web']);
    $role->delete();

    $this->actingAs($this->admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertDontSee('Hidden Role', false);
});

it('forbids restoring roles without delete permission', function () {
    $viewer = User::factory()->create(['active' => true]);
    Permission::firstOrCreate(['name' => 'users-roles-list']);
    $viewer->givePermissionTo('users-roles-list');

    $role = Role::create(['name' => 'Locked', 'guard_name' => 'web']);
    $role->delete();

    $this->actingAs($viewer)
        ->post(route('roles.restore', $role->id))
        ->assertForbidden();
});
