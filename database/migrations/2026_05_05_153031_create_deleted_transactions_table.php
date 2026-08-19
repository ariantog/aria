<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('deleted')) {
        Schema::create('deleted', function (Blueprint $blueprint) {
            $blueprint->unsignedBigInteger('id')->primary();
            $blueprint->date('date')->nullable();
            $blueprint->tinyInteger('type')->unsigned()->nullable();
            $blueprint->date('due')->nullable();
            $blueprint->string('sender_type')->nullable();
            $blueprint->unsignedBigInteger('sender_id')->nullable();
            $blueprint->string('receiver_type')->nullable();
            $blueprint->unsignedBigInteger('receiver_id')->nullable();
            $blueprint->string('invoice')->nullable();
            $blueprint->string('reference_number')->nullable();
            $blueprint->text('description')->nullable();
            $blueprint->text('notes')->nullable();
            $blueprint->tinyInteger('submit_type')->nullable();
            $blueprint->decimal('total', 15, 2)->nullable();
            $blueprint->decimal('discount', 15, 2)->nullable();
            $blueprint->decimal('adjustment', 15, 2)->nullable();
            $blueprint->decimal('ppn', 15, 2)->nullable();
            $blueprint->decimal('real_total', 15, 2)->nullable();
            $blueprint->decimal('total_items', 15, 2)->nullable();
            $blueprint->decimal('sender_balance', 15, 2)->nullable();
            $blueprint->decimal('receiver_balance', 15, 2)->nullable();
            $blueprint->tinyInteger('status')->unsigned()->nullable();
            $blueprint->string('sync_hide', 1)->nullable();
            $blueprint->unsignedBigInteger('a_submit_by')->nullable();
            $blueprint->unsignedBigInteger('b_submit_by')->nullable();
            $blueprint->string('a_reference_id')->nullable();
            $blueprint->string('b_reference_id')->nullable();
            $blueprint->integer('submit_a_count')->nullable();
            $blueprint->integer('submit_b_count')->nullable();
            $blueprint->integer('jubelio_return')->nullable();
            $blueprint->unsignedBigInteger('user_id')->nullable();
            $blueprint->timestamp('created_at')->nullable();
            $blueprint->timestamp('updated_at')->nullable();
            $blueprint->timestamp('deleted_at')->nullable();
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deleted');
    }
};

