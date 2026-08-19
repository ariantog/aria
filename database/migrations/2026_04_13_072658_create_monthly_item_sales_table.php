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
        if (! Schema::hasTable('monthly_item_sales')) {
        Schema::create('monthly_item_sales', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->foreignId('group_id')->nullable()->constrained('item_group')->onDelete('set null');
            $table->foreignId('sender_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->tinyInteger('type')->comment('2: sell, 15: return');
            $table->decimal('total_qty', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['year', 'month', 'group_id', 'sender_id', 'type'], 'item_sale_unique');
            $table->index(['year', 'month']);
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_item_sales');
    }
};

