# Production migration runbook (L10 + L12 shared database)

Aria runs on the **same MySQL database** as the legacy Laravel 10 app until L10 is retired.
L12 uses production table/column names via `PRODUCTION_SCHEMA=true`.

## Prerequisites

- Full database backup
- L12 deployed with `PRODUCTION_SCHEMA=true` in `.env`
- Queue worker available for optional aggregate jobs

## Phase 1 — Schema alignment (one time)

```bash
php artisan migrate
# Runs 2026_08_12_100000_align_production_schema.php
# Adds items.qty, legacy_code, warehouse_item.note, prod_produksi qc/pritil cols, etc.
```

Optional manual backfill if `items.qty` needs a full refresh:

```bash
php artisan app:backfill-items-qty
```

## Phase 2 — Tier A tables (live, no recalc)

These are **already maintained** by L10. L12 reads/writes the same rows.

| L12 model | Production table | Notes |
|-----------|------------------|-------|
| `Addrbook` | `customers` | `member_id` ↔ `memberId` mapped |
| `AddrbookStat` | `customerstat` | PK `customer_id`; ignore `rating` |
| `WarehouseItem` | `warehouse_item` | Stock per warehouse |
| `Transaction` | `transactions` | `invoice_number`↔`invoice`, `due_date`↔`due`, `tax_amount`↔`ppn` |
| `Produksi` | `prod_produksi` | |
| `Worker` | `prod_worker` | |
| `Borongan` | `prod_borongan` | |

**Do not truncate or recalculate** `customerstat` or `warehouse_item` at cutover.

## Phase 3 — Tier B aggregates (batch, 2026 first)

Reports that query `transactions` directly (Nett Cash, Cash Flow) work without these.
Populate caches when needed:

```bash
# 2026 only (add --year= when commands support it)
php artisan app:sync-stat-sells --refresh
php artisan app:recalculate-warehouse-item-stats
php artisan app:recalculate-item-sales
php artisan app:recalculate-nett-cash
php artisan app:recalculate-cash-flow
php artisan app:recalculate-inventory-health
```

Older years can be backfilled later in batches.

## Phase 4 — Parallel operation

- L10 and L12 both write `transactions`, `customerstat`, `warehouse_item`
- Prefer atomic `balance = balance + ?` updates (already how L12 `TransactionService` works)
- Run `php artisan queue:listen` so `UpdateTransactionSummaries` keeps Tier B tables warm for L12-only features

## Phase 5 — Retire L10

When all users are on L12, decommission the L10 app. Keep `PRODUCTION_SCHEMA=true` until a later cleanup renames tables to L12 greenfield names (optional).

## Environment

```env
PRODUCTION_SCHEMA=true
DB_DATABASE=u343060430_aria   # shared production DB
```

For local SQLite / CI tests, leave `PRODUCTION_SCHEMA` unset or `false`.

## Out of scope

- `personnels` / `gaji` / karyawan module (ignored)
- Copying `customer_class` history (L12 can write `customer_class` as `AddrbookDaily` when enabled)
- One-shot `MigrateLegacy*` import commands (not needed when sharing one DB)
