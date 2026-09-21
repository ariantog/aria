# New-subdomain install guide

Use this for a **brand-new Aria host** with an **empty database** (another shop / legal entity on its own subdomain).

Do **not** use this on the current Crystal production host (`aria.corenationactive.com`) or on any clone of that database.

**Crystal (current production)** uses individual `php artisan migrate --path=...` on the shared L10 MySQL schema, plus `ProductionBootstrapSeeder` when needed. It does **not** use the flow below.

**New subdomain** uses a full greenfield `php artisan migrate` (all L12 migration files in order), then `NewDomainSeeder`. Do **not** run `database/migrations/2026_08_13_100000_production_database_bootstrap.php` on an empty database — that bundle is for L10 → L12 **clones** only.

Canonical command: `php artisan app:install-new-domain` (wrapper: `scripts/install-new-domain.sh`).

## Before you start

- PHP **8.3** or **8.4** (not 8.5 — PhpSpreadsheet).
- Composer 2.
- An **empty** MySQL database. Do not import `database/old.sql`, a Crystal dump, or any database that already has customers / reporting entities.
- `APP_URL` host is the **new** subdomain, not `aria.corenationactive.com`.
- `APP_TIMEZONE=Asia/Jakarta` (WIB) if you use Shopee Ads schedules or payroll/absensi.
- `QUEUE_CONNECTION=database` so transaction summary jobs persist.
- `ARIA_LEGACY_PRODUCTION` is unset / `false` on the new host.
- On Crystal, keep `ARIA_LEGACY_PRODUCTION=true` so this path can never run there.

MySQL permission tables default to `aria_permissions` / `aria_roles`. Leave `PERMISSION_TABLE_*` unset on a new MySQL host so `migrate` creates those names. Do not point a new empty DB at Crystal's live schema.

Optional later: `ARCHIVE_DB_*` for data retention, `SHOPEE_*` / `TELEGRAM_*` for Shopee Ads, `JUBELIO_*` when omnichannel is ready, `ITEM_IMAGE_URL` / `CDN_URL` / `INVOICE_PATH` for production asset URLs.

## Guard (why the command may refuse)

`App\Support\NewDomainInstall` refuses install and baseline seed when any of these are true:

1. The database looks like Crystal: reporting entity slug `cv-crystal`, or at least 3 of ledger ids `1558, 2696, 2889, 2234, 830`.
2. `ARIA_LEGACY_PRODUCTION=true`.
3. `APP_URL` host is in `ARIA_LEGACY_HOSTS` (default `aria.corenationactive.com`).

The Crystal fingerprint **always wins**. `--force` and `ARIA_NEW_DOMAIN=true` never bypass it.

`ARIA_NEW_DOMAIN=true` only bypasses a **legacy host name** when the database is empty. Use it if `APP_URL` still happens to match a listed legacy host. Turn it off after install.

## Install

```bash
cp .env.example .env
# Set APP_NAME, APP_URL (new host), APP_KEY, MySQL, QUEUE_CONNECTION=database
php artisan key:generate

composer install --no-dev --optimize-autoloader

php artisan app:install-new-domain
# or: bash scripts/install-new-domain.sh
```

That runs `php artisan migrate --force` then `db:seed --class=NewDomainSeeder --force`.

Confirm the prompt (host + database name). `--force` on the install command skips the prompt only; it does not skip the guard.

`migrate` on a new domain also runs `2026_09_01_140000_seed_new_domain_baseline`, which calls the same seeder. A second seed from the install command is idempotent.

After install on the server:

```bash
php artisan config:cache
php artisan route:cache   # optional
```

### Options

| Flag | Effect |
|------|--------|
| `--force` | Skip the confirmation prompt |
| `--skip-migrate` | Seed only (schema already migrated) |
| `--skip-seed` | Migrate only |

Do **not** run `php artisan db:seed` (the default `DatabaseSeeder`) on a live new domain. That also runs `DemoDataSeeder` (fake transactions, no inventory posting). Local preview only.

