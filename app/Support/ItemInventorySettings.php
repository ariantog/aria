<?php

namespace App\Support;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Setting;

class ItemInventorySettings
{
    public const SETTING_DECIMAL_ITEMS = 'stuff.decimal_quantity.items';

    public const SETTING_DECIMAL_ASSET_LANCAR = 'stuff.decimal_quantity.asset_lancar';

    public const SETTING_DECIMAL_ASSET_TETAP = 'stuff.decimal_quantity.asset_tetap';

    public const SETTING_DECIMAL_SERVICES = 'stuff.decimal_quantity.services';

    /** @deprecated Use per-type stuff.decimal_quantity.* settings */
    public const LEGACY_SETTING_DECIMAL_CATALOG = 'items.allow_decimal_quantity_enabled';

    public static function decimalSettingSlugForType(ItemType $type): ?string
    {
        return match ($type) {
            ItemType::ITEM => self::SETTING_DECIMAL_ITEMS,
            ItemType::ASSET_LANCAR => self::SETTING_DECIMAL_ASSET_LANCAR,
            ItemType::ASSET_TETAP => self::SETTING_DECIMAL_ASSET_TETAP,
            ItemType::SERVICE => self::SETTING_DECIMAL_SERVICES,
            default => null,
        };
    }

    public static function decimalQuantityEnabledForType(ItemType $type): bool
    {
        $slug = self::decimalSettingSlugForType($type);
        if ($slug === null) {
            return false;
        }

        $value = Setting::getValue($slug, false);
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value !== 0;
        }
        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) $value;
    }

    public static function showDecimalQuantityOptionOnForm(ItemType $type): bool
    {
        return self::decimalQuantityEnabledForType($type);
    }

    public static function showTrackInventoryOptionOnForm(ItemType $type): bool
    {
        return false;
    }

    public static function tracksInventoryForType(ItemType $type): bool
    {
        return $type !== ItemType::SERVICE;
    }

    /**
     * @param  array<string, mixed>|object  $input
     */
    public static function resolveTrackInventory(ItemType $type, array|object $input, ?Item $existing = null): bool
    {
        return self::tracksInventoryForType($type);
    }

    /**
     * @param  array<string, mixed>|object  $input
     */
    public static function resolveAllowDecimalQuantity(ItemType $type, array|object $input, ?Item $existing = null): bool
    {
        if (! self::decimalQuantityEnabledForType($type)) {
            return false;
        }

        $data = is_array($input) ? $input : (array) $input;

        if (array_key_exists('allow_decimal_quantity', $data)) {
            return (bool) $data['allow_decimal_quantity'];
        }

        if ($existing !== null) {
            return $existing->allowsDecimalQuantity();
        }

        return false;
    }
}
