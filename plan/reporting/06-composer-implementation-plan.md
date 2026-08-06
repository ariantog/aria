# 06 — Implementation Plan for Composer

Ordered, self-contained task briefs. Each is one branch and one PR, sized so it can be reviewed on
its own and reverted without unpicking the others.

**How to use this file:** hand Composer one task at a time. Each brief lists the files to touch,
what to build, and how the task is verified. Do not batch tasks — the phases have real dependencies
and the early ones change data the later ones read.

## Conventions that apply to every task

- Branch `cursor/<short-kebab-name>-e924`, one task per branch, based on `main` after the previous
  PR merges.
- Blade + Alpine only. No JS build step, no Tabulator outside the restock page, `gray-*` palette.
- New dated migrations only; never edit an existing migration.
- Query logic goes in `app/Services/Reporting/`, not in controllers, so it is testable without HTTP
  and reusable by exports and console commands.
- No MySQL-only SQL — no `DATE_FORMAT`. The dev DB is SQLite and the suite must run on it.
- Add each new permission to `App\Models\Report::getPermissions()`, then run
  `PermissionGenerator::generateAll()` and re-sync the superadmin role.
- Alpine: methods not getters for `:disabled` / `:class`; bare scope-inherited names inside nested
  `x-data`; `@js(...)` not `@json(...)` inside a double-quoted `x-data`.
- Run `./vendor/bin/pest` before committing, and `tests/Feature/BladePagesRenderTest.php` after
  touching any Blade view. Add the new report routes to that smoke test as they are created.

---

# Phase 0 — Foundation

Nothing in Phase 1+ produces correct numbers until these land.

## Task 0.1 — Normalise the transaction amount

**Why:** `CreateCashTransaction`, `CreateTransferTransaction` and `CreateAdjustTransaction` write
only `grand_total` and leave `total` at `0`. Every money report and both monthly summary tables sum
`total`, so all cash, transfer and adjust rows currently contribute zero. Measured evidence is in
`01-current-state-and-gaps.md` §2.

**Files:** `app/Actions/Transactions/CreateCashTransaction.php`,
`CreateTransferTransaction.php`, `CreateAdjustTransaction.php`,
`app/Console/Commands/` (new), `app/Jobs/UpdateTransactionSummaries.php`.

**Build:**
1. Set `total` on all three actions to the absolute pre-tax amount, so it means the same thing it
   means for item transactions. Leave `grand_total` untouched — balances depend on it and must not
   move.
2. Add `php artisan report:backfill-transaction-totals` to set `total = ABS(grand_total)` for
   existing rows where `total = 0 AND grand_total <> 0`, restricted to types 6, 7, 9, 12. Support
   `--dry-run` and print a per-type count.
3. Change `UpdateTransactionSummaries` to use `grand_total` where it means a money movement, and
   fix the `daily_inventory_summaries` writes: it increments `qty_buy`, `qty_move_in`,
   `qty_move_out`, `qty_return_in`, `qty_return_out`, `qty_adjust_in` and `qty_adjust_out`, all of
   which migration `2026_04_13_085605` dropped, so every completed Buy, Move, Return,
   ReturnSupplier and Adjust currently queues a job that throws. Either restore the columns or drop
   the increments — do not leave a job writing to columns that do not exist. Check `failed_jobs`
   first; it will show how long this has been happening.

**Verify:** a feature test posting one cash-in, one cash-out, one transfer and one adjust, then
asserting `total <> 0` on each and that `addrbook_stats.balance` is unchanged from current
behaviour. A test that the backfill command is idempotent. Confirm the existing Laporan Biaya and
Cash Flow pages show non-zero figures afterwards.

**Do not** change any balance, any `grand_total`, or the sign convention.

---

## Task 0.2 — Account classification

**Why:** no statement can be assembled without knowing whether an account is an asset, a liability,
revenue or an expense. Design in `03` §2.

**Files:** two migrations per `05` §3, `config/accounting.php` (new),
`app/Services/Reporting/AccountClassifier.php` (new), `app/Models/Operation.php`,
`app/Models/Addrbook.php`, `resources/views/journals/operations/` (form fields).

**Build:**
1. Migrations `add_classification_to_operations_table` and
   `add_account_overrides_to_addrbooks_table` — columns in `05` §3.
2. `config/accounting.php` with the type-level defaults from `03` §2.3, the statement-line
   definitions (key, label, section, sort order), and the sign rules.
3. `AccountClassifier` with `classify(Addrbook $a): AccountClassification` resolving
   addrbook override → operation → config type default → `suspense`, and
   `signedAmount(Addrbook $a, float $balance): float` applying `03` §2.4 — including the `is_cash`
   special case and the split of each population by sign, so an advance from a customer is reported
   as a liability rather than netted against receivables.
