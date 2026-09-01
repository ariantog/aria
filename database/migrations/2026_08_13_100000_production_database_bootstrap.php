<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Single production bootstrap migration for an L10 → L12 database clone.
 *
 * Production bootstrap runs, in order:
 *
 * 1. `2026_08_13_115000_normalize_legacy_mysql_zero_dates` — null out `0000-00-00` dates on all tables
 * 2. `2026_08_12_100000_align_production_schema` — guarded ALTERs on existing prod tables
 * 3. `2026_08_12_200000_install_l12_production_tables` — guarded CREATEs for L12-only tables
 * 4. `2026_08_18_120000_create_warehouse_arrangement_refresh_jobs_table` — guarded CREATE
 * 5. `2026_08_19_070000_install_standalone_invoice_tables` — guarded CREATE/align
 * 6. `2026_08_19_080000_add_logo_path_to_standalone_invoices_table` — guarded column add
 * 6b. `2026_08_31_160000_add_settlement_fields_to_standalone_invoices_table` — paid/discount columns
 * 7. `2026_08_22_110000_install_reporting_tables` — guarded reporting schema install
 * 8. `2026_08_24_120000_install_reporting_summary_tables` — reporting aggregate tables
 * 9. `2026_08_24_130000_install_monthly_tax_summaries_table` — per-entity tax rollups
 * 10. `2026_08_29_100000_install_reporting_neraca_tables` — persediaan roll-forward + balance snapshots
 * 10b. `2026_08_29_140000_add_manufactured_cogs_to_reporting_monthly_inventory_values` — HPP produksi columns
 * 11. `2026_08_25_100000_bootstrap_shopee_ads_tables` — Shopee Ads bot tables
 * 12. `2026_08_13_120000_add_production_not_null_column_defaults` — MySQL `DEFAULT` on NOT NULL columns
 * 13. `2026_08_13_130000_fix_production_bigint_columns_to_int` — INT FK types for prod PKs
 * 14. `2026_09_01_210000_install_jubelio_stock_check_columns` — sync_cursor + discrepancy qty columns
 *
 * Safe to run on a fresh prod copy in one step:
 *
 *   php artisan migrate --path=database/migrations/2026_08_13_100000_production_database_bootstrap.php --force
 *
 * Never run a bare `php artisan migrate` against production: the greenfield
 * CREATE TABLE history targets tables that already exist there.
 *
 * See doc/production-schema-diff.md for old.sql vs L12 target schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        (require __DIR__.'/2026_08_13_115000_normalize_legacy_mysql_zero_dates.php')->up();
        (require __DIR__.'/2026_08_12_100000_align_production_schema.php')->up();
        (require __DIR__.'/2026_08_12_200000_install_l12_production_tables.php')->up();
        (require __DIR__.'/2026_08_18_120000_create_warehouse_arrangement_refresh_jobs_table.php')->up();
        (require __DIR__.'/2026_08_19_070000_install_standalone_invoice_tables.php')->up();
        (require __DIR__.'/2026_08_19_080000_add_logo_path_to_standalone_invoices_table.php')->up();
        (require __DIR__.'/2026_08_31_160000_add_settlement_fields_to_standalone_invoices_table.php')->up();
        (require __DIR__.'/2026_08_22_110000_install_reporting_tables.php')->up();
        (require __DIR__.'/2026_08_24_120000_install_reporting_summary_tables.php')->up();
        (require __DIR__.'/2026_08_24_130000_install_monthly_tax_summaries_table.php')->up();
        (require __DIR__.'/2026_08_29_100000_install_reporting_neraca_tables.php')->up();
        (require __DIR__.'/2026_08_29_140000_add_manufactured_cogs_to_reporting_monthly_inventory_values.php')->up();
        (require __DIR__.'/2026_08_25_100000_install_tax_faktur_imports_table.php')->up();
        (require __DIR__.'/2026_08_25_100100_add_payment_schedule_to_customers_table.php')->up();
        (require __DIR__.'/2026_08_25_100200_add_variance_transaction_id_to_tax_faktur_imports_table.php')->up();
        (require __DIR__.'/2026_08_27_100000_add_sell_transaction_id_to_tax_faktur_imports_table.php')->up();
        (require __DIR__.'/2026_08_31_120000_install_tax_faktur_import_sells_table.php')->up();
        (require __DIR__.'/2026_08_25_100000_bootstrap_shopee_ads_tables.php')->up();
        (require __DIR__.'/2026_08_26_120000_widen_shopee_ads_item_id_column.php')->up();
        (require __DIR__.'/2026_08_28_100000_add_shopee_ads_item_performance_and_topup.php')->up();
        (require __DIR__.'/2026_08_28_120000_create_data_retention_runs_table.php')->up();
        (require __DIR__.'/2026_08_29_100000_install_karyawan_gaji_table.php')->up();
        (require __DIR__.'/2026_08_29_100100_add_payroll_attendance_fields_to_karyawans_table.php')->up();
        (require __DIR__.'/2026_08_13_120000_add_production_not_null_column_defaults.php')->up();
        (require __DIR__.'/2026_08_13_130000_fix_production_bigint_columns_to_int.php')->up();
        (require __DIR__.'/2026_08_24_100000_install_user_preferences_table.php')->up();
        (require __DIR__.'/2026_08_29_100000_install_depreciation_register.php')->up();
        (require __DIR__.'/2026_08_31_100000_install_inventory_health_snapshots_table.php')->up();
        (require __DIR__.'/2026_08_31_120000_install_staff_role_checklists.php')->up();
        (require __DIR__.'/2026_09_01_150000_add_nama_absensi_to_karyawans_table.php')->up();
        (require __DIR__.'/2026_09_01_150100_add_izin_to_cutis_table.php')->up();
        (require __DIR__.'/2026_09_01_160000_add_absen_id_and_jam_kerja_to_karyawans_table.php')->up();
        (require __DIR__.'/2026_09_01_160100_install_hari_libur_table.php')->up();
        (require __DIR__.'/2026_09_01_160200_install_absensi_tables.php')->up();
        (require __DIR__.'/2026_09_01_160300_add_jam_kerja_hours_to_karyawan_gaji_table.php')->up();
        (require __DIR__.'/2026_09_01_170000_install_karyawan_cuti_sisa_tables.php')->up();
        (require __DIR__.'/2026_09_01_210000_install_jubelio_stock_check_columns.php')->up();
    }

    public function down(): void
    {
        (require __DIR__.'/2026_08_19_070000_install_standalone_invoice_tables.php')->down();
        (require __DIR__.'/2026_08_18_120000_create_warehouse_arrangement_refresh_jobs_table.php')->down();
        (require __DIR__.'/2026_08_12_200000_install_l12_production_tables.php')->down();
    }
};