Do **not** run `ReportingBootstrapSeeder`. That copies Crystal entity / ledger ids.

Do **not** run `migrate:fresh` / `migrate:refresh` except on local SQLite.

## What gets created

### Schema (via full `migrate`)

Greenfield migrations create the full L12 schema: reporting summaries, tax faktur, payroll/absensi, item group catalog (`brand` / `genre`, widened `name`), `items.alias`, reseller price, `items.cost_cnh`, item insight rollup tables, Jubelio/Shopee Ads tables, and addrbook `ppn_included` (default **true** on new contacts).

### Data (`NewDomainSeeder`)

`NewDomainSeeder` calls, in order:

1. `SuperAdminSeeder` — user `superadmin` / `password` (login is **username**, not email). Change this immediately.
2. `ProductionBootstrapSeeder` — Spatie permissions (including newer report permissions such as item insights), cron rows (`ScheduledTaskSeeder`), all `SettingRegistry` keys, stock-intelligence weights, staff checklist catalog.
3. `TypicalLedgerSeeder` — 15 operations and typical ledgers (new auto-increment ids, not Crystal ids). Reporting roles: marketplace / toko / material / production cost / tax / adjustment.
4. `AddrbookPlaceholderSeeder` — one contact per type, plus `customerstat` and the default location. Supplier placeholder has `ppn` enabled.
5. `NewDomainSettingsSeeder` — fills **empty** operational settings only:

   | Setting | Points at |
   |---------|-----------|
   | `restock.default_supplier_id` | Supplier |
   | `restock.default_receiver_id` | Gudang |
   | `restock.default_warehouse_ids` | Gudang |
   | `produksi.default_warehouse_id` | Gudang |
   | `asset_tetap.depreciation_expense_account_id` | Biaya Perawatan |
   | `asset_tetap.depreciation_contra_account_id` | Penyesuaian Umum |

`SettingSeeder` (inside step 2) also seeds registry defaults you should review in System Settings, including:

| Setting | Default | Notes |
|---------|---------|--------|
| `ppn_rate` | 11 | Global rate label; tax still depends on entity/contact |
| `transactions.default_ppn_included` | true | Buy/sell form default when contact has no `ppn_included` |
| `restock.export_cost_field` | `cost` | Restock Excel export uses IDR `cost`; switch to `cost_cnh` if needed |
| `reporting.persediaan_awal` | 0 | Opening inventory for Jan 2026 neraca roll-forward |

Placeholder contacts: Pelanggan, Gudang, Kas / Bank, Supplier, Gudang Virtual, Akun Virtual, Reseller, Akun Umum, Lainnya.

Operations: Biaya Marketplace, Biaya Toko, Marketing Umum, Gaji & Upah, Produksi, Logistik, Kantor & Utilitas, Perawatan & Mesin, Jasa Profesional, Kesejahteraan Karyawan, Pajak & Retribusi, Perbankan, Penyesuaian, Lain-lain, Sewa HQ.

Catalog lives in `App\Support\NewDomainChartOfAccounts`. Re-running the seeder does not overwrite names or settings the operator already changed.

### Cron rows seeded (high level)

`ScheduledTaskSeeder` registers the usual daily/hourly jobs (warehouse item stats reconcile + backfill, reporting summaries/inventory snapshot, inventory health, Jubelio order sync, stock check, Shopee Ads process, and others). Notable:

- **Jubelio:** `jubelio:order-jubelio-to-aria`, `jubelio:poll-missing-orders`, `jubelio:check-connection`, `app:jubelio-stock-check`. Legacy **`jubelio:get-orders` resume cron is not seeded** (removed).
- **Depreciation:** `app:run-monthly-depreciation` is present but **inactive** until accounts are set.
- **Data retention:** `app:process-data-retention-archive` is **inactive** until an archive DB exists.

## After install

