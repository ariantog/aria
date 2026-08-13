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
        if (! Schema::hasTable('scheduled_tasks')) {
            return;
        }

        if (! Schema::hasColumn('scheduled_tasks', 'expression') || Schema::hasColumn('scheduled_tasks', 'frequency')) {
            return;
        }

        Schema::table('scheduled_tasks', function (Blueprint $table) {
            $table->renameColumn('expression', 'frequency');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_tasks', function (Blueprint $table) {
            $table->renameColumn('frequency', 'expression');
        });
    }
};
