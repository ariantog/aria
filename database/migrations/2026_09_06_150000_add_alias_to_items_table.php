<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('items', 'alias')) {
            Schema::table('items', function (Blueprint $table) {
                $table->string('alias', 100)->default('')->after('name');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('items', 'alias')) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('alias');
        });
    }
};
