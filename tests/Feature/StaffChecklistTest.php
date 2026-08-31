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

it('seeds nine staff roles and assigns all to superadmin', function () {
    expect(\App\Models\StaffRole::count())->toBe(count(StaffChecklistCatalog::roles()))
        ->and(\App\Models\ChecklistTemplate::count())->toBeGreaterThan(50);

    $superadmin = User::find(1);
    expect($superadmin->staffRoles)->toHaveCount(9);
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
    $user = User::find(1);
    $template = \App\Models\ChecklistTemplate::query()->where('frequency', 'daily')->first();

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
    $user = User::find(1);
    $data = app(StaffChecklistService::class)->forUser($user);

    expect($data['has_checklists'])->toBeTrue()
        ->and($data['groups'])->not->toBeEmpty()
        ->and(collect($data['groups'])->pluck('frequency')->all())->toContain('daily', 'weekly', 'biweekly', 'monthly');
});
