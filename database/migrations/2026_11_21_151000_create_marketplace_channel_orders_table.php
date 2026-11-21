<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_channel_orders')) {
            return;
        }

        Schema::create('marketplace_channel_orders', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16);
            $table->string('order_ref', 64);
            $table->string('shop_ref', 64)->nullable();
            $table->string('order_status', 64)->nullable();
            $table->dateTime('last_event_at');
            $table->dateTime('first_received_at');
            $table->dateTime('paid_at')->nullable();
            $table->unsignedBigInteger('last_webhook_event_id')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['channel', 'order_ref'], 'mkt_ch_ord_ch_ord_uidx');
            $table->index('last_event_at', 'mkt_ch_ord_last_ev_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_channel_orders');
    }
};