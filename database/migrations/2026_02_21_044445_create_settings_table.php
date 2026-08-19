<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Greenfield / SQLite bootstrap for settings. Production L10 rows are aligned
     * by 2026_08_12_* migrations (slug, group, location_id).
     */
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->nullable()->index();
            $table->string('name');
            $table->string('slug', 100)->nullable();
            $table->text('value')->nullable();
            $table->integer('location_id')->default(0);
            $table->timestamps();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

