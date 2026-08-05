<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deleted_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('deleted_transactions', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deleted_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('deleted_transactions', 'discount_percent')) {
                $table->dropColumn('discount_percent');
            }
        });
    }
};
