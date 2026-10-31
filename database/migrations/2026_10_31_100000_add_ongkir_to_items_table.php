<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('items') || Schema::hasColumn('items', 'ongkir')) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            $table->decimal('ongkir', 20, 2)->default(0)->after('cost_cnh');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('items') || ! Schema::hasColumn('items', 'ongkir')) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('ongkir');
        });
    }
};
