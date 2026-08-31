<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Leftover Spatie names from removed /reports/cash-flow, /expense, /purchase pages
 * and the L10 ACL action "cash-flow" (now mapped to report-laba-rugi).
 */
class ObsoleteReportPermissions
{
    /**
     * @var list<string>
     */
    public const NAMES = [
        'report-cash-flow',
        'report-expense',
        'report-purchase',
        'cash-flow',
    ];

    /**
     * @return Collection<int, object{id: int|string, name: string}>
     */
    public static function existing(): Collection
    {
        $permissionTable = config('permission.table_names.permissions');
        if (! is_string($permissionTable) || ! Schema::hasTable($permissionTable)) {
            return collect();
        }

        return DB::table($permissionTable)
            ->whereIn('name', self::NAMES)
            ->orderBy('name')
            ->get(['id', 'name']);
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
