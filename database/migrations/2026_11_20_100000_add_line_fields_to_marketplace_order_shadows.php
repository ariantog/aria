<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_order_shadows')) {
            return;
        }

        Schema::table('marketplace_order_shadows', function (Blueprint $table) {
            if (! Schema::hasColumn('marketplace_order_shadows', 'line_summary')) {
                $table->json('line_summary')->nullable()->after('channel_payload');
            }
            if (! Schema::hasColumn('marketplace_order_shadows', 'jubelio_line_summary')) {
                $table->json('jubelio_line_summary')->nullable()->after('line_summary');
            }
            if (! Schema::hasColumn('marketplace_order_shadows', 'line_compare_status')) {
                $table->string('line_compare_status', 32)->nullable()->index()->after('jubelio_line_summary');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_order_shadows')) {
            return;
        }

        Schema::table('marketplace_order_shadows', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_order_shadows', 'line_compare_status')) {
                $table->dropColumn('line_compare_status');
            }
            if (Schema::hasColumn('marketplace_order_shadows', 'jubelio_line_summary')) {
                $table->dropColumn('jubelio_line_summary');
            }
            if (Schema::hasColumn('marketplace_order_shadows', 'line_summary')) {
                $table->dropColumn('line_summary');
            }
        });
    }
};