<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restock_cells')) {
        Schema::create('restock_cells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restock_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('color_id')->nullable()->constrained('tags')->nullOnDelete();
            $table->foreignId('size_id')->nullable()->constrained('tags')->nullOnDelete();
            $table->unsignedInteger('qty_restock')->default(0);
            $table->unsignedInteger('qty_production')->default(0);
            $table->unsignedInteger('qty_shipped')->default(0);
            $table->unsignedInteger('qty_missing')->default(0);
            $table->timestamp('missing_at')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->boolean('urgent_manual')->default(false);
            $table->timestamp('urgent_flagged_at')->nullable();
            $table->unsignedInteger('urgent_threshold')->nullable();
            $table->timestamps();

            $table->unique(['restock_sheet_id', 'item_id']);
            $table->index(['restock_sheet_id', 'color_id', 'size_id']);
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restock_cells');
    }
};

