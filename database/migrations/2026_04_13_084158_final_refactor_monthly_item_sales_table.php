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
        // Karena struktur sebelumnya berantakan (FK tidak terpasang dengan benar atau index hilang),
        // cara paling aman adalah Drop dan Recreate tabel summary.
        Schema::dropIfExists('monthly_item_sales');

        Schema::create('monthly_item_sales', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->foreignId('group_id')->nullable()->constrained('item_group')->onDelete('set null');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->decimal('qty_net', 15, 2)->default(0);
            $table->decimal('amount_net', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['year', 'month', 'group_id', 'customer_id'], 'item_sale_cust_unique');
            $table->index(['year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_item_sales');
    }
};
