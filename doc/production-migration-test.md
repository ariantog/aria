# Production migration test procedure

Use this to validate L12 on a **copy** of the live database before cutover.

## Overview

1. Clone production MySQL → dev/staging database (exact copy).
2. Point L12 `.env` at the clone.
3. Run **additive migrations only** (not full greenfield `CREATE TABLE` history).
4. Sync L12 permissions; smoke-test.
5. Fix issues; repeat; then run same steps on production.

---

## Step 1 — Copy production DB to dev

### Option A: cPanel (phpMyAdmin)

1. **Export** production database: phpMyAdmin → select prod DB → Export → Quick → SQL → Go. Save `prod-backup-YYYYMMDD.sql`.
2. **Drop** (or create empty) dev database in cPanel → MySQL Databases.
3. **Import** into dev DB: phpMyAdmin → dev DB → Import → choose the SQL file.
4. Note dev DB host, name, user, password for `.env`.

### Option B: Command line (SSH)

On a machine with access to both servers:

```bash
# Export from production (replace credentials)
mysqldump -h PROD_HOST -u PROD_USER -p \
  --single-transaction --routines --triggers \
  PROD_DATABASE > prod-backup-$(date +%Y%m%d).sql

# Recreate dev database
mysql -h DEV_HOST -u DEV_USER -p -e "DROP DATABASE IF EXISTS DEV_DATABASE; CREATE DATABASE DEV_DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import
mysql -h DEV_HOST -u DEV_USER -p DEV_DATABASE < prod-backup-YYYYMMDD.sql
```

### Option C: cPanel “Copy database”

Some hosts allow copying a DB to a new name in the same account — equivalent to export/import.

---

## Step 2 — Configure L12 for the clone

```env
DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=your_dev_clone_db
DB_USERNAME=...
DB_PASSWORD=...
```

Deploy L12 code (branch `cursor/migration` or later). Do **not** use SQLite for this test.

```bash
composer install --no-dev   # on server
php artisan config:clear
php artisan cache:clear
```

---

## Step 3 — Migrations (critical)

**Do not run `php artisan migrate` blindly** on a prod copy. Most L12 migration files are greenfield `CREATE TABLE` for SQLite CI; production tables already exist.

### 3a. Check status

```bash
php artisan migrate:status
```

You will see many migrations as “Pending”. That is expected.

### 3b. Run the two production-safe migrations

Do **not** run `php artisan migrate` (full history). Use these two paths only:

```bash
# 1. ALTER existing prod tables (customers, items, transactions, prod_produksi, …)
php artisan migrate --path=database/migrations/2026_08_12_100000_align_production_schema.php --force

# 2. CREATE L12-only tables (guarded — skips tables that already exist)
php artisan migrate --path=database/migrations/2026_08_12_200000_install_l12_production_tables.php --force
```

### 3c. What each migration does

| Migration | Purpose |
|-----------|---------|
| `2026_08_12_100000_align_production_schema` | Adds L12 columns to **existing** prod tables (`operation_id`, `arrangement_enabled` on `customers`; `legacy_code`, `restock_urgent_threshold` on `items`; etc.) |
| `2026_08_12_200000_install_l12_production_tables` | Creates **new** L12 tables only if missing |

**Tables created by `install_l12_production_tables`** (not in `database/old.sql`):

- `scheduled_tasks` — L12 cron manager (replaces legacy `cron` table usage)
- `jobs`, `job_batches`, `cache`, `cache_locks` — queue/cache drivers (prod already has `failed_jobs`)
- `monthly_account_summaries`, `monthly_category_summaries`, `daily_inventory_summaries`
- `monthly_item_sales`
- `stok_reports`, `stock_data`
- `warehouse_item_monthly_stats`, `product_performance_rollups`
- `warehouse_arrangement_*` (4 tables)
- `item_identity_conversion_runs`, `item_identity_conversion_results`
- `restock_sheets`, `restock_cells`, `restock_cell_histories` (L12 restock UI; prod keeps legacy `restocks` / `restock_histories`)

### 3d. Tables that already exist in production (do NOT re-create)

Skip `CREATE` migrations for: `customers`, `customerstat`, `customer_class`, `warehouse_item`, `item_group`, `items`, `tags`, `item_tag`, `transactions`, `transaction_details`, `users`, `prod_produksi`, `prod_worker`, `prod_borongan`, `prod_borongandetail`, `deleted`, `deleted_details`, `location_customer`, `locations`, `settings`, `jubelio*`, `stat_sells`, `warehouse_compares`, etc.

Reference: `database/old.sql`.

### 3e. Optional backfill

```bash
php artisan app:backfill-items-qty
```

---

## Step 4 — Bootstrap seeder (permissions + crons)

**Do not run** `DemoDataSeeder` or `SuperAdminSeeder` on a prod copy.

