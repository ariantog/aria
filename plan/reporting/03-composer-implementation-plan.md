# Composer Implementation Plan

> **For:** Composer agent (or any implementation agent)  
> **Branch:** continue on `cursor/reporting-d945` or child branches per phase  
> **Prerequisite:** User answers open questions in `01-brainstorm.md` §7

---

## Phase 0 — Foundation (do first)

### 0.1 Settings & migrations

- [ ] Migration: `add_npwp_to_addrbooks_table` — `npwp` nullable string
- [ ] Migration: `create_monthly_inventory_values_table`
- [ ] Seeder/settings: add Reporting group slugs from `02-data-model.md`
- [ ] UI: extend `system-settings/edit.blade.php` with Reporting section
  - Company name, NPWP, PKP flag
  - Modal, persediaan awal (+ effective month)
  - Multi-select journal accounts for material purchases
  - Production cost source dropdown
  - Warehouse multi-select for inventory valuation
- [ ] Addrbook form: NPWP field (optional)
- [ ] Tests: settings save, NPWP validation (format optional v1)

### 0.2 Report infrastructure

- [ ] `app/Services/Reports/` namespace:
  - `PeriodFilter` value object (year, month, startDate, endDate)
  - `ReportPeriodService` — builds date range; optional `tutup_buku` support
- [ ] `app/Services/Reports/TaxReportService.php`
- [ ] `app/Services/Reports/NeracaService.php`
- [ ] `app/Services/Reports/InventoryValuationService.php` — extract from `WarehouseItemReportController`
- [ ] `app/Services/Reports/ReceivablesService.php`
- [ ] Base layout: `resources/views/reports/layout.blade.php` — period picker partial
- [ ] Permissions in `app/Models/Report.php` + `PermissionGenerator`
- [ ] Sidebar: new "Laporan Keuangan" submenu under Reports

**Files to create/modify:**

```
app/Services/Reports/PeriodFilter.php
app/Services/Reports/TaxReportService.php
app/Services/Reports/NeracaService.php
app/Services/Reports/InventoryValuationService.php
app/Services/Reports/ReceivablesService.php
app/Http/Controllers/Reports/TaxPpnController.php
app/Http/Controllers/Reports/NeracaController.php
app/Http/Controllers/Reports/ReceivablesController.php
resources/views/reports/tax/ppn.blade.php
resources/views/reports/neraca.blade.php
resources/views/reports/receivables.blade.php
resources/views/reports/partials/period-filter.blade.php
routes/web.php (new routes)
```

---

## Phase 1 — PPN Tax Reports

### 1.1 TaxReportService methods

```php
public function keluaran(PeriodFilter $period): Collection;
public function masukan(PeriodFilter $period): Collection;
public function summary(PeriodFilter $period): array;
// Returns: keluaran_dpp, keluaran_ppn, masukan_dpp, masukan_ppn, net_ppn
```

**Rules:**

- Query `Transaction::query()->active()->whereBetween('date', ...)`
- Keluaran: types `Sell`, `Return`; `tax_amount != 0`
- Masukan: types `Buy`, `ReturnSupplier`; `tax_amount != 0`
- Eager-load sender/receiver addrbook
- Return rows with: date, invoice_number, counterparty name, npwp, dpp, tax_amount, grand_total, type label
- Summary: Return reduces keluaran; ReturnSupplier reduces masukan

### 1.2 UI — `/reports/tax/ppn`

- Period filter (month/year)
- Three sections: Keluaran table, Masukan table, Ringkasan card
- Totals row per table
- Link to transaction show page
- Highlight rows where `tax_amount > 0` but counterparty `ppn = false` (data inconsistency)
- Export CSV button

### 1.3 Tests — `tests/Feature/Reports/TaxPpnReportTest.php`

- Buy with PPN supplier → appears in masukan
- Sell with PPN customer → keluaran
- Sell without PPN → excluded from keluaran, optional "non-PPN sales" list
- Return reduces keluaran totals
- Month filter correctness

---

## Phase 2 — Neraca

### 2.1 InventoryValuationService

Extract valuation SQL from `WarehouseItemReportController`:

```php
public function totalValue(?array $warehouseIds = null): float;
public function breakdownByWarehouse(): Collection;
```

### 2.2 Monthly inventory roll-forward

`InventoryValuationService::monthlyFlow(PeriodFilter $period): array`

