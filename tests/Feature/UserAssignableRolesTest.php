<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    User::factory()->create();

    $this->admin = User::factory()->create();
    Permission::firstOrCreate(['name' => 'users-create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'users-edit', 'guard_name' => 'web']);
    $this->admin->givePermissionTo(['users-create', 'users-edit']);

    Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Editor', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web']);
});

it('does not list the superadmin role on the create user form', function () {
    $this->actingAs($this->admin)
        ->get(route('users.create'))
        ->assertOk()
        ->assertSee('Editor')
        ->assertSee('Viewer')
        ->assertDontSee('value="superadmin"', false);
});

it('rejects assigning the superadmin role on create', function () {
    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => 'Evil Admin',
            'username' => 'evil_admin',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
            'role' => 'superadmin',
            'location_id' => '',
            'active' => 1,
        ])
        ->assertSessionHasErrors('role');

    expect(User::where('username', 'evil_admin')->exists())->toBeFalse();
});
