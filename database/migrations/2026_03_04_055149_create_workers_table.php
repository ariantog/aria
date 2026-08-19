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
        if (! Schema::hasTable('prod_worker')) {
        Schema::create('prod_worker', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('type')->comment('1: Potong, 2: Jahit, 3: QC');
            $table->timestamps();
            $table->softDeletes();
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prod_worker');
    }
};

