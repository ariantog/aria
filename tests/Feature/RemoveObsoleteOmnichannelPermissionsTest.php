<?php

use App\Models\Omnichannel;
use App\Models\User;
use App\Support\ObsoleteOmnichannelPermissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function seedObsoleteOmnichannelPermissions(): Role
{
    foreach (ObsoleteOmnichannelPermissions::NAMES as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    Permission::firstOrCreate(['name' => Omnichannel::getPermissions()['orders'], 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => Omnichannel::getPermissions()['view'], 'guard_name' => 'web']);

    $role = Role::firstOrCreate(['name' => 'legacy-webhook-viewer', 'guard_name' => 'web']);
    $role->givePermissionTo(ObsoleteOmnichannelPermissions::NAMES);
    $role->givePermissionTo([
        Omnichannel::getPermissions()['orders'],
        Omnichannel::getPermissions()['view'],
    ]);

    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo(ObsoleteOmnichannelPermissions::NAMES);

    return $role;
}

it('lists leftover omnichannel permissions without deleting on dry-run', function () {
    seedObsoleteOmnichannelPermissions();

    $this->artisan('app:remove-obsolete-omnichannel-permissions', ['--dry-run' => true])
        ->expectsOutputToContain('marketplace-webhook-view')
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    foreach (ObsoleteOmnichannelPermissions::NAMES as $name) {
        expect(Permission::query()->where('name', $name)->exists())->toBeTrue();
    }
});

it('deletes leftover omnichannel permissions via artisan and leaves live omnichannel intact', function () {
    $role = seedObsoleteOmnichannelPermissions();

    $this->artisan('app:remove-obsolete-omnichannel-permissions')
        ->expectsOutputToContain('Deleted '.count(ObsoleteOmnichannelPermissions::NAMES).' leftover omnichannel permission(s).')
        ->assertSuccessful();

    foreach (ObsoleteOmnichannelPermissions::NAMES as $name) {
        expect(Permission::query()->where('name', $name)->exists())->toBeFalse();
    }

    expect($role->fresh()->hasPermissionTo(Omnichannel::getPermissions()['orders']))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo(Omnichannel::getPermissions()['view']))->toBeTrue();
});

it('deletes leftover omnichannel permissions via the standalone migration', function () {
    seedObsoleteOmnichannelPermissions();

    $migration = require database_path('migrations/2026_11_23_120000_remove_obsolete_omnichannel_permissions.php');
    $migration->up();

    foreach (ObsoleteOmnichannelPermissions::NAMES as $name) {
        expect(Permission::query()->where('name', $name)->exists())->toBeFalse();
    }
});

it('is a no-op when leftover omnichannel permissions are already gone', function () {
    $this->artisan('app:remove-obsolete-omnichannel-permissions')
        ->expectsOutputToContain('No leftover omnichannel permissions found.')
        ->assertSuccessful();
});