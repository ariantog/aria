<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tiktok_shop_warehouse_syncs')) {
            return;
        }

        Schema::create('tiktok_shop_warehouse_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('shop_ref', 64);
            $table->string('shop_cipher', 64)->default('');
            $table->string('shop_name')->default('');
            $table->string('platform_warehouse_id', 64);
            $table->string('platform_warehouse_name')->default('');
            $table->string('platform_warehouse_type', 64)->default('');
            $table->string('commerce_platform', 32)->default('');
            $table->unsignedInteger('warehouse_id')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['shop_ref', 'platform_warehouse_id', 'commerce_platform'],
                'tts_wh_sync_uq',
            );
            $table->index('warehouse_id', 'tts_wh_sync_wh_idx');
            $table->index('shop_ref', 'tts_wh_sync_shop_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('tiktok_shop_warehouse_syncs')) {
            Schema::dropIfExists('tiktok_shop_warehouse_syncs');
        }
    }
};