Set Spatie table names in `.env` for production MySQL (optional — auto-detected when `aria_permissions` exists):

```env
PERMISSION_TABLE_PERMISSIONS=aria_permissions
PERMISSION_TABLE_ROLES=aria_roles
```

Then run the idempotent bootstrap seeder (permissions, scheduled tasks, missing settings):

```bash
php artisan config:clear
php artisan db:seed --class=ProductionBootstrapSeeder
```

This will:

1. `PermissionGenerator::generateAll()` — create any missing L12 permission rows
2. Migrate `items-contributor` → `report-product-performance` on roles that had the old permission
3. `syncPermissions()` on the superadmin Spatie role (user id 1 bypasses ACL regardless)
4. Seed `scheduled_tasks` rows for the cron manager
5. `SettingSeeder` — `updateOrCreate` only; safe if settings already exist

---

## Step 5 — Runtime services

```bash
# Web (adjust host/port)
php artisan serve --host=0.0.0.0 --port=5000

# Queue worker (balance / summary jobs)
php artisan queue:listen
```

Login with an **existing production user** (username + password from prod DB). User id `1` is superadmin.

---

## Step 6 — Smoke test checklist

| Area | Action | Expect |
|------|--------|--------|
| Addrbook | Open customer/warehouse list | Reads `customers` |
| Addrbook | Create/edit contact | Writes `customers` |
| Transaction | Create buy/sell | `transactions` row: `invoice`, `due`, `ppn`, `real_total`, integer `sender_type` |
| Balance | After transaction | `customerstat.balance` updates |
| Stock | Buy into warehouse | `warehouse_item.quantity` changes |
| Items | List / edit | `items` + `item_group` |
| Produksi | Create potong entry | `prod_produksi.user_id` = current user |
| Reports | Nett cash / transaction list | No SQL errors on `customers` joins |

---

## Step 7 — If something breaks

### Foreign key errno 150 on new L12 tables

Production legacy tables use signed `INT(11)` primary keys (`customers.id`, `items.id`, etc.).
Laravel's default `foreignId()` is `BIGINT UNSIGNED`, which MySQL rejects as a FK target.

The install migration uses `integer()` for legacy FK columns. If a previous run failed partway,
re-run the install migration — it auto-drops any new tables that still have `BIGINT` FK columns.

### Unknown column `user_id` on `sessions`

L10 `sessions` only has `id`, `payload`, `last_activity`. Re-run the align migration (or install
migration, which includes the same guarded ALTER) to add `user_id`, `ip_address`, `user_agent`.

### Invalid default value for `updated_at` (MySQL 1067)

Legacy L10 tables use `DEFAULT '0000-00-00 00:00:00'` on timestamps. Strict MySQL rejects any `ALTER` on that table until fixed.

The align migration (`2026_08_12_100000`) now normalizes `created_at`/`updated_at` to `TIMESTAMP NULL` before adding columns. Pull latest `cursor/migration` and re-run.

Manual fix if needed before migrate (fix **updated_at first**, relax strict mode):

```sql
SET @old_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = REPLACE(REPLACE(@old_mode, 'NO_ZERO_DATE', ''), 'NO_ZERO_IN_DATE', '');

UPDATE `customers` SET `updated_at` = NULL WHERE `updated_at` IN ('0000-00-00 00:00:00', '0000-00-00');
ALTER TABLE `customers` MODIFY `updated_at` TIMESTAMP NULL DEFAULT NULL;

UPDATE `customers` SET `created_at` = NULL WHERE `created_at` IN ('0000-00-00 00:00:00', '0000-00-00');
ALTER TABLE `customers` MODIFY `created_at` TIMESTAMP NULL DEFAULT NULL;

SET SESSION sql_mode = @old_mode;
```

### Other errors

1. Note the exact error (SQL column/table name).
2. Fix L12 code or add a **guarded** migration (`hasColumn` / `hasTable`).
3. Re-test from Step 3 on a **fresh** prod copy (or restore snapshot).
4. Document fix in `doc/schema-decisions.md`.

---

## Step 8 — Production cutover (same as test)

1. Maintenance window or read-only L10 (optional).
2. Final prod mysqldump backup.
3. Same `.env` + deploy as tested on dev clone.
4. Same additive `migrate --path=...` commands.
5. Permission sync.
6. `queue:listen` on L12.
7. Parallel L10 + L12 until L10 retired.

---

## Quick reference — commands only

```bash
# After DB clone + .env configured:
php artisan config:clear
php artisan migrate --path=database/migrations/2026_08_12_100000_align_production_schema.php --force
php artisan migrate --path=database/migrations/2026_08_12_200000_install_l12_production_tables.php --force
php artisan app:backfill-items-qty                    # optional
php artisan db:seed --class=ProductionBootstrapSeeder
php artisan queue:listen &
php artisan serve --host=0.0.0.0 --port=5000
```
