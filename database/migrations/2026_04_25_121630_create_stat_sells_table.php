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
        if (! Schema::hasTable('stat_sells')) {
            Schema::create('stat_sells', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('group_id')->nullable();
                $table->unsignedSmallInteger('bulan');
                $table->unsignedSmallInteger('tahun');
                $table->unsignedBigInteger('sender_id')->nullable();
                $table->unsignedTinyInteger('type');
                $table->decimal('sum_qty', 15, 2)->default(0);
                $table->decimal('sum_total', 15, 2)->default(0);
                $table->timestamps();

                $table->unique(['group_id', 'bulan', 'tahun', 'sender_id', 'type'], 'stat_sells_unique');

                $table->foreign('group_id')->references('id')->on('item_group')->onDelete('cascade');
                $table->foreign('sender_id')->references('id')->on('customers')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stat_sells');
    }
};
