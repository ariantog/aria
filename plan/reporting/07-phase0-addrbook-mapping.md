# Phase 0: Addrbook Mapping UI (Composer plan)

> **Goal:** Make every contact report-ready before building tax/neraca reports.  
> **Prerequisite for:** all reporting phases.

---

## Why first?

Reports need classified data at source. Today:

- Banks have no entity / PKP assignment
- Ledger accounts set `operation_id` only via Journal → Account List (not addrbook edit)
- Marketplace customers exist as separate contacts but have no **default bank**
- No NPWP field on addrbook form
- Cash In/Out has no reporting metadata — classification must come from contact + bank + ledger operation

---

## New schema

### `company_entities`

```php
Schema::create('company_entities', function (Blueprint $table) {
    $table->id();
    $table->string('name');           // e.g. PT Crystal, PT Core, Non-PKP Personal
    $table->string('slug')->unique();
    $table->boolean('is_pkp')->default(false);
    $table->string('npwp', 20)->nullable();
    $table->decimal('modal', 15, 2)->nullable();        // rough owner capital
    $table->decimal('laba_ditahan', 15, 2)->nullable(); // opening retained earnings
    $table->boolean('is_active')->default(true);
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

### `addrbooks` additions

```php
$table->string('npwp', 20)->nullable();
$table->foreignId('company_entity_id')->nullable()->constrained()->nullOnDelete();
$table->foreignId('default_bank_id')->nullable()->constrained('addrbooks')->nullOnDelete();
$table->foreignId('operation_id')->nullable(); // already exists; expose on form for type=Account
$table->boolean('is_active_in_reports')->default(true);
$table->boolean('is_internal_lending')->default(false); // customer investment / pinjaman
$table->string('reporting_role', 30)->nullable(); // see enum below
```

### `operations` addition

```php
$table->string('report_category', 40)->nullable();
// marketing, gaji, sewa, kantor, transport, pajak, maintenance, produksi, pemasukan, pembelian_bahan, internal, other
```

### `reporting_role` enum (on addrbook, type-specific)

| Type | Values | Purpose |
|------|--------|---------|
| Bank | — (uses `company_entity_id`) | Tax entity assignment |
| Customer/Reseller | `sales_channel`, `internal_lending`, `general` | Channel + F2 flag |
| Supplier | `material`, `general` | Material vs other suppliers |
| Account | — (uses `operation_id` → `report_category`) | Expense classification |

`is_internal_lending = true` OR `reporting_role = internal_lending` for investment customers.

---

## UI: Addrbook edit form (type-aware sections)

Extend `resources/views/addrbook/partials/form.blade.php` with Alpine `x-show` by type.

### All types

- **NPWP** (optional text input)

### Type = Bank (3)

| Field | Widget | Notes |
|-------|--------|-------|
| Company entity | select | Required for active banks; create-link to entity admin |
| Active in reports | toggle | Hide inactive / Transfer Pending / Investment |
| PKP | read-only badge | From entity `is_pkp` (not duplicated on bank) |

Show helper: "PPN/PPH on CashIn is determined by this bank's entity."

### Type = Customer (1) / Reseller (7)

| Field | Widget | Notes |
|-------|--------|-------|
| Default bank | select (banks only) | For marketplace channels — where payments land |
| PPN counterparty | toggle (existing `ppn`) | Relabel: "PKP counterparty (faktur / masukan)" |
| Reporting role | select | `sales_channel` / `general` / `internal_lending` |
| Internal lending | toggle | F2: investment to customer; exclude from sales tax totals |

When `reporting_role = sales_channel`, show hint to pick default bank matching channel entity.

### Type = Supplier (4)

| Field | Widget |
|-------|--------|
| PPN counterparty | toggle (existing) |
| Material supplier | toggle OR `reporting_role = material` | Counts toward persediaan purchases |

### Type = Account (8)

| Field | Widget | Notes |
|-------|--------|-------|
| Operation (parent ledger) | select | **Move from journal-only UI** — required for new accounts |
| Report category | read-only | From operation.report_category; override optional later |

Show warning if `operation_id` is null (23 orphans in prod).

### Type = Warehouse (2)

No reporting fields v1 (persediaan uses global warehouse list in settings).

---

## Company entities admin

New simple CRUD (settings area or `/reports/entities`):

- List entities with PKP badge, bank count
- Create/edit: name, is_pkp, npwp, modal, laba_ditahan, notes
- Banks tab: list banks assigned to this entity

Alternatively: inline on bank edit only for v1 (minimal).

**Recommendation:** `Reports/CompanyEntityController` + views under `resources/views/reports/entities/` — keeps accounting setup together.

---

## Operations: report category

Extend `OperationController` edit form:

- Add `report_category` dropdown
- Seed default mapping for existing op IDs (see v2 architecture doc)

Accounts inherit category through `operation_id`.

---

## Controller / validation changes

### `UpdateAddrbookRequest` / `StoreAddrbookRequest`

```php
'npwp' => 'nullable|string|max:20',
'company_entity_id' => 'nullable|exists:company_entities,id',
'default_bank_id' => 'nullable|exists:addrbooks,id',
'operation_id' => 'nullable|exists:operations,id',
'is_active_in_reports' => 'boolean',
'is_internal_lending' => 'boolean',
'reporting_role' => 'nullable|string|in:sales_channel,general,internal_lending,material',
```

Conditional rules:

- `company_entity_id` required when `type = Bank` and `is_active_in_reports = true`
- `operation_id` required when `type = Account` (soft: warn only for existing orphans)
- `default_bank_id` must reference type=Bank

### `AddrbookController@edit`

Pass to view:

- `$companyEntities`
- `$banks` (active)
- `$operations` (for account type)

---

## Index page enhancements

Show badges on addrbook index (per type):

| Type | Badges |
|------|--------|
| Bank | Entity name, PKP/Non-PKP, inactive |
| Customer | Default bank, internal lending |
| Supplier | Material, PPN |
| Account | Operation name or "Uncategorized" |

Filter: "Unmapped banks" / "Uncategorized accounts" quick links for data cleanup.

---

## Data migration / seeding

### 1. Seed `company_entities` (draft — user confirms names)

Run after migration; user edits in UI.

### 2. Artisan: `reporting:suggest-mappings`

Read-only suggestions from `database/addrbooks.sql` patterns:

- Bank name → entity guess
- Customer name contains Shopee/Tokopedia/TikTok → `sales_channel` + bank guess
- Supplier fabric keywords → `material`
- Account 1558 → material; 2696 → produksi

Output CSV or tinker table — **do not auto-apply** without review.

### 3. Artisan: `reporting:estimate-equity --year=2025`

For E1/E2 guesstimate:

```
Per entity (once banks mapped):
  kas_year_end     = sum bank balances replayed to 2025-12-31
  piutang          = negative customer balances
  hutang           = positive supplier balances
  suggested_modal  = user input OR prompt
  suggested_laba_ditahan = aktiva - kewajiban - modal - persediaan_guess
