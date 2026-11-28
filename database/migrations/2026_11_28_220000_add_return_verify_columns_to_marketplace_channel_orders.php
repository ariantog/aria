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

        if (! Schema::hasColumn('marketplace_channel_orders', 'return_verified_at')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->dateTime('return_verified_at')->nullable()->after('posted_return_at');
            });
        }

        if (! Schema::hasColumn('marketplace_channel_orders', 'return_verified_by')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->unsignedInteger('return_verified_by')->default(0)->after('return_verified_at');
            });
        }

        if (! Schema::hasColumn('marketplace_channel_orders', 'return_verify_note')) {
            Schema::table('marketplace_channel_orders', function (Blueprint $table) {
                $table->string('return_verify_note', 255)->nullable()->after('return_verified_by');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_channel_orders')) {
            return;
        }

        foreach (['return_verify_note', 'return_verified_by', 'return_verified_at'] as $column) {
            if (Schema::hasColumn('marketplace_channel_orders', $column)) {
                Schema::table('marketplace_channel_orders', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};