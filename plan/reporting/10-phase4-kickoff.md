# Phase 4 kickoff — Excel pack, PPh Final, mapping UI

Phase 3 (Laba Rugi + aging) is **merged** (`#481`). Paste the block below into a **new** chat on a **new** branch off latest `main`. One PR. Do not stack on this docs-only branch.

---

```
Goal: Phase 4 reporting — accountant Excel exports, dedicated PPh Final report, and unused reporting mapping UI (unassigned banks, ledger roles, warehouse fulfillment)
Where: export xlsx on existing financial reports; new /reports/tax/pph; extend /reports/entities (and addrbook reporting fields) for roles + fulfillment
Reuse: LabaRugiService, AgingReportService, TaxReportService, NeracaService, ExportSellExportService / WarehouseArrangementExportService (PhpSpreadsheet), ReportingLedgerRole, reporting_warehouse_fulfillment
Acceptance: xlsx download for PPN, neraca, laba rugi, piutang, hutang; PPh Final page with entity + period + CSV/xlsx; entities index lists unassigned operating banks; ledger-role and fulfillment rows can be saved; rebuild-summaries cron for recent months; Pest + BladePagesRenderTest
Notes: signed totals unchanged; no React/Vite; no table drops; Blade + Alpine CDN; request()->query(); Alpine methods not getters; no DJP e-Faktur API/XML in this PR
```

---

## Numbering

| Name here | What it is | Status |
|---|---|---|
| Phase 0 + 1a–1d | Schema, aggregates, PPN UI, faktur + cash PPN/PPh | **Done** |
| Phase 2 | Persediaan + `/reports/neraca` (`#476`) | **Done** |
| Phase 3 | Laba rugi + piutang/hutang aging (`#481`) | **Done** |
| **Phase 4 (this)** | **Excel + PPh Final + mapping UI** | Next |

Official v2 Phase 4 was “laba rugi, aging, exports”. Laba rugi/aging shipped; this kickoff is the **exports + leftover mapping**.

## Prerequisite (already on main)

Read before coding:

- `app/Services/Reporting/{LabaRugiService,AgingReportService,TaxReportService,NeracaService,ReportingSummaryRecorder}.php`
- `app/Services/{ExportSellExportService,WarehouseArrangementExportService}.php` — copy xlsx streamed-response style (`PhpOffice\PhpSpreadsheet`, already required; PHP matrix is 8.3/8.4 only)
- `resources/views/reports/{laba-rugi,neraca,receivables,payables,tax/ppn}.blade.php`
- `app/Models/ReportingLedgerRole.php`, `app/Enums/ReportingLedgerRole.php`
- `database/migrations/2026_08_22_110000_install_reporting_tables.php` — `reporting_warehouse_fulfillment` exists, **no UI**
- `ReportingChannelBank` is **deprecated** — do not revive per-customer default bank. Revenue entity is CashIn receiver bank (`reporting_entity_banks`).
- `plan/reporting/06-answered-decisions.md`

Phase 3 leftovers you may **use**, not redo:

- CSV already works (`export=csv`) on PPN / laba rugi / aging
- Per-entity opex is computed live from CashOut when not konsolidasi; HPP is **konsolidasi only**
- Aging uses FIFO + `BalanceAsOfService` + `payment_due_day`
- Daily cron already exists for `reporting:snapshot-balances` and `reporting:rebuild-inventory`
- **`reporting:rebuild-summaries` has no cron**

## What to build

### A. Excel pack (`export=xlsx`)

Add **Export Excel** next to existing CSV on:

| Page | Route |
|---|---|
| Laporan PPN | `/reports/tax/ppn` |
| Neraca | `/reports/neraca` |
| Laba Rugi | `/reports/laba-rugi` |
| Piutang | `/reports/receivables` |
| Hutang | `/reports/payables` |

Same filters as the HTML page. One sheet per report is enough (ringkasan + detail rows). Match `ExportSellExportService`: `Spreadsheet` + `Xlsx` + `StreamedResponse`, bold header, auto-size, filename with entity + period.

Shared helper is fine (`app/Services/Reporting/ReportingExcelExport.php`) if it stays thin — do not pull in a new frontend stack. `maatwebsite/excel` is already in composer; **prefer raw PhpSpreadsheet** like the existing export services.

Do **not** rewrite operational Excel (Export Sell, warehouse arrangement, restock).

### B. PPh Final — `/reports/tax/pph`

Non-PKP entity CashIn → `monthly_tax_summaries.pph_final` (rate `config('reporting.pph_final_rate')` = 0.5%). Today this is only a ringkasan line on Laporan PPN.

- Filters: year, month, entity (non-PKP + Konsolidasi)
- Ringkasan: gross CashIn, PPh Final, tax_paid
- Drill-down: CashIn rows that fed the rollup (reuse `ReportingSummaryRecorder` / `TaxReportService` entity resolution)
- CSV + xlsx
- Permission `report-tax-pph`; sidebar Reports → Tax next to Laporan PPN
- Cutover 2025-01-01; no 2024

Do not change how PPh Final is recorded. Do not build PPh 21/23/25.

