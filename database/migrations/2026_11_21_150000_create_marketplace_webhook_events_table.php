<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_webhook_events')) {
            return;
        }

        Schema::create('marketplace_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16)->index('mkt_wh_ev_channel_idx');
            $table->string('idempotency_key', 128)->unique('mkt_wh_ev_idem_uidx');
            $table->string('event_type', 32)->nullable();
            $table->string('shop_ref', 64)->nullable()->index('mkt_wh_ev_shop_idx');
            $table->string('order_ref', 64)->nullable()->index('mkt_wh_ev_order_idx');
            $table->string('order_status', 64)->nullable();
            $table->boolean('signature_ok')->default(true);
            $table->json('payload');
            $table->timestamp('received_at')->useCurrent()->index('mkt_wh_ev_rcv_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_webhook_events');
    }
};