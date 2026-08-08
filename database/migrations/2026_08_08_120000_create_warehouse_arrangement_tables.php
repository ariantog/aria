<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_arrangement_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_warehouse_id')->constrained('addrbooks')->cascadeOnDelete();
            $table->foreignId('source_warehouse_id')->constrained('addrbooks')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['destination_warehouse_id', 'source_warehouse_id'], 'arr_src_dest_src_unique');
        });

        Schema::create('warehouse_arrangement_pcode_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_warehouse_id')->constrained('addrbooks')->cascadeOnDelete();
            $table->string('pcode');
            $table->string('master')->nullable();
            $table->string('master_name')->nullable();
            $table->string('warna')->nullable();
            $table->unsignedSmallInteger('present_count')->default(0);
            $table->unsignedSmallInteger('total_count')->default(0);
            $table->decimal('completeness_pct', 5, 1)->default(0);
            $table->decimal('family_demand_365', 12, 2)->default(0);
            $table->json('sizes')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['destination_warehouse_id', 'pcode'], 'arr_pcode_dest_pcode_unique');
        });

        Schema::create('warehouse_arrangement_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_warehouse_id')->constrained('addrbooks')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('pcode')->nullable();
            $table->string('master')->nullable();
            $table->string('item_code')->nullable();
            $table->string('item_name')->nullable();
            $table->string('size_code')->nullable();
            $table->string('warna')->nullable();
            $table->decimal('demand_30', 12, 2)->default(0);
            $table->decimal('demand_90', 12, 2)->default(0);
            $table->decimal('demand_180', 12, 2)->default(0);
            $table->decimal('demand_365', 12, 2)->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['destination_warehouse_id', 'item_id'], 'arr_candidate_dest_item_unique');
            $table->index(['destination_warehouse_id', 'pcode'], 'arr_candidate_dest_pcode_idx');
        });

        Schema::create('warehouse_arrangement_candidate_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('warehouse_arrangement_candidates')->cascadeOnDelete();
            $table->foreignId('source_warehouse_id')->constrained('addrbooks')->cascadeOnDelete();
            $table->unsignedInteger('source_stock')->default(0);
            $table->unsignedInteger('suggested_qty')->default(1);
            $table->timestamps();

            $table->unique(['candidate_id', 'source_warehouse_id'], 'arr_cand_src_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_arrangement_candidate_sources');
        Schema::dropIfExists('warehouse_arrangement_candidates');
        Schema::dropIfExists('warehouse_arrangement_pcode_snapshots');
        Schema::dropIfExists('warehouse_arrangement_sources');
    }
};
