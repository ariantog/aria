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
        Schema::create('prod_borongan', function (Blueprint $table) {
            $table->id();
            $table->date('date')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('jahit_id')->constrained('prod_worker');
            $table->decimal('tres', 12, 2)->default(0);
            $table->decimal('permak', 12, 2)->default(0);
            $table->decimal('lain2', 12, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->integer('total_items')->default(0);
            $table->date('from')->nullable();
            $table->date('to')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prod_borongan');
    }
};