4. Add the classification fields to the operations create/edit form.

**Verify:** unit tests for the resolution chain (override wins over operation wins over default),
for the sign rules across all addrbook types, and for a customer with a positive balance
classifying as a liability rather than a negative receivable.

---

## Task 0.3 — Accounting periods

**Why:** reports must be reproducible; a neraca printed for March must not change in June.
Design in `03` §8.

**Files:** migrations per `05` §4.1 and §4.2, `app/Models/AccountingPeriod.php`,
`app/Models/PeriodBalance.php`, `app/Services/Reporting/PeriodService.php`, a console command.

**Build:**
1. `accounting_periods` and `period_balances` tables.
2. `PeriodService` with `close(year, month)` snapshotting every addrbook's opening balance,
   period movement (debit and credit separately) and closing balance into `period_balances`, with
   the classification snapshotted alongside — reclassifying later must not restate a closed period.
3. `php artisan report:close-period {year} {month}` and `report:rebuild-period {year} {month}`,
   the latter recomputing an already-closed period and logging that it did.
4. Reports read the snapshot for closed periods and compute live for open ones.

**Verify:** closing a period then posting nothing and re-reading gives identical figures; a
`rebuild` after a manual data change produces different figures and logs the rebuild; opening
balance of month N equals closing balance of month N−1.

---

# Phase 1 — Tax reporting

Delivers standalone value and does not depend on inventory valuation.

## Task 1.1 — Tax identity and rates

**Files:** migrations per `05` §1 and §2, `database/seeders/SettingSeeder.php`,
`app/Models/Addrbook.php`, `app/Models/TaxRate.php` (new),
`resources/views/addrbook/partials/form.blade.php`, `resources/views/system-settings/`,
`app/Http/Requests/StoreAddrbookRequest.php`, `UpdateAddrbookRequest.php`.

**Build:**
1. Tax fields on `addrbooks`; data-migrate `tax_treatment` from the existing `ppn` boolean
   (`true → exclusive`, `false → none`). Keep `ppn` — it is read by `CalculatesTransactionTotals`,
   the lookup API and the Alpine transaction form.
2. `tax_rates` table, seeded per `05` §2.1.
3. Company profile settings, and a section in the system-settings UI to edit them.
4. Surface the new addrbook tax fields in the form and validate them. NPWP: strip formatting, expect
   16 digits.

**Verify:** existing PPN behaviour is unchanged — the `Buy`/`Sell` PPN tests must still pass
untouched. A test that migrating an addrbook with `ppn = true` yields `tax_treatment = exclusive`.

---

## Task 1.2 — Persist tax components per transaction

**Files:** `app/Actions/Transactions/Concerns/CalculatesTransactionTotals.php`,
`CreateItemTransaction.php`, migration per `05` §2.2,
`app/Services/Reporting/TaxCalculator.php` (new).

**Build:**
1. `TaxCalculator` resolving the applicable `tax_rates` row **by transaction date**, and returning
   `taxable_base`, `dpp`, `dpp_basis`, `ppn_rate`, `ppn_amount` per `02` §2.
2. Persist those alongside the existing `tax_amount`, and set `tax_period` to `date` formatted
   `YYYY-MM`.
3. Handle `tax_treatment = inclusive`: gross down rather than add on top —
   `ppn = price × 11/111`, `dpp = (price − ppn) × dpp_factor`.
4. Reverse tax on returns: `Return` and `ReturnSupplier` currently always write `tax_amount = 0`, so
   a return never reverses the PPN the sale charged. Compute the tax and record
   `nota_retur_ref_id` pointing at the original transaction where it can be determined.

**Critical:** `ppn_amount` must remain numerically identical to today's `tax_amount` for the
`exclusive` case. This task makes components explicit; it does not change what anyone is charged.

**Verify:** a test asserting `tax_amount` is byte-identical before and after for an `exclusive`
sale; tests for `inclusive` gross-down arithmetic; a test that a return produces negative tax
referencing the original.

---

## Task 1.3 — PPN reports

**Files:** `app/Http/Controllers/Reports/` (four new controllers),
`app/Services/Reporting/PpnReportService.php`, `resources/views/reports/` (four views),
`routes/web.php`, `App\Models\Report`, `resources/views/partials/sidebar-nav.blade.php`,
`tests/Feature/BladePagesRenderTest.php`.

**Build** the four reports specified in `02` §6.1–6.4:

1. **Rekap Pajak Keluaran** — taxable `Sell` plus negative `Return` rows, columns per `02` §6.1,
   subtotalled per customer, filtered by masa pajak.
