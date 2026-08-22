<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        Permission::where('name', 'report-product-performance')->where('guard_name', 'web')->delete();
    }
};
