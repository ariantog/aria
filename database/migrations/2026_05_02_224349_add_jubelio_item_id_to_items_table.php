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
        if (! Schema::hasColumn('items', 'jubelio_item_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->unsignedBigInteger('jubelio_item_id')->nullable()->after('pcode');
            });
        }
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('jubelio_item_id');
        });
    }
};