2. **Rekap Pajak Masukan** — `Buy` from PKP suppliers plus negative `ReturnSupplier`, with a
   separate non-creditable section for non-PKP suppliers.
3. **Ringkasan SPT Masa PPN** — PK − PM per masa with a 12-month strip and a carried-forward lebih
   bayar column.
4. **Laporan Penjualan Non-PPN** — sales to `tax_treatment = none` customers, with both exposure
   columns (`× 11/111` if the prices were meant to be tax-inclusive, `× 11%` if tax should have
   been added). **Build this one first** — it sizes the problem described in `02` §3 and is the
   report the business most immediately needs.

Permissions: `report-ppn-keluaran`, `report-ppn-masukan`, `report-spt-ppn`,
`report-penjualan-non-ppn`. Group them under an "Akuntansi & Pajak" sidebar heading — the existing
Reports section is already crowded.

**Verify:** feature tests seeding a known month and asserting exact totals; a test that PK − PM
equals the induk figure; smoke-test entries for all four routes.

---

## Task 1.4 — XLSX export

**Files:** `app/Services/Reporting/ReportExportService.php`, the Phase 1 controllers.

**Build:** a shared exporter using PhpSpreadsheet directly, following
`app/Services/Restock/RestockSheetExportService.php` — that is the established pattern and
`maatwebsite/excel` is installed but unused. One `?export=xlsx` parameter per report, reusing the
same service method that feeds the view so the export can never drift from the screen.

**Verify:** a test asserting the response content type and that the sheet's row count matches the
report's.

---

# Phase 2 — Inventory valuation

Design in `04`. Tasks 2.1–2.3 are additive and safe; 2.4–2.5 change posting behaviour.

## Task 2.1 — Standard cost master data

**Files:** migrations per `05` §5, `app/Models/Item.php`, `ItemGroup.php`,
`resources/views/items/` forms, `app/Http/Controllers/ItemsController.php`,
`app/Services/Reporting/StandardCostResolver.php`, a backfill command.

**Build:** the three cost components plus effective date on `items` and `item_groups`;
`StandardCostResolver::unitCost(Item, ?Carbon $at)` falling back item → group → settings default;
form fields and validation; `php artisan items:backfill-labour-cost` populating
`standard_labour_cost` from `tags.price` on each item's TYPE_JAHIT tag, the same lookup
`BoronganController::findBorongan()` performs.

**Verify:** unit tests for the fallback chain and for effective-date selection.

---

## Task 2.2 — Persediaan computation

**Files:** migration per `05` §4.3, `app/Models/PeriodInventory.php`,
`app/Services/Reporting/InventoryValuationService.php`, settings per `04` §10.

**Build** the anchor + movement + opname model from `04` §5:
- `bahan_baku`: opening anchor, plus purchases from the nominated ledger accounts, minus
  consumption computed as `Σ (qty produced × standard_material_cost)`, with the
  `material_to_labour_ratio` fallback for items lacking a standard cost.
- `barang_jadi`: `Σ warehouse_items.quantity × unit_cost`.
- `wip`: `Σ produksis` with `status ∈ {1, 2}` at material plus labour × completion factor
  (`04` §9).
- Opname override with the difference recorded as `selisih`.

**Verify:** tests for the rolling identity (`awal(n) = akhir(n−1)`), for the opname override, and
for the fallback estimator when standard costs are absent.

---

## Task 2.3 — Persediaan report

**Files:** a controller, a view, a route, a permission.

**Build:** all three buckets per period, computed versus opname, with the selisih and an opname
entry form. Read-only over the service from 2.2 — see the numbers before anything depends on them.

---

## Task 2.4 — Cost the production posting

**Files:** `app/Actions/Produksi/SendToWarehouse.php`.

**Build** the three changes in `04` §8: post the detail at standard cost instead of `price = 0`;
set `status = Completed` so `TransactionObserver` dispatches the summary job; route through
`TransactionService::handleTransaction()` instead of calling `InventoryService::add()` directly.

**Care required:** `updateBalances()` has no `Production` branch, so no balance moves — that is
correct, a production receipt is not a receivable. `updateGlobalStock()` also ignores `Production`,
so `items.qty` will not move; decide deliberately whether that is intended and cover it with a test
either way.

**Verify:** the existing `ProduksiPotongTest`, `ProduksiJahitTest` and `ProduksiQcTest` stay green;
new tests for stock landing in the right warehouse, for the transaction carrying a non-zero value,
and for no balance movement.

---

## Task 2.5 — Post labour to the ledger

**Files:** migrations per `05` §6, `app/Http/Controllers/BoronganController.php`,
`GajiController.php`, `app/Actions/Transactions/CreateCashTransaction.php`,
new payroll settings.

