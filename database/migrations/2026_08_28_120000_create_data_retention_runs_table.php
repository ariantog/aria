<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('data_retention_runs')) {
            return;
        }

        Schema::create('data_retention_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('transactions_copied')->default(0);
            $table->unsignedInteger('details_copied')->default(0);
            $table->unsignedInteger('customers_copied')->default(0);
            $table->unsignedInteger('items_copied')->default(0);
            $table->unsignedInteger('items_purged')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('archive_started_at')->nullable();
            $table->timestamp('archive_finished_at')->nullable();
            $table->timestamp('cleanup_started_at')->nullable();
            $table->timestamp('cleanup_finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_retention_runs');
    }
};
