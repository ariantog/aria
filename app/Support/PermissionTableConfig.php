<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Production L10 stores Spatie permissions in aria_permissions / aria_roles.
 * Greenfield SQLite uses permissions / roles. Auto-detect when env is unset.
 */
class PermissionTableConfig
{
    public static function apply(): void
    {
        if (filled(env('PERMISSION_TABLE_PERMISSIONS')) || filled(env('PERMISSION_TABLE_ROLES'))) {
            return;
        }

        try {
            if (! Schema::hasTable('aria_permissions')) {
                return;
            }

            config([
                'permission.table_names.permissions' => 'aria_permissions',
                'permission.table_names.roles' => 'aria_roles',
            ]);

            if (app()->bound(PermissionRegistrar::class)) {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            }
        } catch (\Throwable) {
            // Database may be unavailable during early boot.
        }
    }
}
