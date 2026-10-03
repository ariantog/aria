<?php

namespace App\Models;

/**
 * Permission anchor for Shopee seller stock (Open Platform product APIs).
 */
class ShopeeStock
{
    /**
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'shopee-stock-view',
            'sync' => 'shopee-stock-sync',
        ];
    }
}
