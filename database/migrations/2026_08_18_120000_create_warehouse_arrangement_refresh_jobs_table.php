<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_arrangement_refresh_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('destination_warehouse_id');
            $table->integer('user_id')->nullable();
            $table->string('status')->default('created');
            $table->string('phase')->default('stats');
            $table->unsignedInteger('item_cursor')->default(0);
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('stats_rows_inserted')->default(0);
            $table->unsignedInteger('sync_candidates')->nullable();
            $table->unsignedInteger('sync_sources')->nullable();
            $table->text('error_message')->nullable();
            $table->text('result_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['destination_warehouse_id', 'status'], 'arr_refresh_dest_status_idx');
            $table->index('status', 'arr_refresh_status_idx');
            $table->foreign('user_id', 'arr_refresh_user_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_arrangement_refresh_jobs');
    }
};
