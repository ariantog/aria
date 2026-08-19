<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('item_identity_conversion_runs')) {
        Schema::create('item_identity_conversion_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('item_type');
            $table->boolean('dry_run')->default(false);
            $table->unsignedInteger('batch_size')->default(1000);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
        }

        if (! Schema::hasTable('item_identity_conversion_results')) {
        Schema::create('item_identity_conversion_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('item_identity_conversion_runs')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('failure_code', 40)->nullable();
            $table->text('detail')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'status']);
            $table->index('failure_code');
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('item_identity_conversion_results');
        Schema::dropIfExists('item_identity_conversion_runs');
    }
};

