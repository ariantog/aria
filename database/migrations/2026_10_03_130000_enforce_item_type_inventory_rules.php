<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('items', 'track_inventory')) {
            return;
        }

        DB::table('items')->where('type', 5)->update(['track_inventory' => false]);
        DB::table('items')->whereIn('type', [1, 2, 3])->update(['track_inventory' => true]);
    }

    public function down(): void
    {
        // Non-destructive: leave enforced values in place.
    }
};
