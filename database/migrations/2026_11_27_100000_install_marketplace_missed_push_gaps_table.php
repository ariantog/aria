<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_missed_push_gaps')) {
            return;
        }

        Schema::create('marketplace_missed_push_gaps', function (Blueprint $table) {
            $table->increments('id');
            $table->string('channel', 32);
            $table->string('shop_ref', 64)->nullable();
            $table->string('order_ref', 128);
            $table->string('order_status', 64)->nullable();
            $table->timestamp('platform_update_at')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('ingested_at')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'order_ref'], 'mkt_missed_push_gap_ch_ord_uq');
            $table->index(['channel', 'ingested_at', 'detected_at'], 'mkt_missed_push_gap_open_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_missed_push_gaps')) {
            return;
        }

        Schema::dropIfExists('marketplace_missed_push_gaps');
    }
};