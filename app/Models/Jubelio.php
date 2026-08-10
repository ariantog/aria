<?php

namespace App\Models;

class Jubelio
{
    /**
     * Define permissions associated with Jubelio.
     * This model is used only for permission management based on sub-menus.
     *
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'jubelio-view',
            'sync' => 'jubelio-sync',
            'stock-check' => 'jubelio-stock-check',
        ];
    }
}
