# Production schema diff — `old.sql` vs L12 target

Reference files in `database/`:

| File | Meaning |
|------|---------|
| `old.sql` | **Production L10** schema before migration (source of truth for what must never be dropped) |
| `new.sql` | **Migrated production** export (2026-08-21, `u343060430_coreId`) — old.sql plus the L12 bootstrap: 98 legacy tables intact + 25 new L12 tables. Uses production table names (`customers`, `warehouse_item`, `item_group`, …). |

The earlier greenfield L12 dev export (`notused.sql`, with `addrbooks`/`warehouse_items`
naming) has been deleted — its table names never applied to production.

Audit of old.sql vs new.sql (verified 2026-08-21 on a MariaDB clone): no production table
dropped; existing tables only gained columns (plus the `werehouse_id` → `warehouse_id`
typo-fix rename); every key referencing a legacy table is INT, not BIGINT; every NOT NULL
column on legacy tables carries a DB default (after the corrective migration below).

## Production migration command (one step)

```bash
php artisan migrate --path=database/migrations/2026_08_13_100000_production_database_bootstrap.php --force
php artisan config:clear
php artisan db:seed --class=ProductionBootstrapSeeder --force
```

Databases migrated before 2026-08-21 also need the corrective migration once — it re-applies
the NOT NULL defaults and INT key fixes that intermediate code versions missed, and converts
`standalone_invoices.sender_addrbook_id` / `user_id` from BIGINT to INT UNSIGNED
(no-op on freshly bootstrapped databases):

```bash
php artisan migrate --path=database/migrations/2026_08_21_100000_reapply_production_defaults_and_int_keys.php --force
```

OS cron (unchanged):

```cron
* * * * * cd /path/to/aria && php artisan schedule:run >> /dev/null 2>&1
```

---

## Tables only in L12 (created by install migration)

These do **not** exist in `old.sql`:

| Table | Purpose |
|-------|---------|
| `scheduled_tasks` | Cron manager (`/cron-manager`) |
| `jobs`, `job_batches`, `cache`, `cache_locks` | Queue + cache (`failed_jobs` already in prod) |
| `monthly_account_summaries`, `monthly_category_summaries`, `daily_inventory_summaries` | Report aggregates |
| `monthly_item_sales` | Item sales rollup |
| `stok_reports`, `stock_data` | Stock intelligence reports |
| `warehouse_item_monthly_stats`, `product_performance_rollups` | Arrangement / performance |
| `warehouse_arrangement_*` (4 tables) | Warehouse arrangement cache |
| `item_identity_conversion_*` (2 tables) | Legacy item converter |
| `restock_sheets`, `restock_cells`, `restock_cell_histories` | L12 restock UI |

Prod keeps legacy `restocks` / `restock_histories` — L12 restock uses the new tables above.

---

## Existing prod tables — column adds (align migration)

| Table | L12 adds / fixes |
|-------|------------------|
| `customers` | `operation_id`, `arrangement_enabled`, `contact_person` |
| `items` | `qty`, `legacy_code`, `url`, `image_path`, `restock_urgent_threshold` |
| `warehouse_item` | `warehouse_type`, `note`, `created_at`, `updated_at` |
| `prod_produksi` | `user_id`, `qc_*`, `pritil_*`, `original_id`, `transaction_id` |
| `transactions` | `notes`, `reference_number` (uses prod cols `invoice`, `due`, `ppn`, `real_total`) |
| `sessions` | `user_id`, `ip_address`, `user_agent` |
| `settings` | `id`, `slug`, `group`; backfill `slug` from `name`; widen `name`/`value` |
| `users` | `name`, `email`, Fortify 2FA columns; backfill `name` from `username` |
| `transaction_details` | — (hard delete; archive in `deleted_details`) |
| `operations` | `created_at`, `updated_at`, `deleted_at` |
| `tags` | `created_at`, `updated_at` |
| `warehouse_compares` | rename `werehouse_id` → `warehouse_id` |
| `karyawans`, `cutis` | `deleted_at` |
| `prod_produksi` | `user_id`, `qc_*`, `pritil_*`, `original_id`, `transaction_id` — all `INT(11)`, not BIGINT |
| `customers` | `operation_id` — `INT(11)` FK to `operations.id` |

Production bootstrap also runs `2026_08_13_120000_add_production_not_null_column_defaults` — adds `DEFAULT` on every MySQL `NOT NULL` column that lacks one (except `users` and primary keys). See `doc/production-not-null-audit.md` for the full table list.

`2026_08_13_130000_fix_production_bigint_columns_to_int` corrects any BIGINT columns from an earlier align/install run (`operation_id`, `prod_produksi.*_id`, `product_performance_rollups.warehouse_id`).

L10 columns that L12 **ignores** but leaves in place: `customers.phone2`, `category`, `customerstat.rating`, etc. See `doc/schema-decisions.md`.

---

## Spatie permissions (not in new.sql greenfield export)

Production uses `aria_permissions` / `aria_roles` (not `permissions` / `roles`). Auto-detected when `DB_CONNECTION=mysql` or when `permissions` table is missing.

---

## FK type note

Production PKs are signed `INT(11)`. Install migration uses `integer()` FKs to `customers`, `items`, `users`, etc. — not Laravel's default `foreignId()` (`BIGINT UNSIGNED`).

---

## new.sql vs old.sql (logical mapping)

Greenfield `new.sql` table → production equivalent:

| new.sql | Production |
|---------|------------|
| `addrbooks` | `customers` |
| `addrbook_stats` | `customerstat` |
| `addrbook_dailies` | `customer_class` |
| `warehouse_items` | `warehouse_item` |
| `item_groups` | `item_group` |
| `produksis` | `prod_produksi` |
| `workers` | `prod_worker` |
| `borongans` | `prod_borongan` |
| `borongan_details` | `prod_borongandetail` |
| `deleted_transactions` | `deleted` |
| `deleted_transaction_details` | `deleted_details` |
| `permissions` | `aria_permissions` |

`new.sql` is useful for **column intent** on greenfield tables, not for production table names.
