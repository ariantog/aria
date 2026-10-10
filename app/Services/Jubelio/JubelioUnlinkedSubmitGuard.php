<?php

namespace App\Services\Jubelio;

use App\Models\Item;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Models\User;

/**
 * Blocks sell/move create when Jubelio-mapped warehouses include unlinked SKUs,
 * unless the user has transactions-submit-jubelio-unlinked (superadmin exempt).
 */
final class JubelioUnlinkedSubmitGuard
{
    public static function userMaySubmitWithUnlinked(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->is_superadmin) {
            return true;
        }

        return $user->can(Transaction::getPermissions()['submit-jubelio-unlinked']);
    }

    /**
     * @param  list<int|string>  $itemIds
     */
    public static function blocksSubmit(string $typeSlug, int $senderId, int $receiverId, array $itemIds): bool
    {
        if (! in_array($typeSlug, ['sell', 'move'], true)) {
            return false;
        }

        if (! self::jubelioWarehouseMapped($typeSlug, $senderId, $receiverId)) {
            return false;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $itemIds), fn (int $id) => $id > 0)));
        if ($ids === []) {
            return false;
        }

        return self::anyItemUnlinked($ids);
    }

    public static function jubelioWarehouseMapped(string $typeSlug, int $senderId, int $receiverId): bool
    {
        $synced = Jubeliosync::query()->pluck('warehouse_id')->map(fn ($id) => (int) $id)->all();
        $set = array_flip($synced);

        if ($typeSlug === 'move') {
            return ($senderId > 0 && isset($set[$senderId]))
                || ($receiverId > 0 && isset($set[$receiverId]));
        }

        if ($typeSlug === 'sell') {
            return $senderId > 0 && isset($set[$senderId]);
        }

        return false;
    }

    /**
     * @param  list<int>  $itemIds
     */
    private static function anyItemUnlinked(array $itemIds): bool
    {
        $linkedCount = Item::query()
            ->whereIn('id', $itemIds)
            ->where('jubelio_item_id', '>', 0)
            ->count();

        return $linkedCount < count($itemIds);
    }
}
