<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('items') && ! Schema::hasColumn('items', 'cost_cnh')) {
            Schema::table('items', function (Blueprint $table) {
                $table->decimal('cost_cnh', 20, 2)->default(0)->after('cost');
            });
        }
    }

    public function down(): void
    {
        // Live production tables — do not drop columns.
    }
};
