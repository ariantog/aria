<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restock_sheets')) {
        Schema::create('restock_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('pcode');
            $table->string('name');
            $table->foreignId('type_tag_id')->constrained('tags')->cascadeOnDelete();
            $table->foreignId('representative_group_id')->nullable()->constrained('item_group')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_saved_at')->nullable();
            $table->foreignId('last_saved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('pcode');
            $table->index('type_tag_id');
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restock_sheets');
    }
};

