<?php

namespace App\Models;

/**
 * Permissions for cross-channel ops UI (shadow compare; Jubelio still posts until cutover).
 */
class Omnichannel
{
    /**
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'omnichannel-view',
        ];
    }

    public static function userCanView(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->is_superadmin) {
            return true;
        }

        return $user->can(self::getPermissions()['view']);
    }
}