<?php

namespace App\Models;

/**
 * Permission anchor for the User Activity Audit admin page (no database table).
 */
class UserActivityAudit
{
    /**
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'user-activity-audit-view',
        ];
    }
}