```php
[
  'opening' => ...,
  'material_purchases' => ...,  // sum CashOut/Buy from material_account_ids
  'production_cost' => ...,     // borongan overlap
  'cogs' => ...,                // per cogs_method setting
  'adjustment' => ...,
  'closing' => opening + purchases - production_cost - cogs + adjustment,
]
```

- On first run for a month with no `monthly_inventory_values` row: opening = previous month closing OR `reporting.persediaan_awal` if first month
- Persist closing to `monthly_inventory_values`

### 2.3 NeracaService

```php
public function build(?Carbon $asOf = null): array;
```

Sections:

```php
[
  'aktiva_lancar' => [
    'kas' => float,           // sum bank balances
    'piutang' => float,       // abs(sum negative customer/reseller balances)
    'persediaan' => float,    // from InventoryValuationService
    'lainnya' => 0,
  ],
  'aktiva_tetap' => float,    // ASSET_TETAP items net depreciation (v1: sum cost×qty)
  'kewajiban' => [
    'hutang_usaha' => float,  // sum positive supplier balances
    'hutang_ppn' => 0,        // v2
    'lainnya' => 0,
  ],
  'ekuitas' => [
    'modal' => float,         // from settings
    'laba_ditahan' => float,  // plug: total_aktiva - total_kewajiban - modal
  ],
  'balance_check' => float,   // should be ~0 if plug used
]
```

### 2.4 UI — `/reports/neraca`

- As-of date picker (default: today)
- Two-column neraca layout (Indonesian labels)
- Expandable drill-down per line (click Kas → list banks)
- Warning banner if `balance_check` != 0
- Show persediaan breakdown (opening → closing) in footnote

### 2.5 Tests — `tests/Feature/Reports/NeracaReportTest.php`

- Known balances → expected piutang/hutang
- Persediaan roll-forward math
- Modal setting reflected in ekuitas

---

## Phase 3 — Piutang / Hutang Aging

### 3.1 ReceivablesService

- List contacts with non-zero balance
- Aging buckets from unpaid transactions (Sell/CashIn positive impact on receivable)
- Simplified v1: age from oldest open transaction date per contact

### 3.2 UI

- `/reports/receivables` — customers/resellers, balance, 0-30/31-60/61-90/90+
- `/reports/payables` — suppliers (mirror)

---

## Phase 4 — Enhancements (later)

- [ ] Extend `UpdateTransactionSummaries` with `tax_amount` columns
- [ ] `balance_snapshots` table + artisan command `reporting:snapshot-balances`
- [ ] Laba Rugi report
- [ ] Excel export via PhpSpreadsheet (already in project)
- [ ] `transactions.tax_invoice_number` for e-Faktur prep
- [ ] Historical neraca (month-end snapshot)

---

## Implementation order (single PR or split)

| PR | Contents | Depends on |
|----|----------|------------|
| PR1 | Phase 0 + Phase 1 (PPN reports) | User confirms tax period convention |
| PR2 | Phase 2 (Neraca + inventory roll-forward) | PR1 settings |
| PR3 | Phase 3 (Aging) | PR1 |
| PR4 | Phase 4 | PR2 |

---

## Code conventions (match existing app)

- Blade + Alpine, Tailwind CDN, no SPA
- `Gate::authorize(Report::getPermissions()[...])`
- Query params: `request()->query('month')` not `request('month')` on typed routes
- Run `./vendor/bin/pest tests/Feature/Reports/` after changes
- Run `BladePagesRenderTest` if views added
- Commit messages: `feat(reports): ...`, `test(reports): ...`

---

## Acceptance criteria (MVP)

### PPN Report

- [ ] Filter by month/year
- [ ] Keluaran lists all PPN sells + returns with correct totals
- [ ] Masukan lists all PPN buys + return-supplier with correct totals
- [ ] Summary shows net PPN payable
- [ ] CSV export works

### Neraca

- [ ] Shows Kas, Piutang, Persediaan, Hutang, Modal, Laba ditahan (plug)
- [ ] Persediaan uses roll-forward formula with settings
- [ ] Imbalance warning visible
- [ ] Drill-down to bank/customer list

### Settings

- [ ] Can set persediaan awal, modal, material accounts, company NPWP
- [ ] Values persist and affect reports

---

## Do NOT (from AGENTS.md)

- Reintroduce React/Inertia/Vite
- Record demo videos
- Rewrite existing operational reports in Phase 1
- Use double-entry journal posting — derive from signed balances
