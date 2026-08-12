# Production schema diff — `old.sql` vs L12 target

Reference files in `database/`:

| File | Meaning |
|------|---------|
| `old.sql` | **Production L10** schema (source of truth for what exists today) |
| `new.sql` | Greenfield **L12 dev export** (Aug 2026) — uses legacy greenfield table names (`addrbooks`, `warehouse_items`). **Do not use table names from new.sql on production.** L12 on shared prod uses production names (`customers`, `warehouse_item`, …). |

## Production migration command (one step)

```bash
php artisan migrate --path=database/migrations/2026_08_13_100000_production_database_bootstrap.php --force
php artisan config:clear
php artisan db:seed --class=ProductionBootstrapSeeder --force
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
