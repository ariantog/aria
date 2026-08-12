<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Production L10 stores Spatie permissions in aria_permissions / aria_roles.
 * Greenfield SQLite uses permissions / roles.
 */
class PermissionTableConfig
{
    public static function apply(): void
    {
        try {
            if (self::shouldUseAriaTables()) {
                config([
                    'permission.table_names.permissions' => 'aria_permissions',
                    'permission.table_names.roles' => 'aria_roles',
                ]);
                self::forgetPermissionCache();
            }
        } catch (\Throwable) {
            // Database may be unavailable during early boot.
        }
    }

    public static function shouldUseAriaTables(): bool
    {
        if (! Schema::hasTable('aria_permissions')) {
            return false;
        }

        // Legacy prod has aria_* but no permissions table — always use aria_*.
        if (! Schema::hasTable('permissions')) {
            return true;
        }

        // MySQL deployments default to aria_* unless standard tables were explicitly chosen.
        return env('DB_CONNECTION', 'sqlite') === 'mysql'
            && ! filled(env('PERMISSION_TABLE_PERMISSIONS'));
    }

    private static function forgetPermissionCache(): void
    {
        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
