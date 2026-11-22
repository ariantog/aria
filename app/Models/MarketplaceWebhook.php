<?php

namespace App\Models;

/**
 * Permissions for unified Shopee/TikTok webhook inbox (read-only).
 */
class MarketplaceWebhook
{
    /**
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'marketplace-webhook-view',
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

        $perms = self::getPermissions();

        return $user->can($perms['view'])
            || $user->can(TikTokShop::getPermissions()['view'])
            || $user->can(Omnichannel::getPermissions()['view']);
    }
}