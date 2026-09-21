<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

it('shows produksi statistics under the produksi sidebar group', function () {
    Permission::firstOrCreate(['name' => 'report-produksi-potong']);

    User::factory()->create();
    $user = User::factory()->create();
    expect($user->is_superadmin)->toBeFalse();

    $user->syncPermissions(['report-produksi-potong']);

    $html = $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Statistik Potong', false)
        ->assertSee('Produksi', false)
        ->assertSee('>Report<', false)
        ->assertSee('text-indigo-600', false)
        ->getContent();

    expect($html)->toContain("navLinkVisible('Statistik Potong', 'Produksi')");
    expect($html)->not->toContain("navLinkVisible('Statistik Potong', 'Reports')");
});

it('does not list produksi statistics under the reports sidebar group', function () {
    Permission::firstOrCreate(['name' => 'report-produksi-potong']);

    User::factory()->create();
    $user = User::factory()->create();
    $user->syncPermissions(['report-produksi-potong']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee("navLinkVisible('Statistik Potong', 'Reports')", false);
});
