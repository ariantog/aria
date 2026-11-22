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
            'orders' => 'omnichannel-orders',
            'settings' => 'omnichannel-settings',
            'settings-edit' => 'omnichannel-settings-edit',
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

    public static function userCanOrders(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->is_superadmin) {
            return true;
        }

        $perms = self::getPermissions();

        return $user->can($perms['orders'])
            || $user->can($perms['view'])
            || $user->can(MarketplaceWebhook::getPermissions()['view'])
            || $user->can(TikTokShop::getPermissions()['view']);
    }

    public static function userCanSettings(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->is_superadmin) {
            return true;
        }

        $perms = self::getPermissions();

        return $user->can($perms['settings']) || $user->can($perms['view']);
    }

    public static function userCanSettingsEdit(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->is_superadmin) {
            return true;
        }

        return $user->can(self::getPermissions()['settings-edit']);
    }

    public static function userCanNavGroup(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->is_superadmin) {
            return true;
        }

        return self::userCanView($user)
            || self::userCanOrders($user)
            || self::userCanSettings($user)
            || MarketplaceWebhook::userCanView($user);
    }
}