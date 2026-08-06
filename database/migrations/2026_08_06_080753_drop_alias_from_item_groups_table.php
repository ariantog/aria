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
        Schema::table('item_groups', function (Blueprint $table) {
            $table->dropColumn('alias');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_groups', function (Blueprint $table) {
            $table->string('alias')->nullable()->after('name');
        });
    }
};
