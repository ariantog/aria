<?php

use App\Models\Report;
use App\Models\User;
use App\Support\ObsoleteReportPermissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function seedObsoleteReportPermissions(): Role
{
    foreach (ObsoleteReportPermissions::NAMES as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    Permission::firstOrCreate(['name' => 'report-laba-rugi', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'report-receivables', 'guard_name' => 'web']);

    $role = Role::firstOrCreate(['name' => 'legacy-finance-viewer', 'guard_name' => 'web']);
    $role->givePermissionTo(ObsoleteReportPermissions::NAMES);
    $role->givePermissionTo(['report-laba-rugi', 'report-receivables']);

    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo(ObsoleteReportPermissions::NAMES);

    return $role;
}

it('lists leftover report permissions without deleting on dry-run', function () {
    seedObsoleteReportPermissions();

    $this->artisan('app:remove-obsolete-report-permissions', ['--dry-run' => true])
        ->expectsOutputToContain('report-cash-flow')
        ->expectsOutputToContain('cash-flow')
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    foreach (ObsoleteReportPermissions::NAMES as $name) {
        expect(Permission::query()->where('name', $name)->exists())->toBeTrue();
    }
});

it('deletes leftover report permissions via artisan and leaves live reports intact', function () {
    $role = seedObsoleteReportPermissions();

    $this->artisan('app:remove-obsolete-report-permissions')
        ->expectsOutputToContain('Deleted 4 leftover report permission(s).')
        ->assertSuccessful();

    foreach (ObsoleteReportPermissions::NAMES as $name) {
        expect(Permission::query()->where('name', $name)->exists())->toBeFalse();
    }

    expect($role->fresh()->hasPermissionTo('report-laba-rugi'))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo('report-receivables'))->toBeTrue()
        ->and(Report::getPermissions())->toContain('report-laba-rugi');
});

it('deletes leftover report permissions via the standalone migration', function () {
    seedObsoleteReportPermissions();

    $migration = require database_path('migrations/2026_08_31_120000_remove_obsolete_report_permissions.php');
    $migration->up();

    foreach (ObsoleteReportPermissions::NAMES as $name) {
        expect(Permission::query()->where('name', $name)->exists())->toBeFalse();
    }
});

it('is a no-op when leftover report permissions are already gone', function () {
    $this->artisan('app:remove-obsolete-report-permissions')
        ->expectsOutputToContain('No leftover report permissions found.')
        ->assertSuccessful();
});
