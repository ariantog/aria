<?php

use Illuminate\Support\Facades\DB;

$deletedMigrations = [
    '2025_08_14_170933_add_two_factor_columns_to_users_table',
    '2026_02_12_093357_add_is_active_to_users_table',
    '2026_02_12_094116_add_location_id_to_users_table',
    '2026_02_12_103219_add_username_to_users_table',
    '2026_02_13_075110_add_type_to_locations_table',
    '2026_02_16_053604_add_details_to_locations_table',
    '2026_02_16_060549_change_address_column_in_locations_table',
    '2026_02_16_060726_add_addrbook_fields_to_locations_table',
    '2026_02_16_070218_cleanup_locations_table',
    '2026_02_20_170633_simplify_locations_table',
    '2026_02_13_082659_add_image_path_to_items_table',
    '2026_02_13_132139_add_jubelio_item_id_to_items_table',
    '2026_02_20_043755_add_qty_to_items_table',
    '2026_02_23_044234_add_jubelio_item_id_to_items_table',
    '2026_02_26_160136_add_soft_deletes_to_items_table',
    '2026_02_16_071855_create_addrbook_types_table',
    '2026_02_16_071856_add_type_id_to_addrbooks',
    '2026_02_16_072936_revert_addrbook_types_to_column',
    '2026_02_19_063322_update_addrbook_types_data',
    '2026_02_20_173944_remove_ppn_from_addrbooks_table',
    '2026_02_21_043401_revert_additional_fees_and_restore_ppn',
    '2026_02_20_172045_create_additional_fees_table',
    '2026_02_20_172046_create_addrbook_additional_fee_table',
    '2026_02_19_034235_add_balances_to_transactions_table',
    '2026_02_20_155240_add_total_items_to_transactions_table',
    '2026_02_21_075548_add_adjustment_to_transactions_table',
    '2026_02_23_050727_add_submit_type_to_transactions_table',
    '2026_02_19_163620_add_warehouse_type_to_warehouse_items_table',
    '2026_02_19_165642_update_unique_index_on_warehouse_items_table',
    '2026_02_21_040650_change_warehouse_reference_on_warehouse_items_table',
    '2026_02_20_101352_rename_addrbook_classes_to_addrbook_dailies',
    '2026_02_20_141208_sync_addrbook_dailies_type',
    '2026_02_12_094114_create_locations_table', // Replaced by 0001_01_00_
];

echo "Cleaning up migrations table...\n";

$deleted = DB::table('migrations')->whereIn('migration', $deletedMigrations)->delete();
echo "- Deleted {$deleted} old migration records.\n";

DB::table('migrations')->where('migration', '0001_01_00_000000_create_locations_table')->delete();

$existsCreate = DB::table('migrations')->where('migration', '2026_02_12_094114_create_locations_table')->exists();
if (!$existsCreate) {
    DB::table('migrations')->insert(['migration' => '2026_02_12_094114_create_locations_table', 'batch' => 1]);
    echo "- Inserted 2026_02_12_094114_create_locations_table into migrations.\n";
} else {
    echo "- 2026_02_12_094114_create_locations_table already exists in migrations.\n";
}

$existsRel = DB::table('migrations')->where('migration', '2026_02_12_094116_add_location_id_to_users_table')->exists();
if (!$existsRel) {
    DB::table('migrations')->insert(['migration' => '2026_02_12_094116_add_location_id_to_users_table', 'batch' => 1]);
    echo "- Inserted 2026_02_12_094116_add_location_id_to_users_table into migrations.\n";
} else {
    echo "- 2026_02_12_094116_add_location_id_to_users_table already exists in migrations.\n";
}

echo "Migration cleanup complete. You can now safely run 'php artisan migrate' or 'php artisan migrate:status'.\n";
