<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    User::factory()->create();

    $this->admin = User::factory()->create(['active' => true]);
    Permission::firstOrCreate(['name' => 'users-roles-list']);
    $this->admin->givePermissionTo('users-roles-list');
});

it('shows users assigned to a role', function () {
    $role = Role::firstOrCreate(['name' => 'Editor', 'guard_name' => 'web']);
    $assignedUser = User::factory()->create([
        'name' => 'Role Member',
        'username' => 'role_member',
        'active' => true,
    ]);
    $assignedUser->syncRoles([$role->name]);

    User::factory()->create([
        'name' => 'Other User',
        'username' => 'other_user',
    ]);

    $response = $this->actingAs($this->admin)->get(route('roles.show', $role));

    $response->assertOk()
        ->assertSee('Editor', false)
        ->assertSee('Role Member', false)
        ->assertSee('role_member', false)
        ->assertDontSee('Other User', false);
});

it('links role names on the roles index to the show page', function () {
    $role = Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web']);

    $response = $this->actingAs($this->admin)->get(route('roles.index'));

    $response->assertOk()
        ->assertSee(route('roles.show', $role->id), false)
        ->assertSee('Viewer', false);
});

it('forbids viewing role users without permission', function () {
    $role = Role::firstOrCreate(['name' => 'Restricted', 'guard_name' => 'web']);
    $viewer = User::factory()->create(['active' => true]);

    $this->actingAs($viewer)
        ->get(route('roles.show', $role))
        ->assertForbidden();
});
