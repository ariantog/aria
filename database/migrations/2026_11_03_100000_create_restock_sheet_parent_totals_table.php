<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restock_sheet_parent_totals')) {
            return;
        }

        Schema::create('restock_sheet_parent_totals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restock_sheet_id')->constrained('restock_sheets')->cascadeOnDelete();
            $table->string('parent_pcode', 64);
            $table->unsignedInteger('qty_restock')->default(0);
            $table->unsignedInteger('qty_production')->default(0);
            $table->unsignedInteger('qty_shipped')->default(0);
            $table->unsignedInteger('qty_missing')->default(0);
            $table->unsignedInteger('urgent_count')->default(0);
            $table->timestamps();

            $table->unique(['restock_sheet_id', 'parent_pcode'], 'restock_parent_totals_sheet_pcode_uniq');
            $table->index('restock_sheet_id', 'restock_parent_totals_sheet_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restock_sheet_parent_totals');
    }
};
