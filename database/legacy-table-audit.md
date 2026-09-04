# Legacy table drop audit (draft)

Maintainer-only drops — **no agent migrations with `DROP TABLE`**. Update this file as review progresses.


Schema source: `database/old.sql` (L10 production snapshot).


## Process

1. Pull / refresh table structure (`database/old.sql` or prod export).

2. Map `app/Models/**` `$table` values → physical tables.

3. Ripgrep each remaining table in `app/`, `routes/`, `resources/views/`, `database/migrations/`, `tests/` (exclude `old.sql`).

4. Confirm **L10 is retired** for that table (shared MySQL).

5. Move confirmed rows to **Approved to drop**; maintainer runs `DROP TABLE` manually.


## Approved to drop

_None yet — maintainer sign-off required._


## Strong candidates (no model, no L12 code ref)

First pass — still verify L10 before drop.

- `accesses`
- `advices`
- `alert`
- `alertrules`
- `ams`
- `app_settings`
- `aproduksi`
- `balance_trackers`
- `borongandetail`
- `cron_stat_runs`
- `cronruns`
- `desty_payloads`
- `desty_syncs`
- `desty_warehouses`
- `gajihs`
- `geo_city`
- `geo_province`
- `hashtag_transaction`
- `hashtags`
- `idea_comments`
- `idea_milestones`
- `idea_personnel`
- `ideas`
- `item_stat`
- `itemalert`
- `loginlog`
- `monthly_records`
- `p_cuti`
- `p_pelanggaran`
- `personal_access_tokens`
- `personnel_cuti`
- `personnels`
- `po_details`
- `pos`
- `problem_solution`
- `problems`
- `promo_transaction`
- `promos`
- `reminder`
- `sitesettings`
- `solutions`
- `updater`
- `user_activity`

_43 tables._


## Manual review (referenced in L12, no Eloquent model)

- `tags` (40 files) — `app/Console/Commands/MigrateLegacyItems.php`
- `users` (25 files) — `app/Console/Commands/ImportLegacyAcl.php`
- `roles` (21 files) — `app/Console/Commands/ImportLegacyAcl.php`
- `locations` (16 files) — `app/Console/Commands/ImportLegacyAcl.php`
- `settings` (16 files) — `app/Http/Controllers/Restock/RestockSettingsController.php`
- `operations` (13 files) — `app/Console/Commands/MigrateLegacyJournalsCommand.php`
- `produksi` (13 files) — `app/Http/Controllers/BoronganController.php`
- `gaji` (11 files) — `app/Http/Controllers/GajiController.php`
- `item_tag` (10 files) — `app/Console/Commands/MigrateLegacyItems.php`
- `report` (10 files) — `app/Http/Controllers/Reports/ChannelPnlReportController.php`
- `jubelioorders` (8 files) — `app/Console/Commands/ImportLegacyJubelioData.php`
- `borongan` (7 files) — `app/Http/Controllers/BoronganController.php`
- `karyawans` (7 files) — `app/Http/Controllers/CutiController.php`
- `location_customer` (7 files) — `app/Console/Commands/ImportLegacyAcl.php`
- `jubelio_stock_discrepancies` (6 files) — `database/migrations/2026_05_18_143743_create_jubelio_stock_checks_tables.php`
- `jubelio_stock_checks` (5 files) — `database/migrations/2026_05_18_143743_create_jubelio_stock_checks_tables.php`
- `migrations` (5 files) — `database/migrations/2026_08_13_120000_add_production_not_null_column_defaults.php`
- `acl` (4 files) — `app/Console/Commands/ImportLegacyAcl.php`
- `cutis` (4 files) — `app/Http/Controllers/CutiController.php`
- `jubeliosyncs` (4 files) — `app/Console/Commands/ImportLegacyJubelioData.php`
- `restock_histories` (4 files) — `database/migrations/2026_05_06_132335_create_restock_histories_table.php`
- `restocks` (4 files) — `database/migrations/2026_05_06_132334_create_restocks_table.php`
- `stat_sells` (4 files) — `app/Console/Commands/SyncStatSells.php`
- `crongetorders` (3 files) — `database/migrations/2026_08_07_160000_create_crongetorders_table.php`
- `sessions` (3 files) — `database/migrations/0001_01_01_000000_create_users_table.php`
- `cron` (2 files) — `app/Services/DashboardService.php`
- `failed_jobs` (2 files) — `database/migrations/0001_01_01_000002_create_jobs_table.php`
- `jubelioreturns` (2 files) — `app/Console/Commands/ImportLegacyJubelioData.php`
- `logjubelios` (2 files) — `app/Console/Commands/ImportLegacyJubelioData.php`
- `warehouse_compares` (2 files) — `database/migrations/2026_04_09_074736_create_warehouse_compares_table.php`
- `aria_permissions` (1 files) — `app/Support/PermissionTableConfig.php`
- `aria_roles` (1 files) — `app/Support/PermissionTableConfig.php`
- `crongetorderdetails` (1 files) — `database/migrations/2026_08_07_160000_create_crongetorders_table.php`
- `gpu` (1 files) — `app/Services/LegacyAclMapper.php`
- `model_has_permissions` (1 files) — `database/migrations/2026_02_13_055633_create_permission_tables.php`
- _… and 5 more_

_40 tables._


## Active in L12 (has Eloquent `$table` mapping)

- `customer_class`
- `customers`
- `customerstat`
- `deleted`
- `deleted_details`
- `depreciation`
- `item_group`
- `items`
- `prod_borongan`
- `prod_borongandetail`
- `prod_produksi`
- `prod_worker`
- `transaction_details`
- `transactions`
- `warehouse_item`

_15 tables from old.sql have a model `$table` (includes `customers` → Addrbook, etc.)._
