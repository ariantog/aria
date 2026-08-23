<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('item_stock_notifications')) {
            Schema::create('item_stock_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('item_id');
                $table->unsignedInteger('sold_out_warehouse_id');
                $table->unsignedInteger('source_warehouse_id');
                $table->decimal('source_stock', 12, 2)->default(0);
                $table->string('source_status', 32)->default('available');
                $table->unsignedInteger('trigger_transaction_id')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('dismissed_at')->nullable();
                $table->timestamps();

                $table->index(['dismissed_at', 'read_at', 'created_at'], 'item_stock_notif_active_idx');
                $table->index(['sold_out_warehouse_id', 'item_id'], 'item_stock_notif_dest_item_idx');
                $table->unique(
                    ['item_id', 'sold_out_warehouse_id', 'source_warehouse_id'],
                    'item_stock_notif_unique_pair',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('item_stock_notifications');
    }
};
