<?php

use App\Models\User;
use App\Services\StaffChecklistService;
use App\Support\StaffChecklistCatalog;
use Database\Seeders\StaffRoleChecklistSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\SuperAdminSeeder::class);
    $this->seed(StaffRoleChecklistSeeder::class);
});

it('seeds nine staff roles without assigning them to superadmin', function () {
    expect(\App\Models\StaffRole::count())->toBe(count(StaffChecklistCatalog::roles()))
        ->and(\App\Models\ChecklistTemplate::count())->toBeGreaterThan(50);

    $superadmin = User::find(1);
    expect($superadmin->staffRoles)->toBeEmpty();
});

it('shows role checklists on dashboard for assigned user', function () {
    $user = User::factory()->create();
    $user->staffRoles()->sync([1]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Checklist peran', false)
        ->assertSee('Checklist harian', false);
});

it('does not show role checklists when user has no staff roles', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Checklist peran', false);
});

it('toggles checklist completion for current period', function () {
    $user = User::factory()->create();
    $user->staffRoles()->sync([1]);
    $template = \App\Models\ChecklistTemplate::query()->where('staff_role_id', 1)->where('frequency', 'daily')->first();

    $this->actingAs($user)
        ->post(route('checklist.toggle', $template))
        ->assertOk()
        ->assertJson(['completed' => true, 'template_id' => $template->id]);

    $periodKey = app(StaffChecklistService::class)->periodKeyFor($template->frequency);

    expect(\App\Models\ChecklistCompletion::query()
        ->where('user_id', $user->id)
        ->where('checklist_template_id', $template->id)
        ->where('period_key', $periodKey)
        ->exists())->toBeTrue();

    $this->actingAs($user)
        ->post(route('checklist.toggle', $template))
        ->assertOk()
        ->assertJson(['completed' => false]);

    expect(\App\Models\ChecklistCompletion::query()
        ->where('user_id', $user->id)
        ->where('checklist_template_id', $template->id)
        ->where('period_key', $periodKey)
        ->exists())->toBeFalse();
});

it('blocks toggle for templates outside user roles', function () {
    $user = User::factory()->create();
    $template = \App\Models\ChecklistTemplate::query()
        ->whereHas('staffRole', fn ($q) => $q->where('slug', 'pemilik'))
        ->first();

    $this->actingAs($user)
        ->post(route('checklist.toggle', $template))
        ->assertForbidden();
});

it('groups templates by frequency in checklist service', function () {
    $user = User::factory()->create();
    $user->staffRoles()->sync(\App\Models\StaffRole::query()->pluck('id'));
    $data = app(StaffChecklistService::class)->forUser($user);

    expect($data['has_checklists'])->toBeTrue()
        ->and($data['groups'])->not->toBeEmpty()
        ->and(collect($data['groups'])->pluck('frequency')->all())->toContain('daily', 'weekly', 'biweekly', 'monthly')
        ->and($data['summary']['pending'])->toBeGreaterThan(0);
});

it('shows header checklist link with pending count for assigned user', function () {
    $user = User::factory()->create();
    $user->staffRoles()->sync([1]);

    $pending = app(StaffChecklistService::class)->forUser($user)['summary']['pending'];

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-testid="header-checklist-link"', false)
        ->assertSee((string) $pending, false);
});

it('hides header checklist link when user has no staff roles', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('data-testid="header-checklist-link"', false);
});

it('renders dedicated my-checklist page for assigned user', function () {
    $user = User::factory()->create();
    $user->staffRoles()->sync([1]);

    $this->actingAs($user)
        ->get(route('my-checklist.index'))
        ->assertOk()
        ->assertSee('Checklist peran', false)
        ->assertSee('data-testid="staff-checklist-panel"', false);
});

it('redirects my-checklist to dashboard when user has no staff roles', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('my-checklist.index'))
        ->assertRedirect(route('dashboard'));
});

it('never shows a checklist for user id 1 even when roles are assigned', function () {
    $superadmin = User::find(1);
    $superadmin->staffRoles()->sync(\App\Models\StaffRole::query()->pluck('id'));

    expect(app(StaffChecklistService::class)->forUser($superadmin)['has_checklists'])->toBeFalse();

    $this->actingAs($superadmin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('data-testid="header-checklist-link"', false)
        ->assertDontSee('data-testid="staff-checklist-panel"', false);

    $this->actingAs($superadmin)
        ->get(route('my-checklist.index'))
        ->assertRedirect(route('dashboard'));
});

it('blocks checklist toggles for user id 1', function () {
    $template = \App\Models\ChecklistTemplate::query()->first();

    $this->actingAs(User::find(1))
        ->post(route('checklist.toggle', $template))
        ->assertForbidden();
});
