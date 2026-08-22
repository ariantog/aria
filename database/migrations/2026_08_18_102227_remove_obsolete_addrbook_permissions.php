<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
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
        $pivotTables = [
            config('permission.table_names.role_has_permissions'),
            config('permission.table_names.model_has_permissions'),
        ];

        $ids = DB::table($permissionTable)->whereIn('name', $obsolete)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        foreach ($pivotTables as $table) {
            DB::table($table)->whereIn('permission_id', $ids)->delete();
        }

        DB::table($permissionTable)->whereIn('id', $ids)->delete();

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Permissions are regenerated via PermissionGenerator when needed.
    }
};
