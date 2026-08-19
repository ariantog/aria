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
        if (! Schema::hasTable('scheduled_tasks')) {
        Schema::create('scheduled_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('command')->unique();
            $table->string('expression')->default('0 0 * * *'); // Default daily at midnight
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_tasks');
    }
};

