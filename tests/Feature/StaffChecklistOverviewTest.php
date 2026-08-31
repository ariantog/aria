<?php

use App\Models\User;
use App\Services\StaffChecklistOverviewService;
use App\Services\StaffChecklistService;
use Database\Seeders\StaffRoleChecklistSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\SuperAdminSeeder::class);
    $this->seed(StaffRoleChecklistSeeder::class);

    Permission::firstOrCreate(['name' => 'users-staff-roles-view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'users-staff-roles-edit', 'guard_name' => 'web']);

    $this->viewer = User::factory()->create(['username' => 'checklist_viewer']);
    $this->viewer->givePermissionTo('users-staff-roles-view');
});

it('requires permission to view staff checklist overview', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('staff-checklists.index'))
        ->assertForbidden();
});

it('shows role catalog, unmapped roles, and user completion progress', function () {
    $mappedUser = User::factory()->create(['username' => 'mapped_user', 'active' => true]);
    $mappedUser->staffRoles()->sync([1]);

    $unmappedUser = User::factory()->create(['username' => 'unmapped_user', 'active' => true]);

    $template = \App\Models\ChecklistTemplate::query()->where('staff_role_id', 1)->where('frequency', 'daily')->first();
    app(StaffChecklistService::class)->toggle($mappedUser, $template);

    $this->actingAs($this->viewer)
        ->get(route('staff-checklists.index'))
        ->assertOk()
        ->assertSee('Checklist Peran', false)
        ->assertSee('Daftar peran operasional', false)
        ->assertSee('Progress checklist per pengguna', false)
        ->assertSee('Pemilik / Direktur', false)
        ->assertSee('mapped_user', false)
        ->assertSee('unmapped_user', false)
        ->assertSee('data-testid="users-without-roles-panel"', false);
});

it('lists unmapped roles when no user is assigned', function () {
    \Illuminate\Support\Facades\DB::table('staff_role_user')->delete();

    $this->actingAs($this->viewer)
        ->get(route('staff-checklists.index'))
        ->assertOk()
        ->assertSee('data-testid="unmapped-roles-panel"', false)
        ->assertSee('Belum dipetakan', false);
});

it('builds overview data with completion timestamps', function () {
    $user = User::factory()->create(['active' => true]);
    $user->staffRoles()->sync([1]);

    $template = \App\Models\ChecklistTemplate::query()->where('staff_role_id', 1)->first();
    app(StaffChecklistService::class)->toggle($user, $template);

    $overview = app(StaffChecklistOverviewService::class)->build();

    expect($overview['summary']['roles_total'])->toBe(9)
        ->and($overview['users_without_roles'])->not->toBeEmpty()
        ->and(collect($overview['users'])->firstWhere('id', $user->id)['summary']['completed'])->toBeGreaterThan(0);
});

it('shows assign link when viewer can edit staff roles', function () {
    $this->viewer->givePermissionTo('users-staff-roles-edit');
    User::factory()->create(['username' => 'needs_roles', 'active' => true]);

    $this->actingAs($this->viewer)
        ->get(route('staff-checklists.index'))
        ->assertOk()
        ->assertSee('Tetapkan peran', false);
});
