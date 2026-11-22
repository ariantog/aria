<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tiktok_shop_syncs')) {
            Schema::create('tiktok_shop_syncs', function (Blueprint $table) {
                $table->id();
                $table->string('shop_ref', 64);
                $table->string('shop_cipher', 64)->default('');
                $table->string('shop_name')->default('');
                $table->unsignedInteger('warehouse_id');
                $table->timestamps();

                $table->unique('shop_ref', 'tts_sync_shop_ref_uq');
                $table->index('warehouse_id', 'tts_sync_wh_idx');
            });
        }

        if (! Schema::hasTable('marketplace_on_order_reservations')) {
            Schema::create('marketplace_on_order_reservations', function (Blueprint $table) {
                $table->id();
                $table->string('channel', 16);
                $table->string('order_ref', 64);
                $table->unsignedInteger('warehouse_id');
                $table->unsignedInteger('item_id');
                $table->decimal('qty', 14, 2)->default(0);
                $table->timestamps();

                $table->unique(
                    ['channel', 'order_ref', 'warehouse_id', 'item_id'],
                    'mkt_on_ord_res_uq',
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_on_order_reservations')) {
            Schema::dropIfExists('marketplace_on_order_reservations');
        }

        if (Schema::hasTable('tiktok_shop_syncs')) {
            Schema::dropIfExists('tiktok_shop_syncs');
        }
    }
};