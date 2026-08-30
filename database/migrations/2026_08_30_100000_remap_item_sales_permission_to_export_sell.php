<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissionTable = config('permission.table_names.permissions');
        if (! Schema::hasTable($permissionTable)) {
            return;
        }

        $newPermission = Permission::firstOrCreate([
            'name' => 'report-export-sell',
            'guard_name' => 'web',
        ]);

        $oldPermission = Permission::query()
            ->where('name', 'report-item-sales')
            ->where('guard_name', 'web')
            ->first();

        if (! $oldPermission) {
            return;
        }

        Role::query()
            ->whereHas('permissions', fn ($q) => $q->where('id', $oldPermission->id))
            ->each(function (Role $role) use ($newPermission): void {
                if (! $role->hasPermissionTo($newPermission)) {
                    $role->givePermissionTo($newPermission);
                }
            });

        $modelPivot = config('permission.table_names.model_has_permissions');
        if (Schema::hasTable($modelPivot)) {
            $holders = DB::table($modelPivot)->where('permission_id', $oldPermission->id)->get();

            foreach ($holders as $row) {
                $alreadyGranted = DB::table($modelPivot)
                    ->where('permission_id', $newPermission->id)
                    ->where('model_type', $row->model_type)
                    ->where('model_id', $row->model_id)
                    ->exists();

                if (! $alreadyGranted) {
                    DB::table($modelPivot)->insert([
                        'permission_id' => $newPermission->id,
                        'model_type' => $row->model_type,
                        'model_id' => $row->model_id,
                    ]);
                }
            }
        }

        $pivotTables = [
            config('permission.table_names.role_has_permissions'),
            $modelPivot,
        ];

        foreach ($pivotTables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('permission_id', $oldPermission->id)->delete();
            }
        }

        DB::table($permissionTable)->where('id', $oldPermission->id)->delete();

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        Permission::firstOrCreate([
            'name' => 'report-item-sales',
            'guard_name' => 'web',
        ]);
    }
};
