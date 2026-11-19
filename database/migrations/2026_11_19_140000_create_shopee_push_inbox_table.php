<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shopee_push_inbox')) {
            return;
        }

        Schema::create('shopee_push_inbox', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable()->index();
            $table->unsignedSmallInteger('push_code')->nullable()->index();
            $table->string('ordersn', 64)->nullable()->index();
            $table->string('order_status', 32)->nullable();
            $table->boolean('signature_ok')->default(true);
            $table->string('callback_url', 512)->nullable();
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopee_push_inbox');
    }
};