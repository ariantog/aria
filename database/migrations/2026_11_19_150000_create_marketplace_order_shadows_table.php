<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_order_shadows')) {
            return;
        }

        Schema::create('marketplace_order_shadows', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 32)->index();
            $table->unsignedBigInteger('shop_id')->nullable()->index();
            $table->string('external_order_sn', 64)->index();
            $table->string('channel_status', 64)->nullable();
            $table->unsignedSmallInteger('last_push_code')->nullable();
            $table->string('compare_status', 32)->default('pending')->index();
            $table->string('jubelio_invoice', 128)->nullable()->index();
            $table->string('jubelio_order_status', 64)->nullable();
            $table->unsignedBigInteger('jubelio_order_id')->nullable();
            $table->json('channel_payload')->nullable();
            $table->string('compare_note', 255)->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'external_order_sn'], 'mkt_shadow_channel_sn_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_order_shadows');
    }
};