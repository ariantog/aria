<?php

use App\Models\ChecklistTemplate;
use App\Models\User;
use App\Services\StaffChecklistService;
use Database\Seeders\StaffRoleChecklistSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\SuperAdminSeeder::class);
    $this->seed(StaffRoleChecklistSeeder::class);

    Permission::firstOrCreate(['name' => 'users-staff-roles-view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'checklist-templates-edit', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'checklist-templates-delete', 'guard_name' => 'web']);

    $this->viewer = User::factory()->create(['username' => 'tpl_viewer']);
    $this->viewer->givePermissionTo('users-staff-roles-view');

    $this->editor = User::factory()->create(['username' => 'tpl_editor']);
    $this->editor->givePermissionTo(['users-staff-roles-view', 'checklist-templates-edit']);

    $this->deleter = User::factory()->create(['username' => 'tpl_deleter']);
    $this->deleter->givePermissionTo(['users-staff-roles-view', 'checklist-templates-delete']);
});

it('requires view permission to list templates', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('staff-checklists.templates.index'))
        ->assertForbidden();
});

it('shows templates list without edit controls for view-only users', function () {
    $template = ChecklistTemplate::query()->first();

    $this->actingAs($this->viewer)
        ->get(route('staff-checklists.templates.index'))
        ->assertOk()
        ->assertSee('Template Checklist', false)
        ->assertSee($template->title, false)
        ->assertDontSee('data-testid="create-checklist-template"', false)
        ->assertDontSee('data-testid="edit-template-'.$template->id.'"', false);
});

it('forbids template edits without checklist-templates-edit', function () {
    $template = ChecklistTemplate::query()->first();

    $this->actingAs($this->viewer)
        ->get(route('staff-checklists.templates.edit', $template))
        ->assertForbidden();

    $this->actingAs($this->viewer)
        ->put(route('staff-checklists.templates.update', $template), [
            'staff_role_id' => $template->staff_role_id,
            'frequency' => $template->frequency->value,
            'title' => 'Hacked title',
            'is_active' => 1,
        ])
        ->assertForbidden();

    expect($template->fresh()->title)->toBe($template->title);
});

it('updates a template when the editor has permission', function () {
    $template = ChecklistTemplate::query()->first();

    $this->actingAs($this->editor)
        ->put(route('staff-checklists.templates.update', $template), [
            'staff_role_id' => $template->staff_role_id,
            'frequency' => 'weekly',
            'title' => 'Judul diubah',
            'description' => 'Deskripsi baru',
            'route_name' => 'items.index',
            'route_query' => '',
            'sort_order' => 9,
            'is_active' => 1,
        ])
        ->assertRedirect(route('staff-checklists.templates.index'))
        ->assertSessionHas('success');

    $template->refresh();
    expect($template->title)->toBe('Judul diubah')
        ->and($template->frequency->value)->toBe('weekly')
        ->and($template->description)->toBe('Deskripsi baru');
});

it('forbids deletes without checklist-templates-delete', function () {
    $template = ChecklistTemplate::query()->first();

    $this->actingAs($this->editor)
        ->delete(route('staff-checklists.templates.destroy', $template))
        ->assertForbidden();

    expect(ChecklistTemplate::query()->whereKey($template->id)->exists())->toBeTrue();
});

it('soft-deletes a template and its completions', function () {
    $user = User::factory()->create();
    $user->staffRoles()->sync([1]);
    $template = ChecklistTemplate::query()->where('staff_role_id', 1)->first();
    app(StaffChecklistService::class)->toggle($user, $template);

    $this->actingAs($this->deleter)
        ->delete(route('staff-checklists.templates.destroy', $template))
        ->assertRedirect(route('staff-checklists.templates.index'));

    expect(ChecklistTemplate::query()->whereKey($template->id)->exists())->toBeFalse()
        ->and(ChecklistTemplate::withTrashed()->whereKey($template->id)->exists())->toBeTrue()
        ->and(\App\Models\ChecklistCompletion::query()->where('checklist_template_id', $template->id)->exists())->toBeFalse();

    $checklist = app(StaffChecklistService::class)->forUser($user);
    expect(collect($checklist['groups'])->flatMap(fn ($group) => $group['items'])->pluck('id'))->not->toContain($template->id);
});

it('does not recreate a deleted catalog template on reseed', function () {
    $template = ChecklistTemplate::query()->whereNotNull('catalog_key')->first();
    $key = $template->catalog_key;
    $template->delete();

    $this->seed(StaffRoleChecklistSeeder::class);

    expect(ChecklistTemplate::query()->where('catalog_key', $key)->exists())->toBeFalse()
        ->and(ChecklistTemplate::withTrashed()->where('catalog_key', $key)->exists())->toBeTrue();
});

it('creates a custom template with the edit permission', function () {
    $this->actingAs($this->editor)
        ->post(route('staff-checklists.templates.store'), [
            'staff_role_id' => 1,
            'frequency' => 'daily',
            'title' => 'Cek custom harian',
            'description' => null,
            'route_name' => 'dashboard',
            'route_query' => null,
            'sort_order' => 99,
            'is_active' => 1,
        ])
        ->assertRedirect(route('staff-checklists.templates.index'));

    expect(ChecklistTemplate::query()->where('title', 'Cek custom harian')->exists())->toBeTrue();
});
