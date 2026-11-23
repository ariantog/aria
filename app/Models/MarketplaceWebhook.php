<?php

namespace App\Models;

/**
 * Legacy permission stub for unified Shopee/TikTok webhook inbox redirects.
 *
 * Access is gated via {@see Omnichannel::userCanOrders()} only; no Spatie permissions are generated.
 */
class MarketplaceWebhook
{
    /**
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [];
    }

    public static function userCanView(?User $user): bool
    {
        return Omnichannel::userCanOrders($user);
    }
}