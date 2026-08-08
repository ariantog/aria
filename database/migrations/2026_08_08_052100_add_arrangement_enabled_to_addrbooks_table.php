<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addrbooks', function (Blueprint $table) {
            if (! Schema::hasColumn('addrbooks', 'arrangement_enabled')) {
                $table->boolean('arrangement_enabled')->default(false)->after('is_online');
            }
        });
    }

    public function down(): void
    {
        Schema::table('addrbooks', function (Blueprint $table) {
            if (Schema::hasColumn('addrbooks', 'arrangement_enabled')) {
                $table->dropColumn('arrangement_enabled');
            }
        });
    }
};
