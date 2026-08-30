<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('shows export sell under transactions for non-superadmin with report-export-sell', function () {
    Permission::firstOrCreate(['name' => 'report-export-sell']);

    User::factory()->create(); // ensure user id 1 exists
    $user = User::factory()->create();
    expect($user->is_superadmin)->toBeFalse();

    $user->syncPermissions(['report-export-sell']);

    $html = $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Export Sell', false)
        ->assertSee('Transactions', false)
        ->getContent();

    expect($html)->toContain('Transactions');
    expect(substr($html, strpos($html, 'Transactions'), strpos($html, 'Export Sell') - strpos($html, 'Transactions')))->toContain('Transactions');
});

it('shows export sell when permission is granted through a role', function () {
    Permission::firstOrCreate(['name' => 'report-export-sell']);

    User::factory()->create();
    $user = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'export-sell-viewer']);
    $role->syncPermissions(['report-export-sell']);
    $user->syncRoles([$role->name]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Export Sell', false)
        ->assertSee('Transactions', false);
});

it('does not list the retired item sale report in the sidebar', function () {
    Permission::firstOrCreate(['name' => 'report-export-sell']);

    $user = User::factory()->create();
    expect($user->is_superadmin)->toBeTrue();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Export Sell', false)
        ->assertDontSee('/reports/item-sales', false)
        ->assertDontSee("navLinkVisible('Item Sale', 'Reports')", false);
});

it('shows export sell in transactions section when user also has transactions-list', function () {
    Permission::firstOrCreate(['name' => 'report-export-sell']);
    Permission::firstOrCreate(['name' => 'transactions-list']);

    User::factory()->create();
    $user = User::factory()->create();
    $user->syncPermissions(['report-export-sell', 'transactions-list']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Export Sell', false)
        ->assertSee('List All', false);
});
