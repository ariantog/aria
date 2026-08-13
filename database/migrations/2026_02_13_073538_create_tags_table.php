<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Greenfield / SQLite test schema aligned with production tags (database/new.sql).
     * L12 align migration adds timestamps on MySQL when missing.
     */
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->unsignedTinyInteger('type')->index();
            $table->string('code', 50)->default('');
            $table->integer('item_type')->default(0)->index();
            $table->integer('price')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
