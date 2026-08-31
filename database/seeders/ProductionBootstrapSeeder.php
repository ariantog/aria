<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\PermissionGenerator;
use App\Support\PermissionTableConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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

        $permissionsTable = config('permission.table_names.permissions');
        if ($permissionsTable !== 'aria_permissions' && ! \Illuminate\Support\Facades\Schema::hasTable($permissionsTable)) {
            throw new \RuntimeException(
                "Spatie permissions table [{$permissionsTable}] not found. "
                .'On production MySQL, set PERMISSION_TABLE_PERMISSIONS=aria_permissions and '
                .'PERMISSION_TABLE_ROLES=aria_roles in .env, then run php artisan config:clear.'
            );
        }

        $this->seedPermissions();
        $this->migrateContributorPermission();
        $this->removeObsoleteAddrbookPermissions();
        $this->syncSuperadminRolePermissions();
        $this->call(ScheduledTaskSeeder::class);
        $this->call(SettingSeeder::class);
        $this->call(StaffRoleChecklistSeeder::class);
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

    /**
     * Mirrors 2026_08_18_102227_remove_obsolete_addrbook_permissions so a fresh
     * production bootstrap needs no extra data migration.
     */
    private function removeObsoleteAddrbookPermissions(): void
    {
        $obsolete = [
            'addrbook-list',
            'addrbook-create',
            'addrbook-edit',
            'addrbook-delete',
            'addrbook-other-list',
            'addrbook-other-create',
            'addrbook-other-edit',
            'addrbook-other-delete',
        ];

        $permissionTable = config('permission.table_names.permissions');
        $ids = DB::table($permissionTable)->whereIn('name', $obsolete)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        foreach ([
            config('permission.table_names.role_has_permissions'),
            config('permission.table_names.model_has_permissions'),
        ] as $pivotTable) {
            DB::table($pivotTable)->whereIn('permission_id', $ids)->delete();
        }

        DB::table($permissionTable)->whereIn('id', $ids)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->command?->info('Obsolete addrbook permissions removed.');
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
