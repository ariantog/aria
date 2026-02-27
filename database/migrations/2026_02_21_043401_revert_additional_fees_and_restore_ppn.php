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
        Schema::table('addrbooks', function (Blueprint $table) {
            $table->boolean('ppn')->default(false)->after('is_online');
        });

        Schema::dropIfExists('addrbook_additional_fee');
        Schema::dropIfExists('additional_fees');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('additional_fees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('value', 15, 2);
            $table->string('type'); // percent, nominal
            $table->timestamps();
        });

        Schema::create('addrbook_additional_fee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addrbook_id')->constrained('addrbooks')->onDelete('cascade');
            $table->foreignId('additional_fee_id')->constrained('additional_fees')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::table('addrbooks', function (Blueprint $table) {
            $table->dropColumn('ppn');
        });
    }
};
