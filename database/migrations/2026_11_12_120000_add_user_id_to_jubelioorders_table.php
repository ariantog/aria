<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jubelioorders')) {
            return;
        }

        Schema::table('jubelioorders', function (Blueprint $table) {
            if (! Schema::hasColumn('jubelioorders', 'user_id')) {
                $table->unsignedInteger('user_id')->default(0)->after('execute_by');
                $table->index('user_id', 'jubelioorders_user_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('jubelioorders') || ! Schema::hasColumn('jubelioorders', 'user_id')) {
            return;
        }

        Schema::table('jubelioorders', function (Blueprint $table) {
            $table->dropIndex('jubelioorders_user_idx');
            $table->dropColumn('user_id');
        });
    }
};
