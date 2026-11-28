<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * shopee_push_inbox.order_status was varchar(32); code 30 ingest stores
 * FULFILLMENT:LOGISTICS_DELIVERY_DONE (35 chars). Align with marketplace_webhook_events (64).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shopee_push_inbox') || ! Schema::hasColumn('shopee_push_inbox', 'order_status')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $row = DB::selectOne(
                'SELECT CHARACTER_MAXIMUM_LENGTH AS len FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['shopee_push_inbox', 'order_status'],
            );

            if ($row && (int) $row->len >= 64) {
                return;
            }

            DB::statement('ALTER TABLE `shopee_push_inbox` MODIFY `order_status` VARCHAR(64) NULL');

            return;
        }

        if ($driver === 'sqlite') {
            // SQLite tests: no strict varchar width; unified events table already uses 64.
        }
    }

    public function down(): void
    {
        // shopee_push_inbox is live storage — do not shrink order_status.
    }
};