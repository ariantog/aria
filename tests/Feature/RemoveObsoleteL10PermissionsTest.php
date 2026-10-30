<?php

use App\Support\L12PermissionRegistry;
use App\Support\ObsoleteL10Permissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function seedLegacyL10Permissions(): Role
{
    $legacyNames = [
        'item list',
        'transactions.list',
        'create',
        'list',
        'restock',
        'items-contributor',
    ];

    $allNames = [...$legacyNames, 'transactions-list'];

    foreach ($allNames as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => 'legacy-l10-test', 'guard_name' => 'web']);
    $role->syncPermissions($allNames);

    return $role;
}

it('detects legacy L10 permission names as obsolete', function () {
    expect(ObsoleteL10Permissions::isObsoleteName('item list'))->toBeTrue();
    expect(ObsoleteL10Permissions::isObsoleteName('transactions.sell'))->toBeTrue();
    expect(ObsoleteL10Permissions::isObsoleteName('create'))->toBeTrue();
    expect(ObsoleteL10Permissions::isObsoleteName('items-contributor'))->toBeTrue();
    expect(ObsoleteL10Permissions::isObsoleteName('transactions-list'))->toBeFalse();
    expect(ObsoleteL10Permissions::isObsoleteName('export-readonly'))->toBeFalse();
});

it('removes leftover L10 permissions but keeps L12 registry names', function () {
    app(\App\Services\PermissionGenerator::class)->generateAll();

    $role = seedLegacyL10Permissions();

    $keep = 'transactions-list';
    expect(L12PermissionRegistry::contains($keep))->toBeTrue();

    $this->artisan('app:remove-obsolete-l10-permissions')
        ->assertSuccessful();

    foreach (['item list', 'transactions.list', 'create', 'list', 'restock', 'items-contributor'] as $name) {
        expect(Permission::where('name', $name)->exists())->toBeFalse();
    }

    expect(Permission::where('name', $keep)->exists())->toBeTrue();
    expect($role->fresh()->permissions->pluck('name')->all())->toBe([$keep]);
});

it('supports dry run listing without deleting', function () {
    seedLegacyL10Permissions();

    $this->artisan('app:remove-obsolete-l10-permissions', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(Permission::where('name', 'item list')->exists())->toBeTrue();
});

it('deletes leftover L10 permissions via the standalone migration', function () {
    seedLegacyL10Permissions();

    $migration = require database_path('migrations/2026_10_29_120000_remove_obsolete_l10_permissions.php');
    $migration->up();

    expect(Permission::where('name', 'create')->exists())->toBeFalse();
    expect(Permission::where('name', 'transactions-list')->exists())->toBeTrue();
});
