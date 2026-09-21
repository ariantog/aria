<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jubelio_item_link_runners')) {
            Schema::create('jubelio_item_link_runners', function (Blueprint $table) {
                $table->id();
                $table->boolean('paused')->default(false);
                $table->string('calls_hour_bucket', 13)->nullable();
                $table->unsignedSmallInteger('calls_hour_count')->default(0);
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('jubelio_item_link_attempts')) {
            Schema::create('jubelio_item_link_attempts', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('item_id');
                $table->string('outcome', 32);
                $table->string('search_q', 255);
                $table->unsignedInteger('matched_jubelio_item_id')->nullable();
                $table->unsignedSmallInteger('candidates_count')->default(0);
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['item_id', 'created_at'], 'jub_link_att_item_created_idx');
                $table->index(['outcome', 'created_at'], 'jub_link_att_outcome_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jubelio_item_link_attempts');
        Schema::dropIfExists('jubelio_item_link_runners');
    }
};
