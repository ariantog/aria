# Production migration runbook (L10 + L12 shared database)

Aria runs on the **same MySQL database** as the legacy Laravel 10 app until L10 is retired.
L12 code uses production table and column names directly (`customers`, `warehouse_item`, `invoice`, `due`, `ppn`, `real_total`, etc.).

**See also:** `doc/schema-decisions.md` (column usage) · `doc/production-migration-test.md` (clone prod DB → test migrate on dev)

## Prerequisites

- Full database backup
- L12 deployed against the shared production MySQL database
- Queue worker available for optional aggregate jobs

## Phase 1 — Schema alignment (one time)

```bash
php artisan migrate --path=database/migrations/2026_08_12_100000_align_production_schema.php --force
# On existing production DB — do NOT run full migrate; see production-migration-test.md
```

Optional manual backfill if `items.qty` needs a full refresh:

```bash
php artisan app:backfill-items-qty
```

## Phase 2 — Tier A tables (live, no recalc)

These are **already maintained** by L10. L12 reads/writes the same rows.

| L12 model | Production table | Notes |
|-----------|------------------|-------|
| `Addrbook` | `customers` | `memberId` column |
| `AddrbookStat` | `customerstat` | PK `customer_id`; ignore `rating` |
| `WarehouseItem` | `warehouse_item` | Stock per warehouse |
| `Transaction` | `transactions` | `invoice`, `due`, `ppn`, `discount` (%), `real_total` |
| `Produksi` | `prod_produksi` | |
| `Worker` | `prod_worker` | |
| `Borongan` | `prod_borongan` | |

**Do not truncate or recalculate** `customerstat` or `warehouse_item` at cutover.

## Phase 3 — Tier B aggregates (batch, 2026 first)

Reports that query `transactions` directly work without these. Populate caches when needed:

```bash
php artisan app:sync-stat-sells --refresh
php artisan app:recalculate-warehouse-item-stats
php artisan app:recalculate-item-sales
php artisan app:recalculate-inventory-health
```

## Phase 4 — Parallel operation

- L10 and L12 both write `transactions`, `customerstat`, `warehouse_item`
- Run `php artisan queue:listen` for `UpdateTransactionSummaries`

## Phase 5 — Retire L10

When all users are on L12, decommission the L10 app.

## Out of scope

- `personnels` / `gaji` / karyawan module
- One-shot `MigrateLegacy*` import commands (not needed when sharing one DB)
