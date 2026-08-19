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
        if (! Schema::hasTable('stok_reports')) {
        Schema::create('stok_reports', function (Blueprint $table) {
            $table->id();
            $table->timestamp('generet_at');
            $table->string('type'); // cron/manual
            $table->foreignId('generet_by')->nullable()->constrained('users');
            $table->timestamps();
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stok_reports');
    }
};

