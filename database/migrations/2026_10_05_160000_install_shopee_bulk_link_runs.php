<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shopee_bulk_link_runs')) {
            return;
        }

        Schema::create('shopee_bulk_link_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->default(0);
            $table->string('filename', 255)->default('');
            $table->boolean('overwrite_existing')->default(false);
            $table->string('status', 20)->default('running');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('linked_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->timestamp('last_batch_at')->nullable();
            $table->longText('payload');
            $table->longText('recent_results')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_batch_at'], 'shopee_bulk_run_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopee_bulk_link_runs');
    }
};
