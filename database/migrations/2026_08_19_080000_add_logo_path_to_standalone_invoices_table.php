<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('standalone_invoices') || Schema::hasColumn('standalone_invoices', 'logo_path')) {
            return;
        }

        Schema::table('standalone_invoices', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('signature_path');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('standalone_invoices') || ! Schema::hasColumn('standalone_invoices', 'logo_path')) {
            return;
        }

        Schema::table('standalone_invoices', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
