<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('warehouse_stat_backfills')) {
            return;
        }

        Schema::create('warehouse_stat_backfills', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('idle');
            // Periods are stored as year * 12 + month so batches can walk them as integers.
            $table->integer('cursor_period')->default(0);
            $table->integer('oldest_period')->default(0);
            $table->integer('newest_period')->default(0);
            $table->unsignedInteger('months_total')->default(0);
            $table->unsignedInteger('months_done')->default(0);
            $table->unsignedBigInteger('rows_written')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stat_backfills');
    }
};
