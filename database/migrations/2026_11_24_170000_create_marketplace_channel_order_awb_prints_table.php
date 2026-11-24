<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_channel_order_awb_prints')) {
            return;
        }

        Schema::create('marketplace_channel_order_awb_prints', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16);
            $table->string('order_ref', 64);
            $table->unsignedInteger('user_id');
            $table->dateTime('printed_at');
            $table->boolean('is_reprint')->default(false);

            $table->index(['channel', 'order_ref'], 'mkt_ch_awb_pr_ch_ord_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_channel_order_awb_prints');
    }
};