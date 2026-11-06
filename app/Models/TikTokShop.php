<?php

namespace App\Models;

/**
 * Permission registry for TikTok Shop integration (customer-service chat).
 */
class TikTokShop
{
    /**
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'tiktok-shop-view',
            'reply' => 'tiktok-shop-reply',
        ];
    }
}
