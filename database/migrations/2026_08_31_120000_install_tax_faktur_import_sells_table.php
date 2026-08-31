<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Many existing Sells can cover one imported faktur (MDS/Central consignment).
 *
 *   php artisan migrate --path=database/migrations/2026_08_31_120000_install_tax_faktur_import_sells_table.php --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tax_faktur_imports')) {
            return;
        }

        if (! Schema::hasTable('tax_faktur_import_sells')) {
            Schema::create('tax_faktur_import_sells', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tax_faktur_import_id')->constrained('tax_faktur_imports')->cascadeOnDelete();
                $table->unsignedInteger('sell_transaction_id');
                $table->timestamps();

                $table->unique('sell_transaction_id', 'tax_faktur_sells_tx_uidx');
                $table->index('tax_faktur_import_id', 'tax_faktur_sells_import_idx');
            });
        }

        if (! Schema::hasTable('tax_faktur_imports') || ! Schema::hasColumn('tax_faktur_imports', 'sell_transaction_id')) {
            return;
        }

        $now = now();
        $rows = DB::table('tax_faktur_imports')
            ->whereNotNull('sell_transaction_id')
            ->get(['id', 'sell_transaction_id']);

        foreach ($rows as $row) {
            $exists = DB::table('tax_faktur_import_sells')
                ->where('sell_transaction_id', $row->sell_transaction_id)
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('tax_faktur_import_sells')->insert([
                'tax_faktur_import_id' => $row->id,
                'sell_transaction_id' => $row->sell_transaction_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_faktur_import_sells');
    }
};
