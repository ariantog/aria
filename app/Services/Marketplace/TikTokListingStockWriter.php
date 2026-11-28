<?php

namespace App\Services\Marketplace;

use App\Models\Item;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder until TikTok Partner inventory update is wired on aria3.
 */
class TikTokListingStockWriter
{
    /**
     * @return array{ok: bool, skipped: bool, http_status: int, snippet: ?string, error: ?string}
     */
    public function pushSellable(
        Item $item,
        string $shopRef,
        string $shopCipher,
        string $platformWarehouseId,
        int $qty,
        ?string $commercePlatform = null,
    ): array {
        Log::info('TikTok listing stock push skipped (pilot stub)', [
            'item_id' => $item->id,
            'shop_ref' => $shopRef,
            'platform_warehouse_id' => $platformWarehouseId,
            'commerce_platform' => $commercePlatform,
            'qty' => $qty,
        ]);

        return [
            'ok' => false,
            'skipped' => true,
            'http_status' => 0,
            'snippet' => 'tiktok_inventory_update_not_wired',
            'error' => null,
        ];
    }
}