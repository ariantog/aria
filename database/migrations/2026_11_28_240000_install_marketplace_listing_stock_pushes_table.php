<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_listing_stock_pushes')) {
            return;
        }

        Schema::create('marketplace_listing_stock_pushes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('item_id');
            $table->string('channel', 16);
            $table->string('shop_ref', 64)->default('');
            $table->string('platform_location_id', 32)->default('');
            $table->string('trigger', 64);
            $table->string('idempotency_key', 191);
            $table->string('order_ref', 128)->nullable();
            $table->integer('requested_qty');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('outcome', 32)->default('pending');
            $table->text('response_snippet')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->timestamps();

            $table->unique('idempotency_key', 'mkt_lst_push_idem_uq');
            $table->index(['item_id', 'channel'], 'mkt_lst_push_item_ch_idx');
            $table->index(['order_ref', 'trigger'], 'mkt_lst_push_ord_trg_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_listing_stock_pushes')) {
            Schema::dropIfExists('marketplace_listing_stock_pushes');
        }
    }
};