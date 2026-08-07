<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('runs legacy acl import in dry-run mode', function () {
    $this->artisan('app:import-legacy-acl', ['--dry-run' => true])
        ->assertSuccessful();
});

it('imports roles and permissions from legacy dump', function () {
    Permission::firstOrCreate(['name' => 'transactions-list', 'guard_name' => 'web']);

    $this->artisan('app:import-legacy-acl', [
        '--skip-users' => true,
        '--skip-locations' => true,
    ])->assertSuccessful();

    expect(Role::where('name', 'AdminClerk')->exists())->toBeTrue();

    $role = Role::where('name', 'AdminClerk')->first();
    expect($role?->permissions->pluck('name'))->toContain('transactions-list');
});
