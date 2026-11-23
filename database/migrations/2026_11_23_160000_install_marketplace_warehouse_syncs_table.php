<?php

use App\Services\Marketplace\MarketplaceWarehouseSyncBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_warehouse_syncs')) {
            Schema::create('marketplace_warehouse_syncs', function (Blueprint $table) {
                $table->id();
                $table->string('channel', 16);
                $table->string('commerce_platform', 32)->default('');
                $table->string('shop_ref', 64)->default('');
                $table->string('shop_cipher', 64)->default('');
                $table->string('shop_name')->default('');
                $table->string('platform_location_id', 32)->default('');
                $table->string('platform_warehouse_id', 64);
                $table->string('platform_warehouse_name')->default('');
                $table->string('platform_warehouse_type', 64)->default('');
                $table->unsignedInteger('warehouse_id')->default(0);
                $table->timestamp('last_synced_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(
                    ['channel', 'shop_ref', 'platform_location_id', 'platform_warehouse_id', 'commerce_platform'],
                    'mkt_wh_sync_uq',
                );
                $table->index('warehouse_id', 'mkt_wh_sync_wh_idx');
                $table->index(['channel', 'shop_ref'], 'mkt_wh_sync_ch_shop_idx');
            });
        }

        app(MarketplaceWarehouseSyncBackfill::class)->run();
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_warehouse_syncs')) {
            Schema::dropIfExists('marketplace_warehouse_syncs');
        }
    }
};