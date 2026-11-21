<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tiktok_shop_webhook_inbox')) {
            return;
        }

        Schema::create('tiktok_shop_webhook_inbox', function (Blueprint $table) {
            $table->id();
            $table->string('tts_notification_id', 64)->unique('tts_wh_inbox_notif_uidx');
            $table->unsignedSmallInteger('event_type')->nullable()->index();
            $table->string('shop_id', 32)->nullable()->index();
            $table->string('order_id', 32)->nullable()->index();
            $table->string('order_status', 64)->nullable();
            $table->boolean('signature_ok')->default(true);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_shop_webhook_inbox');
    }
};