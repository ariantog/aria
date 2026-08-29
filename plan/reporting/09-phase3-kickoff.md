# Phase 3 kickoff — Laba Rugi + Piutang / Hutang Aging

Phase 2 (persediaan + neraca) is **merged** (`#476`). Paste the block below into a **new** chat on a **new** branch off latest `main`. One PR. Do not stack on this docs-only branch.

---

```
Goal: Phase 3 reporting — Laporan Laba Rugi (simple P&L) and piutang/hutang aging, per reporting entity + konsolidasi
Where: new services under app/Services/Reporting/; pages /reports/laba-rugi, /reports/receivables, /reports/payables
Reuse: InventoryRollForwardService, BalanceAsOfService, ReportingPeriod, NeracaService contact filters, reporting_* summaries, monthly_tax_summaries, payment_due_day
Acceptance: 1/3/6/12-month P&L with drill-down + CSV; aging buckets 0-30/31-60/61-90/90+ as-of month-end; internal lending excluded from revenue and usaha totals; Pest + BladePagesRenderTest
Notes: signed totals unchanged; no React/Vite; no table drops; Blade + Alpine CDN; request()->query(); Alpine methods not getters; HPP from Phase 2 inventory roll-forward (not borongan)
```

---

## Numbering

| Name here | What it is | Status |
|---|---|---|
| Phase 0 + 1a–1d | Schema, aggregates, PPN UI, faktur + cash PPN/PPh | **Done** on `main` |
| Phase 2 | Persediaan roll-forward + `/reports/neraca` (`#476`) | **Done** on `main` |
| **Phase 3 (this)** | **Laba rugi + aging** | Next |

Official v2 (`04-revised-architecture-v2.md`) called this Phase 4. If you wanted another neraca pass, stop — that shipped.

## Prerequisite (already on main)

Read these before coding:

- `app/Services/Reporting/{NeracaService,InventoryRollForwardService,BalanceAsOfService,ReportingPeriod,ReportingSummaryRecorder,TaxReportService}.php`
- `app/Models/ReportingMonthlyInventoryValue.php` (`cogs`, `production_cost`, `material_purchases`, `material_cash_out`)
- `resources/views/reports/neraca.blade.php` — copy filter / card / drill-down / `data-testid` style
- `resources/views/reports/tax/ppn.blade.php` — CSV + period/entity pattern
- `plan/reporting/06-answered-decisions.md`

HPP for 2026+ = `InventoryRollForwardService::forMonth()` / `ensureThrough()` **cogs** (sell × cost already in the roll-forward). **Do not** use `borongans`. Gaji Mingguan + material CashOut are already in the inventory formula — do **not** also subtract them as opex.

Production deploy for Phase 2 (maintainer, not this PR):

```bash
php artisan migrate --path=database/migrations/2026_08_29_100000_install_reporting_neraca_tables.php --force
php artisan db:seed --class=SettingSeeder
php artisan reporting:rebuild-inventory
php artisan reporting:snapshot-balances --from=2026-01
```

## What to build

### A. Laporan Laba Rugi — `/reports/laba-rugi`

```
Pendapatan usaha              entity-bank CashIn (customers/resellers)
  − internal lending          customers.is_internal_lending
HPP / COGS                    sum of reporting_monthly_inventory_values.cogs in period
                              (2026+ only; before Jan 2026 show "—" or 0 + footnote)
= Laba kotor
Beban operasi by report_slug  CashOut → operations.report_slug
  exclude already-in-HPP:     production_cost (Gaji Mingguan 2696), material (1558 / role material)
PPh Final                     monthly_tax_summaries.pph_final
= Laba (rugi) bersih
```

**Filters:** entity (Konsolidasi = `0`, same as `NeracaService::CONSOLIDATED_ENTITY`), year, month, **period length 1 / 3 / 6 / 12** months ending at selected month. Cutover `config('reporting.cutover_date')` = `2025-01-01`. Use `ReportingPeriod` for ranges.

**Revenue source:** `reporting_entity_monthly_summaries.cash_in` is **not** safe alone — `ReportingSummaryRecorder::recordEntityCashIn()` does not skip internal lending. Subtract those CashIn at report time (or add `cash_in_internal` in a guarded ALTER + rebuild; prefer subtract-at-report unless the column is cheap).

**Opex / entity gap:** `reporting_operation_monthly_summaries` is global (`year, month, report_slug`) — no entity. For per-entity P&L:

- **Preferred:** guarded ALTER add nullable `integer` `reporting_entity_id` (not BIGINT). Resolve CashOut **sender bank** via `ReportingEntity::findActiveForBank`. Unique `(year, month, report_slug, reporting_entity_id)` with a **short** index name. Unassigned bank → null bucket “Unassigned”. Extend `recordOperationCashOut` + `reporting:rebuild-summaries`. Add the ALTER to production bootstrap `up()` **and** a standalone `install_*` migration.
- **Fallback:** entity P&L = revenue + HPP + PPh only; opex konsolidasi-only with a banner.

**Drill-down:** click a line → transactions or per-month breakdown. **CSV** on same GET (`export=csv`).

**Do not** rewrite `/reports/expense`.

### B. Umur piutang — `/reports/receivables`