```

Show in entity edit page as "Suggested from 2025 data" with copy-to-field buttons.

Exclude transactions before **2025-01-01** (F1: omit 2024).

---

## Tests

- `tests/Feature/AddrbookReportingFieldsTest.php`
  - Bank requires entity when active
  - Customer default_bank must be bank type
  - Account saves operation_id via addrbook edit
  - NPWP optional saves null
- `tests/Feature/CompanyEntityTest.php` — CRUD
- Update `BladePagesRenderTest` for edit form

---

## Files to create/modify

```
database/migrations/xxxx_create_company_entities_table.php
database/migrations/xxxx_add_reporting_fields_to_addrbooks_table.php
database/migrations/xxxx_add_report_category_to_operations_table.php
app/Models/CompanyEntity.php
app/Http/Controllers/Reports/CompanyEntityController.php
app/Http/Requests/StoreCompanyEntityRequest.php
app/Http/Requests/UpdateCompanyEntityRequest.php
app/Console/Commands/SuggestReportingMappings.php
app/Console/Commands/EstimateEquityFromYear.php
resources/views/addrbook/partials/form.blade.php  (major)
resources/views/reports/entities/*
app/Http/Controllers/AddrbookController.php
app/Http/Requests/StoreAddrbookRequest.php
app/Http/Requests/UpdateAddrbookRequest.php
app/Models/Addrbook.php  (relations, casts)
app/Models/Operation.php
resources/views/journals/operations/*  (report_category)
routes/web.php
resources/views/partials/sidebar-nav.blade.php
```

---

## Acceptance criteria

- [ ] Can create company entities with PKP flag, modal, laba ditahan
- [ ] Every active bank assignable to exactly one entity
- [ ] Customer/reseller can set default bank + internal lending flag
- [ ] Supplier can mark material + PPN counterparty
- [ ] Ledger account can set operation from addrbook edit (not only journal UI)
- [ ] Index shows unmapped/uncategorized warnings
- [ ] `reporting:estimate-equity --year=2025` produces per-entity suggestions
- [ ] No report pages built yet — mapping only

---

## After Phase 0 → Phase 1

Once mappings are ~80% complete:

1. Revamp `UpdateTransactionSummaries`
2. PPN/PPH report (CashIn-based, per entity)
3. Persediaan from Jan 2026
4. Neraca with snapshots
