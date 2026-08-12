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

### 3b. Mark greenfield history as already applied (recommended)

Production already has `customers`, `transactions`, `warehouse_item`, etc. Insert rows into the `migrations` table for all **create** migrations that would duplicate existing tables, **or** use the helper script below.

**Safer approach — run only additive paths:**

```bash
# 1. Schema alignment (guarded ALTERs — safe on prod copy)
php artisan migrate --path=database/migrations/2026_08_12_100000_align_production_schema.php --force

# 2. L12-only tables (run only if migrate:status shows pending AND table missing in DB)
# Check in phpMyAdmin first; examples:
php artisan migrate --path=database/migrations/2026_08_08_120000_create_item_identity_conversion_tables.php --force
php artisan migrate --path=database/migrations/2026_08_08_120000_create_warehouse_arrangement_tables.php --force
php artisan migrate --path=database/migrations/2026_08_06_100000_create_restock_sheets_table.php --force
php artisan migrate --path=database/migrations/2026_08_08_052101_create_warehouse_item_monthly_stats_table.php --force
php artisan migrate --path=database/migrations/2026_08_08_120000_create_product_performance_rollups_table.php --force
# ... other create_* migrations for tables NOT in old.sql
```

### 3c. Tables that already exist in production (do NOT re-create)

Skip `CREATE` migrations for: `customers`, `customerstat`, `customer_class`, `warehouse_item`, `item_group`, `items`, `tags`, `item_tag`, `transactions`, `transaction_details`, `users`, `prod_produksi`, `prod_worker`, `prod_borongan`, `prod_borongandetail`, `deleted`, `deleted_details`, `location_customer`, `locations`, `settings`, `jubelio*`, `stat_sells`, `warehouse_compares`, etc.

Reference: `database/old.sql`.

### 3d. Optional backfill

```bash
php artisan app:backfill-items-qty
```

---

## Step 4 — Permissions (not full seed)

**Do not run** `DemoDataSeeder` or `SuperAdminSeeder` on a prod copy.

Sync L12 permission names onto existing roles:

```bash
php artisan tinker --execute="
app(App\Services\PermissionGenerator::class)->generateAll();
\$role = Spatie\Permission\Models\Role::where('name', 'superadmin')->first()
    ?? Spatie\Permission\Models\Role::where('name', 'Super Admin')->first();
if (\$role) {
    \$role->syncPermissions(Spatie\Permission\Models\Permission::all());
    echo 'Synced permissions to: ' . \$role->name;
} else {
    echo 'No superadmin role found — assign permissions manually';
}
"
```

If `settings` rows are missing for L12 keys only:

```bash
php artisan db:seed --class=SettingSeeder
```

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
php artisan app:backfill-items-qty                    # optional
php artisan tinker --execute="app(App\Services\PermissionGenerator::class)->generateAll(); /* sync roles */"
php artisan queue:listen &
php artisan serve --host=0.0.0.0 --port=5000
```
