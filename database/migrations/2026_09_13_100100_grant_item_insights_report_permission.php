<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $newPermission = Permission::firstOrCreate([
            'name' => 'report-item-insights',
            'guard_name' => 'web',
        ]);

        $source = Permission::where('name', 'report-product-performance')->where('guard_name', 'web')->first();
        if (! $source) {
            return;
        }

        Role::query()->whereHas('permissions', fn ($q) => $q->where('id', $source->id))
            ->each(function (Role $role) use ($newPermission): void {
                if (! $role->hasPermissionTo($newPermission)) {
                    $role->givePermissionTo($newPermission);
                }
            });
    }

    public function down(): void
    {
        Permission::where('name', 'report-item-insights')->where('guard_name', 'web')->delete();
    }
};
