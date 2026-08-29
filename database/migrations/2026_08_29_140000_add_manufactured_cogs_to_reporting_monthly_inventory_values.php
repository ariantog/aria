<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive breakdown for manufactured COGS on the persediaan roll-forward.
 *
 *   php artisan migrate --path=database/migrations/2026_08_29_140000_add_manufactured_cogs_to_reporting_monthly_inventory_values.php --force
 *
 * HPP barang produksi = (borongan + Material Produksi) / pcs gudang.
 * Existing `cogs` stays the total (manufactured + purchased).
 */
return new class extends Migration
{
    /** @var list<string> */
    private const COLUMNS = [
        'pcs_manufactured',
        'borongan_labor',
        'manufactured_unit_cost',
        'manufactured_qty_sold',
        'manufactured_cogs',
        'purchased_cogs',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('reporting_monthly_inventory_values')) {
            return;
        }

        Schema::table('reporting_monthly_inventory_values', function (Blueprint $table) {
            if (! Schema::hasColumn('reporting_monthly_inventory_values', 'pcs_manufactured')) {
                $table->decimal('pcs_manufactured', 15, 2)->default(0)->after('production_cost');
            }
            if (! Schema::hasColumn('reporting_monthly_inventory_values', 'borongan_labor')) {
                $table->decimal('borongan_labor', 15, 2)->default(0)->after('pcs_manufactured');
            }
            if (! Schema::hasColumn('reporting_monthly_inventory_values', 'manufactured_unit_cost')) {
                $table->decimal('manufactured_unit_cost', 15, 4)->default(0)->after('borongan_labor');
            }
            if (! Schema::hasColumn('reporting_monthly_inventory_values', 'manufactured_qty_sold')) {
                $table->decimal('manufactured_qty_sold', 15, 2)->default(0)->after('manufactured_unit_cost');
            }
            if (! Schema::hasColumn('reporting_monthly_inventory_values', 'manufactured_cogs')) {
                $table->decimal('manufactured_cogs', 15, 2)->default(0)->after('manufactured_qty_sold');
            }
            if (! Schema::hasColumn('reporting_monthly_inventory_values', 'purchased_cogs')) {
                $table->decimal('purchased_cogs', 15, 2)->default(0)->after('manufactured_cogs');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reporting_monthly_inventory_values')) {
            return;
        }

        $drop = array_values(array_filter(
            self::COLUMNS,
            fn (string $column) => Schema::hasColumn('reporting_monthly_inventory_values', $column),
        ));

        if ($drop === []) {
            return;
        }

        Schema::table('reporting_monthly_inventory_values', function (Blueprint $table) use ($drop) {
            $table->dropColumn($drop);
        });
    }
};
