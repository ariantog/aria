<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_webhook_events') && ! Schema::hasColumn('marketplace_webhook_events', 'commerce_platform')) {
            Schema::table('marketplace_webhook_events', function (Blueprint $table) {
                $table->string('commerce_platform', 32)->nullable()->after('order_status');
            });
        }

        if (Schema::hasTable('marketplace_channel_orders') && ! Schema::hasColumn('marketplace_channel_orders', 'commerce_platform')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->string('commerce_platform', 32)->nullable()->after('order_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_webhook_events') && Schema::hasColumn('marketplace_webhook_events', 'commerce_platform')) {
            Schema::table('marketplace_webhook_events', function (Blueprint $table) {
                $table->dropColumn('commerce_platform');
            });
        }

        if (Schema::hasTable('marketplace_channel_orders') && Schema::hasColumn('marketplace_channel_orders', 'commerce_platform')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->dropColumn('commerce_platform');
            });
        }
    }
};