Reuse `BalanceAsOfService::balancesAsOf($asOf)` (snapshot or replay — **never** current `customerstat` as the source of truth). Same contact rules as neraca:

- Piutang = customer/reseller and `balance < 0` (they owe us)
- Exclude `is_internal_lending` from usaha total; list them in a separate “Internal” section
- Skip `is_active_in_reports === false`

Neraca’s `receivableRows` / `includeContact` are private — extract a small shared helper or call `BalanceAsOfService` and filter the same way. Do not duplicate a third sign convention.

**As-of:** selected month-end via `ReportingPeriod::asOf($year, $month)` (current month = today), same as neraca.

**Aging buckets:** 0–30 / 31–60 / 61–90 / 90+. **v1 age** = days from oldest completed Sell (or linked keluaran faktur date) on or before as-of that still sits in the open balance. No full open-item subledger.

**Due / overdue:** `payment_due_day` = day of month they usually pay, **month after** last Sell/faktur; `payment_grace_days` default 7 (already on addrbook). Overdue flag after expected date + grace.

Columns: name, NPWP, signed-display `abs(balance)`, due date, buckets, overdue, entity (when konsolidasi). Link name → addrbook.

### C. Umur hutang — `/reports/payables`

Mirror of B: suppliers with `balance > 0`. Tag `reporting_role = material` but keep them in the same table.

### D. Sidebar + permissions

`app/Models/Report.php`:

- `view-laba-rugi` → `report-laba-rugi`
- `view-receivables` → `report-receivables`
- `view-payables` → `report-payables`

Sidebar Reports → Finance, next to Neraca: **Laba Rugi**, **Piutang**, **Hutang**. After merge, maintainer runs `PermissionGenerator::generateAll()`. Superadmin (id 1) already bypasses ACL.

## Locked rules

- Do **not** edit `Transaction::signedAmount()`, `typeIsNegative()`, or `tests/Unit/TransactionSignedAmountTest.php` / `TransactionBalanceIntegrityTest.php`.
- Display may `abs()`; do not persist unsigned totals.
- Gaji Mingguan = production / HPP, not opex. Material Buy + material CashOut = inventory, not opex.
- Internal lending is not consolidated revenue (`F2`).
- Query params: `request()->query('entity')` etc.
- Alpine: methods for `:disabled`; no `$root.*`; `@js(...)` inside double-quoted `x-data`.
- Palette `gray-*`. Add `data-testid` on page, filters, tables (`laba-rugi-page`, `aging-table`, …).

## Schema / prod safety

Allowed: new L12 tables (`Schema::hasTable()`); guarded ADD COLUMN on reporting aggregates; `integer()` FKs to `customers`.

Forbidden: drop/recreate anything in `database/old.sql`; `foreignId()` to legacy PKs; FK to partitioned `transactions`; index names > 64 chars; `database/schema/*.sql`; `migrate:fresh` on MySQL.

NOT NULL new columns need `->default(...)`. Standalone `install_*` + list in `2026_08_13_100000_production_database_bootstrap`.

## Suggested files

```
app/Services/Reporting/LabaRugiService.php
app/Services/Reporting/ReceivablesService.php   # piutang + hutang
app/Http/Controllers/Reports/LabaRugiReportController.php
app/Http/Controllers/Reports/ReceivablesReportController.php
app/Http/Controllers/Reports/PayablesReportController.php
resources/views/reports/laba-rugi.blade.php
resources/views/reports/receivables.blade.php
resources/views/reports/payables.blade.php
routes/web.php
app/Models/Report.php
resources/views/partials/sidebar-nav.blade.php
tests/Feature/LabaRugiReportTest.php
tests/Feature/ReceivablesAgingTest.php
```

Optional: extract `NeracaService` contact filters if aging needs the same lists.

**Out of scope:** neraca changes, persediaan formula changes, Excel/e-Faktur XML, channel-bank UI, rewriting operational reports, open-item AR.

## Tests

`LabaRugiReportTest`

- Customer CashIn to PKP entity bank → pendapatan; internal-lending CashIn excluded from konsolidasi
- CashOut to marketplace/toko ledger → beban; CashOut to Gaji Mingguan / Material Produksi **not** in opex
- HPP equals inventory `cogs` for 2026-01 when Phase 2 roll-forward has a known row
- Non-PKP CashIn → PPh Final line
- `months=3` sums three calendar months; 2024 omitted
- CSV + permission forbidden

`ReceivablesAgingTest`

- As-of replay/snapshot: negative customer → piutang; positive supplier → hutang only
- Internal lending excluded from usaha total
- `payment_due_day` + grace → overdue
- Bucket for a Sell dated 45 days before as-of → 31–60
- Does not read a *current* `customerstat` that differs from as-of replay

Add the three routes to `BladePagesRenderTest`.

```
./vendor/bin/pest tests/Feature/LabaRugiReportTest.php tests/Feature/ReceivablesAgingTest.php
./vendor/bin/pest tests/Feature/BladePagesRenderTest.php
```

No demo videos (`AGENTS.md`).

## Commit / PR

- Branch: `cursor/reporting-laba-rugi-aging-f98d` (use the environment suffix if different)
- Base: latest `main` (includes `#476`)
- Commits: `feat(reports): …` / `test(reports): …`
- Draft PR title: `Phase 3: Laba Rugi + piutang/hutang aging`
