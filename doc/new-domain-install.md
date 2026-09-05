# New-subdomain install guide

Use this for a **brand-new Aria host** with an **empty database** (another shop / legal entity on its own subdomain).

Do **not** use this on the current Crystal production host (`aria.corenationactive.com`) or on any clone of that database.

Current production stays on individual `php artisan migrate --path=...` plus `ProductionBootstrapSeeder`.

Canonical command: `php artisan app:install-new-domain` (wrapper: `scripts/install-new-domain.sh`).

## Before you start

- PHP **8.3** or **8.4** (not 8.5 — PhpSpreadsheet).
- Composer 2.
- An **empty** MySQL database. Do not import `database/old.sql`, a Crystal dump, or any database that already has customers / reporting entities.
- `APP_URL` host is the **new** subdomain, not `aria.corenationactive.com`.
- `ARIA_LEGACY_PRODUCTION` is unset / `false` on the new host.
- On Crystal, keep `ARIA_LEGACY_PRODUCTION=true` so this path can never run there.

MySQL permission tables default to `aria_permissions` / `aria_roles`. Leave `PERMISSION_TABLE_*` unset on a new MySQL host so `migrate` creates those names. Do not point a new empty DB at Crystal's live schema.

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

Confirm the prompt (host + database name). `--force` skips the prompt only; it does not skip the guard.

`migrate` on a new domain also runs `2026_09_01_140000_seed_new_domain_baseline`, which calls the same seeder. A second seed from the install command is idempotent.

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

`NewDomainSeeder` calls, in order:

1. `SuperAdminSeeder` — user `superadmin` / `password` (login is **username**, not email). Change this immediately.
2. `ProductionBootstrapSeeder` — Spatie permissions, cron rows (`ScheduledTaskSeeder`), `SettingRegistry` keys, stock-intelligence weights, staff checklist catalog.
3. `TypicalLedgerSeeder` — 15 operations and typical ledgers (new auto-increment ids, not Crystal ids). Reporting roles: marketplace / toko / material / production cost / tax / adjustment.
4. `AddrbookPlaceholderSeeder` — one contact per type, plus `customerstat` and the default location.
5. `NewDomainSettingsSeeder` — fills **empty** settings only:

   | Setting | Points at |
   |---------|-----------|
   | `restock.default_supplier_id` | Supplier |
   | `restock.default_receiver_id` | Gudang |
   | `restock.default_warehouse_ids` | Gudang |
   | `produksi.default_warehouse_id` | Gudang |
   | `asset_tetap.depreciation_expense_account_id` | Biaya Perawatan |
   | `asset_tetap.depreciation_contra_account_id` | Penyesuaian Umum |

Placeholder contacts: Pelanggan, Gudang, Kas / Bank, Supplier, Gudang Virtual, Akun Virtual, Reseller, Akun Umum, Lainnya.

Operations: Biaya Marketplace, Biaya Toko, Marketing Umum, Gaji & Upah, Produksi, Logistik, Kantor & Utilitas, Perawatan & Mesin, Jasa Profesional, Kesejahteraan Karyawan, Pajak & Retribusi, Perbankan, Penyesuaian, Lain-lain, Sewa HQ.

Catalog lives in `App\Support\NewDomainChartOfAccounts`. Re-running the seeder does not overwrite names or settings the operator already changed.

## After install

1. Log in as `superadmin` / `password` and change the password.
2. Rename the placeholder contacts (or add real ones and leave the placeholders unused).
3. Create the reporting entity at `/reports/entities`. Map banks, PKP, tax accounts, and ledger roles there. Do not import Crystal's `cv-crystal` row.
4. Review System Settings (PPN rate, restock, produksi warehouse, depreciation accounts).
5. Point OS cron at `php artisan schedule:run` every minute. Cron Manager (`/cron-manager`) already has the usual tasks; leave monthly depreciation **off** until the two depreciation accounts are correct, then enable `app:run-monthly-depreciation`.
6. Run a queue worker (`php artisan queue:listen` or the scheduled `app:process-queue`) so `UpdateTransactionSummaries` drains.
7. Leave `JUBELIO_ACTIVE=false` until credentials and `jubeliosyncs` warehouse mapping are ready.

## If install refuses

| Message | What to do |
|---------|------------|
| Crystal ledger IDs or `cv-crystal` | Wrong database. Use a new empty schema. Never force this. |
| `ARIA_LEGACY_PRODUCTION` is set | Unset it on the **new** host only. Keep it `true` on Crystal. |
| `APP_URL` host is the current production domain | Set `APP_URL` to the new subdomain, or (empty DB only) `ARIA_NEW_DOMAIN=true` for the one-shot install. |

## Current production (Crystal)

```bash
# Per migration, when the maintainer is ready — never a bare migrate:
php artisan migrate --path=database/migrations/YYYY_MM_DD_xxxxxx_....php --force
php artisan db:seed --class=ProductionBootstrapSeeder --force
```

The new-domain baseline migration is a documented no-op on Crystal.