1. Log in as `superadmin` / `password` and change the password.
2. Rename placeholder contacts (or add real ones). Set **`ppn`** and **`ppn_included`** per supplier/customer/reseller as needed; item invoices use stored amounts, not a global 11% guess.
3. Create the reporting entity at `/reports/entities`. Map banks, PKP, tax accounts, and ledger roles. Do not import Crystal's `cv-crystal` row. Set **`reporting.persediaan_awal`** if you need a non-zero January 2026 opening inventory.
4. Review System Settings (PPN rate, default PPN included mode, restock export cost field, restock/produksi warehouses, depreciation accounts).
5. Point OS cron at `php artisan schedule:run` every minute. Cron Manager (`/cron-manager`) lists seeded tasks; enable monthly depreciation only after depreciation accounts are correct.
6. Run a queue worker (`php artisan queue:listen` or rely on `app:process-queue` each minute) so `UpdateTransactionSummaries` drains.
7. Leave `JUBELIO_ACTIVE=false` until credentials and `jubeliosyncs` warehouse mapping are ready.
8. **Item Insights** (`/reports/item-insights`): rankings are empty until you have sell history in `warehouse_item_monthly_stats`. Use **Recalculate** on the report (or wait for daily stats reconcile). There is no separate item-insights cron.
9. **Warehouse stats backfill** for old months: System Settings → Warehouse Stats Backfill when you need arrangement / performance history before go-live month.
10. Optional integrations: Shopee Ads (env + OAuth), archive database for data retention, CDN paths for item images and invoice PDFs.

Reporting aggregate tables ignore transactions before `config('reporting.cutover_date')` (default `2025-01-01`).

## If install refuses

| Message | What to do |
|---------|------------|
| Crystal ledger IDs or `cv-crystal` | Wrong database. Use a new empty schema. Never force this. |
| `ARIA_LEGACY_PRODUCTION` is set | Unset it on the **new** host only. Keep it `true` on Crystal. |
| `APP_URL` host is the current production domain | Set `APP_URL` to the new subdomain, or (empty DB only) `ARIA_NEW_DOMAIN=true` for the one-shot install. |

## If `migrate` fails on `warehouse_item` (errno 150)

Older code created `warehouse_item` **before** the `customers` table existed and added MySQL foreign keys production does not use. Use a current build (deferred `2026_02_16_070218_install_greenfield_warehouse_item_table.php`).

On a **failed halfway** database:

1. Drop the partial `warehouse_item` table if it exists (`DROP TABLE warehouse_item;`).
2. If `2026_02_13_073538_create_warehouse_items_table` is marked ran but the table is missing, leave it — the install migration creates the table later.
3. Run `php artisan migrate --force` again (or `php artisan app:install-new-domain` on an empty DB).

Simplest recovery on a new host: empty the database (or create a fresh schema) and re-run install after deploying the fix.

## If `install_l12_production_tables` fails dropping `stok_reports` (errno 1451)

That migration only **drops** BIGINT FK tables when fixing a **production INT(11)** clone. On a **greenfield** database (`customers.id` is still BIGINT from Laravel migrations), it should **skip** those drops and keep the tables created earlier in the same `migrate` run.

If you still see errno 1451 on drop, deploy the current fix and either:

1. Empty the database and run `php artisan migrate --force` again, or  
2. Drop child tables first (`stock_data`, then `stok_reports`), then re-run migrate.

## Current production (Crystal)

```bash
# One-shot schema catch-up on an L10 clone (maintainer), when listed in the bootstrap file:
php artisan migrate --path=database/migrations/2026_08_13_100000_production_database_bootstrap.php --force

# Any newer migration not yet in that bundle — individually, never bare migrate:
php artisan migrate --path=database/migrations/YYYY_MM_DD_xxxxxx_....php --force

php artisan db:seed --class=ProductionBootstrapSeeder --force
```

The new-domain baseline migration `2026_09_01_140000_seed_new_domain_baseline` is a documented **no-op** on Crystal (fingerprint / legacy host).
