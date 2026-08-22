<?php

namespace App\Models;

class ShopeeAds
{
    /**
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'shopee-ads-view',
            'edit' => 'shopee-ads-edit',
        ];
    }
}
