<?php

namespace App\Services;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class PermissionGenerator
{
    /**
     * Generate standard permissions for a module.
     *
     * @param  string  $moduleName
     */
    public function generateForModule(string $modelName, string $guardName = 'web'): array
    {
        $className = 'App\\Models\\'.$modelName;

        // Attempt to resolve custom class path if not found in App\Models
        if (! class_exists($className)) {
            if (class_exists($modelName)) {
                $className = $modelName;
            } else {
                throw new \Exception("Model '{$className}' not found.");
            }
        }

        if (method_exists($className, 'getPermissions')) {
            $permissions = $className::getPermissions();
            $createdPermissions = [];

            foreach ($permissions as $permissionName) {
                // firstOrCreate handles skipping existing ones gracefully (idempotency)
                $permission = Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => $guardName,
                ]);
                $createdPermissions[] = $permission;
            }

            return $createdPermissions;
        }

        throw new \Exception("Model '{$className}' does not have a 'getPermissions' method.");
    }

    private function generateFromString(string $moduleName, string $guardName): array
    {
        $slug = Str::slug($moduleName);
        $actions = ['list', 'create', 'edit', 'delete'];
        $createdPermissions = [];

        foreach ($actions as $action) {
            $permissionName = "{$slug}-{$action}";

            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => $guardName,
            ]);

            $createdPermissions[] = $permission;
        }

        return $createdPermissions;
    }
}
