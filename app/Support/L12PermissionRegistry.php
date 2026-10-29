<?php

namespace App\Support;

/**
 * Canonical Spatie permission names generated from L12 model registries
 * ({@see \App\Services\PermissionGenerator}).
 */
class L12PermissionRegistry
{
    /** @var array<string, true>|null */
    private static ?array $nameSet = null;

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::nameSet());
    }

    public static function contains(string $name): bool
    {
        return isset(self::nameSet()[$name]);
    }

    /**
     * @return array<string, true>
     */
    private static function nameSet(): array
    {
        if (self::$nameSet !== null) {
            return self::$nameSet;
        }

        self::$nameSet = [];

        $modelPath = app_path('Models');
        $files = array_diff(scandir($modelPath), ['.', '..']);

        foreach ($files as $file) {
            $className = 'App\\Models\\'.pathinfo($file, PATHINFO_FILENAME);

            if (! class_exists($className) || ! method_exists($className, 'getPermissions')) {
                continue;
            }

            foreach ($className::getPermissions() as $permissionName) {
                self::$nameSet[$permissionName] = true;
            }
        }

        return self::$nameSet;
    }
}
