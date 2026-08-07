<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addrbook_location', function (Blueprint $table) {
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('addrbook_id')->constrained('addrbooks')->cascadeOnDelete();

            $table->primary(['location_id', 'addrbook_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addrbook_location');
    }
};
