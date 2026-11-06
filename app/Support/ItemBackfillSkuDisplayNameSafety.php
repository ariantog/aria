<?php

namespace App\Support;

use App\Enums\ItemType;
use App\Models\Item;
use App\Services\Items\ItemIdentityBuilder;
use InvalidArgumentException;

/**
 * Manufactured backfill only: replace `items.name` when the current display name is empty or still
 * pcode-like in the leading segment (before the first " - "), so custom marketing titles are not overwritten.
 * Non-manufactured items should be skipped by the artisan commands; if this helper is invoked for them,
 * it does not allow overwrite (except empty name).
 */
final class ItemBackfillSkuDisplayNameSafety
{
    /**
     * @param  non-empty-string  $currentName
     */
    public static function mayOverwriteDisplayName(Item $item, string $currentName): bool
    {
        $trimmed = trim($currentName);
        if ($trimmed === '') {
            return true;
        }

        $first = trim(explode(' - ', $trimmed, 2)[0] ?? '');
        if ($first === '') {
            return true;
        }

        $type = $item->type instanceof ItemType
            ? $item->type
            : ItemType::coerce($item->type) ?? ItemType::ITEM;

        if ($type !== ItemType::ITEM) {
            return false;
        }

        $builder = app(ItemIdentityBuilder::class);

        $candidates = array_values(array_unique([$first, $builder->normalizeManufacturedPcode($first)]));

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            try {
                $builder->validatePcode(ItemType::ITEM, $candidate);

                return true;
            } catch (InvalidArgumentException) {
                // try next
            }
        }

        $upper = strtoupper($first);

        // Manufactured master-only head (e.g. CX90032) when full colorway is not in the leading segment.
        if (preg_match('/^[A-Z]{2,3}[0-9]{4,6}$/', $upper) === 1) {
            return true;
        }

        // TYPE + pcode segment (e.g. AJD-CA32902, AJD-CX90233) — not always validated by ITEM pcode rules alone.
        if (preg_match('/^[A-Z]{2,3}-[A-Z]{1,10}[0-9]{4,6}$/', $upper) === 1) {
            return true;
        }

        // TYPE + full manufactured colorway in one segment (e.g. AJD-CX90032-01).
        if (preg_match('/^[A-Z]{2,3}-[A-Z]{2,3}[0-9]{5}-[0-9]{2,3}$/', $upper) === 1) {
            return true;
        }

        return false;
    }
}
