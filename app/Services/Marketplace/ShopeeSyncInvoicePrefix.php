<?php

namespace App\Services\Marketplace;

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
        if ($shopId !== null && $shopId > 0 && Schema::hasTable('shopee_syncs')) {
            $row = Shopeesync::query()
                ->where('shop_id', $shopId)
                ->orderByDesc('direct_order_ingest')
                ->orderByDesc('id')
                ->first();

            if ($row !== null && filled($row->invoice_prefix)) {
                return strtoupper((string) $row->invoice_prefix);
            }
        }

        return strtoupper((string) config('shopee_flexi.domestic_prefix', 'SP'));
    }

    /**
     * @return list<string>
     */
    public function allKnownPrefixes(): array
    {
        $fromConfig = array_keys(config('shopee_flexi.flexi_prefixes', []));
        $fromDb = Schema::hasTable('shopee_syncs')
            ? Shopeesync::query()
                ->where('invoice_prefix', '!=', '')
                ->distinct()
                ->pluck('invoice_prefix')
                ->map(fn ($p) => strtoupper((string) $p))
                ->all()
            : [];

        return array_values(array_unique(array_merge(
            [strtoupper((string) config('shopee_flexi.domestic_prefix', 'SP'))],
            array_map('strtoupper', $fromConfig),
            $fromDb,
        )));
    }
}