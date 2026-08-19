<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standalone_invoices', function (Blueprint $table) {
            $table->string('preset_id', 64)->nullable()->after('template');
            $table->string('signature_path')->nullable()->after('signatory_name');
        });
    }

    public function down(): void
    {
        Schema::table('standalone_invoices', function (Blueprint $table) {
            $table->dropColumn(['preset_id', 'signature_path']);
        });
    }
};