**Build** Option A from `04` §6: on marking a borongan or gaji paid, create a `CashOut` from the
paying bank to the configured expense account and store `transaction_id` and `paid_at`. Unpaid
records surface as Hutang Gaji & Borongan in the neraca.

**Verify:** a test that paying a borongan creates exactly one CashOut of the right amount to the
right account, moves the bank balance, and is idempotent if the pay action is repeated.

---

# Phase 3 — Financial statements

Design in `03`.

## Task 3.1 — Neraca Saldo

**Build first.** Every account with opening balance, period movement, closing balance and
classification; filterable by period; an "unclassified only" toggle. It is the drill-down target for
both statements and the only place unclassified accounts become visible.

## Task 3.2 — Laba Rugi

Layout in `03` §4. Monthly, YTD and prior-year comparative. Use `grand_total − tax_amount` for
revenue and `grand_total` for the receivable — the split in `03` §4 is the whole point. HPP from
`InventoryValuationService`, showing both the transactional HPP and the period-identity HPP with
their difference, per `04` §7.

## Task 3.3 — Neraca

Layout in `03` §3, comparative across two periods, with the explicit
`Selisih Belum Teridentifikasi` line in both rupiah and as a percentage of total assets. Do **not**
plug equity to force a balance — the residual is the most informative number the report produces.
Every line drills through to Task 3.1.

## Task 3.4 — Arus Kas

Direct method per `03` §6. **Depends on Task 0.1** — every input is a cash transaction. Assert in a
test that closing cash equals the sum of bank balances on the neraca.

---

# Phase 4 — Managerial and PPh

Individually small, order driven by need.

| Task | Summary |
|---|---|
| 4.1 | **Aging piutang & hutang** — 0–30 / 31–60 / 61–90 / >90 buckets. `transactions.due_date` already exists, so this is cheap and immediately useful |
| 4.2 | **HPP & gross margin** per item, item group and customer. Arguably the highest commercial value in the whole set — the business currently has no way to see this |
| 4.3 | **Kartu persediaan** — per-item stock card, quantity and value movement |
| 4.4 | **Laporan produksi & biaya tenaga kerja** — borongan per period and per penjahit, cost per unit |
| 4.5 | **PPh 21 gaji + borongan** — TER Harian per `02` §7; needs Decision 3 answered first |
| 4.6 | **Fixed assets & depreciation** — `create_fixed_assets_table` per `05` §7, commercial and fiscal schedules, wired to `TransactionType::Depreciation (18)` |
| 4.7 | **Equalisasi PPN vs peredaran usaha** — `02` §6.7. Cheap once 1.3 and 3.2 exist, and the best audit-defence document the system can produce |
| 4.8 | **Koreksi fiskal** — `fiscal_correction` column already added in Task 0.2; surface it as a column on the laba rugi |

---

# Cleanups worth folding into adjacent tasks

Small, unrelated to each other, each cheap when already in the file:

| Fix | Fold into |
|---|---|
| `ReportController@stockIntelligence` authorises `report-inventory-health` instead of `report-stock-intelligence` | any Phase 1 task |
| `reports.inventory-health` has a route but no sidebar link | Task 1.3 |
| `updateStockSettings` / `resetStockSettings` flash success without persisting | any report task |
| `AddrbookController` selects non-existent `code` and `alias` columns | Task 1.1 |
| `ItemsController@itemStats` / `groupStats` use MySQL `DATE_FORMAT` and error on SQLite | Task 4.2 |
| `monthly_item_sales` is maintained by a command and read by nothing — drop it or wire it | Task 0.1 |
| `TransactionService::getDailyReportColumn()` maps `CashIn → 'sell'`, `CashOut → 'buy'`, conflating cash with trade in `addrbook_dailies` | Task 0.1, at least documented |

---

# Sequencing summary

```
Phase 0  0.1 amount ─┬─ 0.2 classification ── 0.3 periods
                     │
Phase 1              ├─ 1.1 tax identity ── 1.2 tax components ── 1.3 PPN reports ── 1.4 export
                     │
Phase 2              ├─ 2.1 standard cost ── 2.2 persediaan ── 2.3 report ── 2.4 costed production
                     │                                                    └─ 2.5 labour posting
Phase 3              └─ 3.1 neraca saldo ── 3.2 laba rugi ── 3.3 neraca
                                                          └─ 3.4 arus kas (needs 0.1)
Phase 4  independent, after 3.2
```

Phase 1 can ship before Phase 2 starts. Phase 3 needs Phase 2 for the Persediaan and HPP lines, but
Tasks 3.1 and 3.4 can land earlier — they need only Phase 0.
