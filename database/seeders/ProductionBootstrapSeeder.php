<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\PermissionGenerator;
use App\Support\PermissionTableConfig;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Idempotent bootstrap for an existing production database clone.
 *
 * Does NOT create users or demo data. Safe to run on prod copy / cutover.
 */
class ProductionBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        PermissionTableConfig::apply();

        $this->seedPermissions();
        $this->migrateContributorPermission();
        $this->syncSuperadminRolePermissions();
        $this->call(ScheduledTaskSeeder::class);
        $this->call(SettingSeeder::class);
    }

    private function seedPermissions(): void
    {
        app(PermissionGenerator::class)->generateAll();
        $this->command?->info('L12 permissions generated.');
    }

    /**
     * Mirrors 2026_08_08_120100_migrate_contributor_permission_to_product_performance.
     */
    private function migrateContributorPermission(): void
    {
        $newPermission = Permission::firstOrCreate([
            'name' => 'report-product-performance',
            'guard_name' => 'web',
        ]);

        $oldPermission = Permission::where('name', 'items-contributor')->where('guard_name', 'web')->first();
        if (! $oldPermission) {
            return;
        }

        Role::query()->whereHas('permissions', fn ($q) => $q->where('id', $oldPermission->id))
            ->each(function (Role $role) use ($newPermission): void {
                if (! $role->hasPermissionTo($newPermission)) {
                    $role->givePermissionTo($newPermission);
                }
            });
    }

    private function syncSuperadminRolePermissions(): void
    {
        $role = $this->resolveSuperadminRole();
        if (! $role) {
            $this->command?->warn('No superadmin Spatie role found — skip permission sync (user id 1 still bypasses ACL).');

            return;
        }

        $role->syncPermissions(Permission::all());
        $this->command?->info("Synced all L12 permissions to role: {$role->name}");
    }

    private function resolveSuperadminRole(): ?Role
    {
        foreach (['superadmin', 'Super Admin', 'SuperAdmin', 'super admin'] as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            if ($role) {
                return $role;
            }
        }

        $user = User::find(1);
        if ($user && $user->roles->isNotEmpty()) {
            return $user->roles->first();
        }

        return null;
    }
}
