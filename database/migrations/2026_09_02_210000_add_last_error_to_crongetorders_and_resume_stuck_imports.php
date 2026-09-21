<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crongetorders') && ! Schema::hasColumn('crongetorders', 'last_error')) {
            Schema::table('crongetorders', function (Blueprint $table) {
                $table->text('last_error')->nullable();
            });
        }

        if (Schema::hasTable('crongetorders') && Schema::hasTable('scheduled_tasks')) {
            $running = DB::table('crongetorders')->where('status', 0)->exists();
            if ($running) {
                DB::table('scheduled_tasks')
                    ->where('command', 'jubelio:get-orders')
                    ->update(['active' => true]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crongetorders') && Schema::hasColumn('crongetorders', 'last_error')) {
            Schema::table('crongetorders', function (Blueprint $table) {
                $table->dropColumn('last_error');
            });
        }
    }
};
