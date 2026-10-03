<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('items', 'track_inventory')) {
            Schema::table('items', function (Blueprint $table) {
                $table->boolean('track_inventory')->default(true)->after('qty');
            });
        }

        if (! Schema::hasColumn('items', 'allow_decimal_quantity')) {
            Schema::table('items', function (Blueprint $table) {
                $table->boolean('allow_decimal_quantity')->default(false)->after('track_inventory');
            });
        }

        if (Schema::hasColumn('items', 'track_inventory')) {
            DB::table('items')->where('type', 5)->update(['track_inventory' => false]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('items', 'allow_decimal_quantity')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('allow_decimal_quantity');
            });
        }

        if (Schema::hasColumn('items', 'track_inventory')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('track_inventory');
            });
        }
    }
};
