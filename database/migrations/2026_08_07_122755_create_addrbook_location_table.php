<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_customer', function (Blueprint $table) {
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->primary(['location_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_customer');
    }
};
