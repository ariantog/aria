<?php

namespace App\Models;

/**
 * Permissions for Grok / read-only bulk export (no Eloquent table).
 */
class ReadonlyExport
{
    /**
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'export' => 'export-readonly',
            'cost-view' => 'cost-view',
        ];
    }
}
