<?php

namespace App\Models;

/**
 * Permission anchor for Lark operational alert configuration (no Eloquent table).
 */
class LarkOpsSettings
{
    /**
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'lark-ops-settings-view',
            'edit' => 'lark-ops-settings-edit',
        ];
    }
}
