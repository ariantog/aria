<?php

namespace App\Support;

use App\Models\Item;

class ItemQuantityValidator
{
    public static function validateQuantity(Item $item, float $quantity): ?string
    {
        if ($quantity < 1) {
            return 'Quantity must be at least 1.';
        }

        if (! $item->allowsDecimalQuantity()) {
            if (abs($quantity - round($quantity)) > 0.00001) {
                return "Quantity must be a whole number for {$item->code}.";
            }

            return null;
        }

        $rounded = round($quantity, 2);
        if (abs($quantity - $rounded) > 0.00001) {
            return "Quantity allows at most 2 decimal places for {$item->code}.";
        }

        return null;
    }

    /**
     * @param  list<array{item_id: int|string, quantity: float|int|string}>  $lines
     * @return list<string>
     */
    public static function validateLines(array $lines): array
    {
        $errors = [];
        $itemIds = collect($lines)->pluck('item_id')->filter()->unique()->map(fn ($id) => (int) $id)->all();
        if ($itemIds === []) {
            return [];
        }

        $items = Item::query()->whereIn('id', $itemIds)->get()->keyBy('id');

        foreach ($lines as $index => $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            $item = $items->get($itemId);
            if (! $item) {
                continue;
            }

            $message = self::validateQuantity($item, (float) ($line['quantity'] ?? 0));
            if ($message !== null) {
                $errors[] = $message;
            }
        }

        return $errors;
    }

    /**
     * Free-text invoice lines (no catalog item) — decimals always allowed, max 2 dp, min 1.
     */
    public static function validateFreeformQuantity(float $quantity): ?string
    {
        if ($quantity < 1) {
            return 'Quantity must be at least 1.';
        }

        $rounded = round($quantity, 2);
        if (abs($quantity - $rounded) > 0.00001) {
            return 'Quantity allows at most 2 decimal places.';
        }

        return null;
    }
}
