<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Leftover Spatie rows from the legacy L10 ACL / route middleware naming
 * (spaces, dots, pipe-composites, single-token actions). L12 uses kebab names
 * from {@see L12PermissionRegistry}.
 */
class ObsoleteL10Permissions
{
    /**
     * @return Collection<int, object{id: int|string, name: string}>
     */
    public static function existing(): Collection
    {
        $permissionTable = config('permission.table_names.permissions');
        if (! is_string($permissionTable)) {
            return collect();
        }

        try {
            if (! Schema::hasTable($permissionTable)) {
                return collect();
            }

            return DB::table($permissionTable)
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->filter(fn ($row) => self::isObsoleteName((string) $row->name))
                ->values();
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Cannot inspect the permissions table. Check the database connection. '.$e->getMessage(),
                previous: $e,
            );
        }
    }

    public static function isObsoleteName(string $name): bool
    {
        if (L12PermissionRegistry::contains($name)) {
            return false;
        }

        if (in_array($name, ObsoleteReportPermissions::NAMES, true)) {
            return true;
        }

        if (str_contains($name, ' ') || str_contains($name, '.') || str_contains($name, '|')) {
            return true;
        }

        if (! str_contains($name, '-') && ! str_contains($name, '_')) {
            return true;
        }

        return true;
    }

    public static function remove(): int
    {
        $rows = self::existing();
        if ($rows->isEmpty()) {
            return 0;
        }

        $ids = $rows->pluck('id');
        $permissionTable = config('permission.table_names.permissions');

        foreach ([
            config('permission.table_names.role_has_permissions'),
            config('permission.table_names.model_has_permissions'),
        ] as $pivotTable) {
            if (is_string($pivotTable) && Schema::hasTable($pivotTable)) {
                DB::table($pivotTable)->whereIn('permission_id', $ids)->delete();
            }
        }

        $deleted = (int) DB::table($permissionTable)->whereIn('id', $ids)->delete();

        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $deleted;
    }
}
