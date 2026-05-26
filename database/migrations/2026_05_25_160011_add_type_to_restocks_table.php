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
        Schema::table('restocks', function (Blueprint $table) {
            $table->string('size_type')->nullable()->after('size_id'); // volum-size, alpha-size, all
        });

        Schema::table('restock_histories', function (Blueprint $table) {
            $table->string('size_type')->nullable()->after('size_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restocks', function (Blueprint $table) {
            $table->dropColumn('size_type');
        });

        Schema::table('restock_histories', function (Blueprint $table) {
            $table->dropColumn('size_type');
        });
    }
};
