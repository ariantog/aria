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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedTinyInteger('type');
            $table->date('due')->nullable();
            $table->nullableMorphs('sender');
            $table->nullableMorphs('receiver');
            $table->string('invoice')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->tinyInteger('submit_type')->default(1)->comment('1: aria submit, 2: cron jubelio');
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('adjustment', 15, 2)->default(0);
            $table->decimal('ppn', 15, 2)->default(0);
            $table->decimal('real_total', 15, 2)->default(0);
            $table->decimal('total_items', 15, 2)->default(0);
            $table->decimal('sender_balance', 15, 2)->default(0);
            $table->decimal('receiver_balance', 15, 2)->default(0);
            $table->unsignedTinyInteger('status')->default(0);
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
