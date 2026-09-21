<?php

namespace App\Enums;

enum ItemType: int
{
    case ITEM = 1;
    case ASSET_LANCAR = 2;
    case ASSET_TETAP = 3;
    case SERVICE = 5;

    public function label(): string
    {
        return match ($this) {
            self::ITEM => 'Item',
            self::ASSET_LANCAR => 'Asset Lancar',
            self::ASSET_TETAP => 'Asset Tetap',
            self::SERVICE => 'Service',
        };
    }

    /**
     * Accept an enum instance, raw int, or numeric string from Eloquent / legacy rows.
     */
    public static function coerce(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom((int) $value);
    }
}
