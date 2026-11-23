<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceWarehouseSync;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-shot backfill into marketplace_warehouse_syncs (run from install migration).
 */
class MarketplaceWarehouseSyncBackfill
{
    public function run(): void
    {
        if (! Schema::hasTable('marketplace_warehouse_syncs')) {
            return;
        }

        $this->backfillFromShopeeSyncs();
        $this->backfillFromTiktokWarehouseSyncsTable();
    }

    private function backfillFromShopeeSyncs(): void
    {
        if (! Schema::hasTable('shopee_syncs')) {
            return;
        }

        $hasInvoicePrefix = Schema::hasColumn('shopee_syncs', 'invoice_prefix');
        $hasDirectIngest = Schema::hasColumn('shopee_syncs', 'direct_order_ingest');
        $hasLastPoll = Schema::hasColumn('shopee_syncs', 'last_order_poll_at');

        foreach (DB::table('shopee_syncs')->orderBy('id')->get() as $row) {
            $shopRef = (int) ($row->shop_id ?? 0) > 0 ? (string) (int) $row->shop_id : '';
            $platformWarehouseId = (string) ((int) ($row->shopee_warehouse_id ?? 0) ?: 0);
            if ($platformWarehouseId === '0') {
                continue;
            }

            $locationId = (string) ($row->shopee_location_id ?? '');

            $meta = [
                'legacy_shopee_sync_id' => (int) $row->id,
            ];
            if ($hasInvoicePrefix) {
                $meta['invoice_prefix'] = (string) ($row->invoice_prefix ?? 'SP');
            }
            if ($hasDirectIngest) {
                $meta['direct_order_ingest'] = (bool) ($row->direct_order_ingest ?? false);
            }
            if ($hasLastPoll && isset($row->last_order_poll_at)) {
                $meta['last_order_poll_at'] = $row->last_order_poll_at;
            }

            MarketplaceWarehouseSync::query()->updateOrCreate(
                [
                    'channel' => MarketplaceWarehouseSync::CHANNEL_SHOPEE,
                    'shop_ref' => $shopRef,
                    'platform_location_id' => $locationId,
                    'platform_warehouse_id' => $platformWarehouseId,
                    'commerce_platform' => '',
                ],
                [
                    'shop_cipher' => '',
                    'shop_name' => '',
                    'platform_warehouse_name' => (string) ($row->shopee_warehouse_name ?? ''),
                    'platform_warehouse_type' => '',
                    'warehouse_id' => (int) ($row->warehouse_id ?? 0),
                    'last_synced_at' => Carbon::parse($row->updated_at ?? now()),
                    'meta' => $meta,
                ],
            );
        }
    }

    private function backfillFromTiktokWarehouseSyncsTable(): void
    {
        if (! Schema::hasTable('tiktok_shop_warehouse_syncs')) {
            return;
        }

        foreach (DB::table('tiktok_shop_warehouse_syncs')->orderBy('id')->get() as $row) {
            MarketplaceWarehouseSync::query()->updateOrCreate(
                [
                    'channel' => MarketplaceWarehouseSync::CHANNEL_TIKTOK,
                    'shop_ref' => (string) ($row->shop_ref ?? ''),
                    'platform_location_id' => '',
                    'platform_warehouse_id' => (string) ($row->platform_warehouse_id ?? ''),
                    'commerce_platform' => (string) ($row->commerce_platform ?? ''),
                ],
                [
                    'shop_cipher' => (string) ($row->shop_cipher ?? ''),
                    'shop_name' => (string) ($row->shop_name ?? ''),
                    'platform_warehouse_name' => (string) ($row->platform_warehouse_name ?? ''),
                    'platform_warehouse_type' => (string) ($row->platform_warehouse_type ?? ''),
                    'warehouse_id' => (int) ($row->warehouse_id ?? 0),
                    'last_synced_at' => $row->last_synced_at,
                    'meta' => ['legacy_tiktok_wh_sync_id' => (int) $row->id],
                ],
            );
        }

        Schema::dropIfExists('tiktok_shop_warehouse_syncs');
    }
}