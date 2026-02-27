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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->nullable()->constrained('item_groups')->nullOnDelete();
            $table->string('name');
            $table->string('code')->index();
            $table->string('pcode')->index();
            $table->integer('brand')->default(0);
            $table->integer('type')->default(1);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('cost', 15, 2)->nullable();
            $table->text('tag_ids')->nullable(); // Legacy support
            $table->text('description')->nullable();
            $table->text('description2')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
