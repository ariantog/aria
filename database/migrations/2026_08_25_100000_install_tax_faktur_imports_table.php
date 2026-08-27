<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imported Faktur Pajak PDFs for PPN reporting (keluaran / masukan).
 *
 *   php artisan migrate --path=database/migrations/2026_08_25_100000_install_tax_faktur_imports_table.php --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tax_faktur_imports')) {
            Schema::create('tax_faktur_imports', function (Blueprint $table) {
                $table->id();
                $table->string('faktur_number', 20)->unique();
                $table->date('faktur_date')->nullable();
                $table->string('faktur_date_place')->nullable();
                $table->string('direction', 16);
                $table->foreignId('reporting_entity_id')->constrained('reporting_entities')->cascadeOnDelete();
                $table->unsignedInteger('counterparty_id');
                $table->string('seller_name');
                $table->string('seller_npwp', 20);
                $table->string('buyer_name');
                $table->string('buyer_npwp', 20);
                $table->decimal('gross_total', 15, 2)->default(0);
                $table->decimal('discount_total', 15, 2)->default(0);
                $table->decimal('dpp', 15, 2)->default(0);
                $table->decimal('ppn', 15, 2)->default(0);
                $table->decimal('ppnbm', 15, 2)->default(0);
                $table->smallInteger('report_year');
                $table->tinyInteger('report_month');
                $table->date('expected_payment_date')->nullable();
                $table->decimal('payment_received_amount', 15, 2)->nullable();
                $table->date('payment_received_date')->nullable();
                $table->decimal('payment_variance', 15, 2)->nullable();
                $table->unsignedInteger('variance_expense_addrbook_id')->nullable();
                $table->unsignedInteger('cash_in_transaction_id')->nullable();
                $table->unsignedInteger('variance_transaction_id')->nullable();
                $table->unsignedInteger('sell_transaction_id')->nullable();
                $table->string('signatory_name')->nullable();
                $table->string('source_format', 64)->nullable();
                $table->json('line_items')->nullable();
                $table->string('pdf_path')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('user_id');
                $table->timestamps();

                // MySQL identifier limit is 64 chars — always pass a short explicit name.
                $table->index(['report_year', 'report_month', 'reporting_entity_id'], 'tax_faktur_period_entity_idx');
                $table->index(['counterparty_id', 'expected_payment_date'], 'tax_faktur_cp_payment_idx');
            });

            return;
        }

        Schema::table('tax_faktur_imports', function (Blueprint $table) {
            if (! Schema::hasIndex('tax_faktur_imports', 'tax_faktur_period_entity_idx')) {
                $table->index(['report_year', 'report_month', 'reporting_entity_id'], 'tax_faktur_period_entity_idx');
            }
            if (! Schema::hasIndex('tax_faktur_imports', 'tax_faktur_cp_payment_idx')) {
                $table->index(['counterparty_id', 'expected_payment_date'], 'tax_faktur_cp_payment_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_faktur_imports');
    }
};
