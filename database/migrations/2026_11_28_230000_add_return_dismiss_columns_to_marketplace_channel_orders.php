<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_channel_orders')) {
            return;
        }

        if (! Schema::hasColumn('marketplace_channel_orders', 'return_dismissed_at')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->dateTime('return_dismissed_at')->nullable()->after('return_verify_note');
            });
        }

        if (! Schema::hasColumn('marketplace_channel_orders', 'return_dismissed_by')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->unsignedInteger('return_dismissed_by')->default(0)->after('return_dismissed_at');
            });
        }

        if (! Schema::hasColumn('marketplace_channel_orders', 'return_dismiss_note')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->string('return_dismiss_note', 255)->nullable()->after('return_dismissed_by');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_channel_orders')) {
            return;
        }

        foreach (['return_dismiss_note', 'return_dismissed_by', 'return_dismissed_at'] as $column) {
            if (Schema::hasColumn('marketplace_channel_orders', $column)) {
                Schema::table('marketplace_channel_orders', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};