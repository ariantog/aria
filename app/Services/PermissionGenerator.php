<?php

namespace App\Services;

use Spatie\Permission\Models\Permission;

class PermissionGenerator
{
    /**
     * Generate standard permissions for a module.
     */
    public function generateForModule(string $modelName, string $guardName = 'web'): array
    {
        $className = str_starts_with($modelName, 'App\\Models\\') ? $modelName : 'App\\Models\\'.$modelName;

        if (! class_exists($className)) {
            throw new \Exception("Model '{$className}' not found.");
        }

        if (method_exists($className, 'getPermissions')) {
            $permissions = $className::getPermissions();
            $createdPermissions = [];

            foreach ($permissions as $permissionName) {
                $permission = Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => $guardName,
                ]);
                $createdPermissions[] = $permission;
            }

            return $createdPermissions;
        }

        return [];
    }

    /**
     * Generate permissions for all models that have getPermissions method.
     */
    public function generateAll(string $guardName = 'web'): array
    {
        $modelPath = app_path('Models');
        $files = array_diff(scandir($modelPath), ['.', '..']);
        $allCreated = [];

        foreach ($files as $file) {
            $modelName = pathinfo($file, PATHINFO_FILENAME);
            $className = 'App\\Models\\'.$modelName;

            if (class_exists($className) && method_exists($className, 'getPermissions')) {
                $created = $this->generateForModule($modelName, $guardName);
                $allCreated = array_merge($allCreated, $created);
            }
        }

        return $allCreated;
    }
}
