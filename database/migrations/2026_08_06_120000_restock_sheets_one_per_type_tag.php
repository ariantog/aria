<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restock_sheets', function (Blueprint $table) {
            $table->dropUnique(['pcode']);
            $table->dropColumn('pcode');
        });

        Schema::table('restock_sheets', function (Blueprint $table) {
            $table->unique('type_tag_id');
        });
    }

    public function down(): void
    {
        Schema::table('restock_sheets', function (Blueprint $table) {
            $table->dropUnique(['type_tag_id']);
            $table->string('pcode')->after('id');
            $table->unique('pcode');
        });
    }
};