### C. Mapping UI (tables already exist)

**1. Unassigned banks** on `/reports/entities`

List active `type=Bank` contacts with `is_active_in_reports` that are **not** on any active `reporting_entity_banks` row. Amber banner + links to assign. Transfer Pending / Investment stay off the list when `is_active_in_reports` is false.

**2. Ledger roles**

Seeded only for a handful of ids (1558, 2696, WTC/Citos, Shopee/TikTok). Add a small editor (entities page section **or** addrbook reporting fields for `type=Account`):

`material` | `production_cost` | `marketplace_cost` | `toko_cost` | `tax_payment` | `adjustment` | `exclude`

This is what Laba Rugi already subtracts from opex (`ReportingLedgerRole`). Without a UI, HPP vs beban stays tribal knowledge.

**3. Warehouse fulfillment**

`reporting_warehouse_fulfillment` (`warehouse_id`, `customer_id`) — WTC/Citos warehouse → marketplace/customer channel they ship for. Simple attach/detach UI. **Analysis only** — does not change CashOut ledgers or tax entity.

Do **not** rebuild `reporting_channel_banks` as a tax switch.

### D. Cron — recent summary rebuild

`database/seeders/ScheduledTaskSeeder.php`: add `reporting:rebuild-summaries` for **recent months only** (mirror warehouse stats: `--from` current-month-minus-1, not full history from 2025). Full backfill stays a manual artisan command.

Command already supports `--from` / `--to` / `--year`. If a full truncate+replay of 2025+ is too heavy for a daily cron, add a `--months=2` option rather than deleting all rows every night.

## Locked rules

- Do **not** edit `Transaction::signedAmount()` or signed-total tests
- Display may `abs()`; do not persist unsigned totals
- Blade + Alpine + Tailwind CDN only
- `request()->query(...)`; Alpine methods not getters; no `$root.*`; `@js(...)` in double-quoted `x-data`
- Palette `gray-*`; `data-testid` on new filters/export buttons (`pph-export-xlsx`, `entities-unassigned-banks`, …)
- `Gate::authorize(Report::getPermissions()[...])`; after merge, maintainer runs `PermissionGenerator::generateAll()`
- New L12 tables: `Schema::hasTable()`. Legacy FKs: `integer()` not `foreignId()`. No FK to partitioned `transactions`. Index names ≤ 64 chars. No `database/schema/*.sql`. Add standalone `install_*` + bootstrap `up()` if you add tables/columns.

## Suggested files

```
app/Services/Reporting/ReportingExcelExport.php          # or per-service exportXlsx()
app/Services/Reporting/PphFinalReportService.php
app/Http/Controllers/Reports/TaxPphReportController.php
resources/views/reports/tax/pph.blade.php
resources/views/reports/entities/*                      # unassigned banks, roles, fulfillment
database/seeders/ScheduledTaskSeeder.php
app/Console/Commands/RebuildReportingSummariesCommand.php  # --months= if needed
app/Models/Report.php
resources/views/partials/sidebar-nav.blade.php
tests/Feature/ReportingExcelExportTest.php
tests/Feature/TaxPphReportTest.php
tests/Feature/ReportingMappingUiTest.php
```

## Tests

`ReportingExcelExportTest`

- Each of the five pages with `export=xlsx` returns xlsx (`Content-Type` spreadsheetml) and a readable sheet (PhpSpreadsheet `IOFactory`, same as `ExportSellTest`)
- Forbidden without the matching report permission

`TaxPphReportTest`

- Non-PKP CashIn → PPh Final row; PKP entity excluded from that list
- Month filter; 2024 omitted
- CSV + xlsx + `report-tax-pph` forbidden

`ReportingMappingUiTest`

- Unassigned active bank appears on entities index; assigned bank does not
- Saving a ledger role persists `reporting_ledger_roles`
- Attaching warehouse ↔ customer persists `reporting_warehouse_fulfillment`

Add `/reports/tax/pph` to `BladePagesRenderTest`.

```
./vendor/bin/pest tests/Feature/ReportingExcelExportTest.php tests/Feature/TaxPphReportTest.php tests/Feature/ReportingMappingUiTest.php
./vendor/bin/pest tests/Feature/BladePagesRenderTest.php
```

No demo videos (`AGENTS.md`).

## Out of scope

- DJP e-Faktur XML / live API (`tax_invoice_number` on `transactions` can wait)
- Inter-entity eliminations on konsolidasi neraca
- Splitting HPP per entity (Phase 3: konsolidasi only)
- Adding `reporting_entity_id` to operation summaries (live CashOut path already exists)
- Channel P&L / marketplace comparison report (follow-up after fulfillment + roles are filled)
- Rewriting `/reports/expense`, cash-flow, purchase
- Open-item AR beyond Phase 3 FIFO

## Commit / PR

- Branch: `cursor/reporting-excel-pph-mapping-f98d` (use the environment suffix if different)
- Base: latest `main` (includes `#481`)
- Commits: `feat(reports): …` / `test(reports): …`
- Draft PR title: `Phase 4: Excel exports, PPh Final, reporting mapping UI`
