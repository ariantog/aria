<?php

namespace App\Services\Marketplace;

use App\Models\ShopeeFlexiShop;
use App\Models\Shopeesync;
use Illuminate\Support\Facades\Schema;

class ShopeeSyncInvoicePrefix
{
    /**
     * @return array<string, string> code => label
     */
    public static function selectablePrefixes(): array
    {
        return array_merge(
            ['SP' => 'Indonesia / domestic (SP)'],
            config('shopee_flexi.flexi_prefixes', []),
        );
    }

    public function invoiceForOrderSn(?int $shopId, string $orderSn): string
    {
        $orderSn = trim($orderSn);
        $prefix = $this->prefixForShopId($shopId);

        return $prefix.'-'.$orderSn;
    }

    public function prefixForShopId(?int $shopId): string
    {
        if ($shopId !== null && $shopId > 0) {
            if (Schema::hasTable('shopee_flexi_shops')) {
                $flexi = ShopeeFlexiShop::query()->where('shop_id', $shopId)->first();
                if ($flexi !== null && filled($flexi->invoice_prefix)) {
                    return strtoupper((string) $flexi->invoice_prefix);
                }
            }

            // Legacy fallback until warehouse rows are cleaned up.
            if (Schema::hasTable('shopee_syncs') && Schema::hasColumn('shopee_syncs', 'invoice_prefix')) {
                $legacy = Shopeesync::query()
                    ->where('shop_id', $shopId)
                    ->where('invoice_prefix', '!=', '')
                    ->orderBy('id')
                    ->value('invoice_prefix');
                if (filled($legacy)) {
                    return strtoupper((string) $legacy);
                }
            }
        }

        return strtoupper((string) config('shopee_flexi.domestic_prefix', 'SP'));
    }

    /**
     * FLEXI / export shop — registered in shopee_flexi_shops (not domestic SP-only sync rows).
     */
    public function isFlexiExportShop(?int $shopId): bool
    {
        if ($shopId === null || $shopId <= 0) {
            return false;
        }

        if (! Schema::hasTable('shopee_flexi_shops')) {
            return false;
        }

        return ShopeeFlexiShop::query()->where('shop_id', $shopId)->exists();
    }

    public function isIngestShop(?int $shopId): bool
    {
        if ($shopId === null || $shopId <= 0) {
            return false;
        }

        if (Schema::hasTable('shopee_flexi_shops')) {
            return ShopeeFlexiShop::query()
                ->where('shop_id', $shopId)
                ->where('direct_order_ingest', true)
                ->exists();
        }

        if (Schema::hasTable('shopee_syncs') && Schema::hasColumn('shopee_syncs', 'direct_order_ingest')) {
            return Shopeesync::query()
                ->where('shop_id', $shopId)
                ->where('direct_order_ingest', true)
                ->exists();
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function allKnownPrefixes(): array
    {
        $fromConfig = array_keys(config('shopee_flexi.flexi_prefixes', []));
        $fromDb = [];
        if (Schema::hasTable('shopee_flexi_shops')) {
            $fromDb = ShopeeFlexiShop::query()
                ->distinct()
                ->pluck('invoice_prefix')
                ->map(fn ($p) => strtoupper((string) $p))
                ->all();
        }

        return array_values(array_unique(array_merge(
            [strtoupper((string) config('shopee_flexi.domestic_prefix', 'SP'))],
            array_map('strtoupper', $fromConfig),
            $fromDb,
        )));
    }
}