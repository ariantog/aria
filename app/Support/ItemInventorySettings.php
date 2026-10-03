<?php

namespace App\Support;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Setting;

class ItemInventorySettings
{
    public const SETTING_DECIMAL_FOR_CATALOG = 'items.allow_decimal_quantity_enabled';

    public static function decimalQuantityEnabledForCatalog(): bool
    {
        return (bool) Setting::getValue(self::SETTING_DECIMAL_FOR_CATALOG, false);
    }

    public static function showDecimalQuantityOptionOnForm(ItemType $type): bool
    {
        if ($type === ItemType::SERVICE) {
            return true;
        }

        if ($type === ItemType::ITEM || $type === ItemType::ASSET_LANCAR) {
            return self::decimalQuantityEnabledForCatalog();
        }

        return false;
    }

    public static function showTrackInventoryOptionOnForm(ItemType $type): bool
    {
        return in_array($type, [ItemType::ITEM, ItemType::ASSET_LANCAR, ItemType::SERVICE], true);
    }

    /**
     * @param  array<string, mixed>|object  $input
     */
    public static function resolveTrackInventory(ItemType $type, array|object $input, ?Item $existing = null): bool
    {
        $data = is_array($input) ? $input : (array) $input;

        if (array_key_exists('track_inventory', $data)) {
            return (bool) $data['track_inventory'];
        }

        if (array_key_exists('tanpa_stok', $data)) {
            return ! (bool) $data['tanpa_stok'];
        }

        if ($existing !== null) {
            return $existing->tracksInventory();
        }

        return $type !== ItemType::SERVICE;
    }

    /**
     * @param  array<string, mixed>|object  $input
     */
    public static function resolveAllowDecimalQuantity(ItemType $type, array|object $input, ?Item $existing = null): bool
    {
        if (! self::showDecimalQuantityOptionOnForm($type)) {
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
