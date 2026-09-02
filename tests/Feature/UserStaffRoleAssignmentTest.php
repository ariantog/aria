<?php

use App\Models\User;
use Database\Seeders\StaffRoleChecklistSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\SuperAdminSeeder::class);
    $this->seed(StaffRoleChecklistSeeder::class);

    Permission::firstOrCreate(['name' => 'users-edit', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'users-staff-roles-edit', 'guard_name' => 'web']);

    Role::firstOrCreate(['name' => 'Editor', 'guard_name' => 'web']);

    $this->editor = User::factory()->create(['username' => 'staff_role_editor']);
    $this->editor->givePermissionTo(['users-edit', 'users-staff-roles-edit']);

    $this->basicEditor = User::factory()->create(['username' => 'basic_editor']);
    $this->basicEditor->givePermissionTo('users-edit');
});

it('shows staff role assignment UI only for users with permission', function () {
    $target = User::factory()->create(['username' => 'assign_target']);

    $this->actingAs($this->editor)
        ->get(route('users.edit', $target))
        ->assertOk()
        ->assertSee('Peran operasional (checklist)', false)
        ->assertSee('data-testid="staff-role-assignment"', false);

    $this->actingAs($this->basicEditor)
        ->get(route('users.edit', $target))
        ->assertOk()
        ->assertDontSee('Peran operasional (checklist)', false)
        ->assertDontSee('data-testid="staff-role-assignment"', false);
});

it('assigns staff roles when editor has permission', function () {
    $target = User::factory()->create(['username' => 'role_sync_target']);
    $target->syncRoles(['Editor']);

    $this->actingAs($this->editor)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'username' => $target->username,
            'role' => 'Editor',
            'location_id' => null,
            'active' => 1,
            'staff_role_ids' => [1, 3],
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    expect($target->fresh()->staffRoles->pluck('id')->sort()->values()->all())->toBe([1, 3]);
});

it('ignores staff role changes from users without permission', function () {
    $target = User::factory()->create(['username' => 'protected_roles']);
    $target->syncRoles(['Editor']);
    $target->staffRoles()->sync([2]);

    $this->actingAs($this->basicEditor)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'username' => $target->username,
            'role' => 'Editor',
            'location_id' => null,
            'active' => 1,
            'staff_role_ids' => [1, 3],
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    expect($target->fresh()->staffRoles->pluck('id')->all())->toBe([2]);
});

it('shows checklist on dashboard after staff roles are assigned via user edit', function () {
    $target = User::factory()->create(['username' => 'checklist_user']);
    $target->syncRoles(['Editor']);

    $this->actingAs($this->editor)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'username' => $target->username,
            'role' => 'Editor',
            'location_id' => null,
            'active' => 1,
            'staff_role_ids' => [1],
        ])
        ->assertRedirect(route('users.index'));

    $this->actingAs($target)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Checklist peran', false);
});

it('clears staff roles when none are selected', function () {
    $target = User::factory()->create(['username' => 'clear_roles']);
    $target->syncRoles(['Editor']);
    $target->staffRoles()->sync([1, 2]);

    $this->actingAs($this->editor)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'username' => $target->username,
            'role' => 'Editor',
            'location_id' => null,
            'active' => 1,
        ])
        ->assertRedirect(route('users.index'));

    expect($target->fresh()->staffRoles)->toBeEmpty();
});
