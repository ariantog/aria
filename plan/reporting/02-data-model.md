# Data Model for Reporting

## Existing tables — field reference

### `transactions`

| Field | Reporting use |
|-------|---------------|
| `date` | Period filter |
| `type` | Buy/Sell/Return/ReturnSupplier for PPN |
| `sender_id`, `receiver_id` | Lawan transaksi |
| `sender_type`, `receiver_type` | Filter banks, suppliers, customers |
| `invoice_number` | Faktur reference |
| `total` | Line subtotal (pre header discount) |
| `discount`, `discount_percent` | DPP adjustment |
| `adjustment` | Manual DPP adjustment |
| `tax_amount` | PPN |
| `grand_total` | Signed total incl. PPN |
| `status` | Only `status = 1` (active) |

**DPP formula:** `abs(grand_total) - abs(tax_amount)` or `total - discount + adjustment` (verify equal).

### `addrbooks`

| Field | Reporting use |
|-------|---------------|
| `type` | Customer/Supplier/Bank/Account |
| `ppn` | Whether PPN applies on Buy/Sell |
| `name` | Display |
| `operation_id` | Links Account type to journal operation (expense category) |

**Missing:** `npwp`, `tax_id` (NPWP-16 format)

### `addrbook_stats`

| Field | Use |
|-------|-----|
| `balance` | Current piutang/hutang/kas |

### `borongans`

| Field | Use |
|-------|-----|
| `from`, `to` | Allocate to month (overlap logic) |
| `total` | Production labour cost proxy |
| `jahit_id` | Worker reference |

### `gajis`

| Field | Use |
|-------|-----|
| `bulan`, `tahun` | Monthly office payroll |
| `total_gaji` | Beban gaji for laba rugi |

### `items`

| Field | Use |
|-------|-----|
| `type` | ITEM vs ASSET_LANCAR vs ASSET_TETAP |
| `cost`, `price` | Valuation |
| `group_id` | Category breakdown |

### `warehouse_items`

| Field | Use |
|-------|-----|
| `quantity` | Stock on hand per warehouse |

---

## Proposed new tables

### `monthly_tax_summaries` (optional — performance)

```sql
year, month,
ppn_keluaran_dpp, ppn_keluaran_tax,
ppn_masukan_dpp, ppn_masukan_tax,
retur_keluaran_dpp, retur_keluaran_tax,
retur_masukan_dpp, retur_masukan_tax
UNIQUE(year, month)
```

Populated by `UpdateTransactionSummaries` job extension or nightly command.

### `monthly_inventory_values`

```sql
year, month,
opening_balance,          -- persediaan awal
material_purchases,       -- from configured accounts / buy tx
production_cost,          -- borongan allocated
cogs,                     -- cost of goods sold
adjustment,               -- manual
closing_balance,          -- computed
notes, user_id, timestamps
UNIQUE(year, month)
```

### `balance_snapshots` (Phase 2 — historical neraca)

```sql
date, addrbook_id, balance
INDEX(date), INDEX(addrbook_id, date)
```

Populated nightly or on `tutup_buku`.

---

## Proposed new settings (group: `Reporting`)

| slug | type | default |
|------|------|---------|
| `reporting.company_name` | string | null |
| `reporting.company_npwp` | string | null |
| `reporting.is_pkp` | bool | true |
| `reporting.modal` | decimal | 0 |
| `reporting.persediaan_awal` | decimal | 0 |
| `reporting.persediaan_awal_month` | string | null | first month seed applies |
| `reporting.material_account_ids` | json | [] |
| `reporting.production_cost_source` | string | `borongan` |
| `reporting.cogs_method` | string | `sell_cost` |
| `reporting.warehouse_ids_for_inventory` | json | [] | which warehouses count |

---

## Proposed schema additions (migrations)

### `addrbooks`

```php
$table->string('npwp', 20)->nullable()->after('ppn');
```

### `transactions` (optional Phase 2)

```php
$table->string('tax_invoice_number')->nullable();
$table->boolean('is_tax_invoice')->default(false);
```

---

## Query sketches

### PPN Keluaran (month M, year Y)

```sql
SELECT t.*, r.name AS customer_name, r.npwp
FROM transactions t
JOIN addrbooks r ON r.id = t.receiver_id
WHERE t.type IN (2, 15)          -- Sell, Return
  AND t.status = 1
  AND YEAR(t.date) = :year AND MONTH(t.date) = :month
  AND t.tax_amount <> 0
ORDER BY t.date, t.id
```

Return (15): show as negative/credit row in summary.

### Piutang (receivables)

```sql
SELECT a.id, a.name, s.balance
FROM addrbooks a
JOIN addrbook_stats s ON s.addrbook_id = a.id
WHERE a.type IN (1, 7)   -- Customer, Reseller
  AND s.balance < 0
ORDER BY s.balance ASC
```

### Kas

```sql
SELECT a.name, s.balance
FROM addrbooks a
JOIN addrbook_stats s ON s.addrbook_id = a.id
WHERE a.type = 3         -- Bank
```

### Persediaan (snapshot)

Reuse `WarehouseItemReportController` SQL; sum `total_cost` across selected warehouses.

### Borongan for month

```sql
SELECT SUM(total) FROM borongans
WHERE from <= :last_day AND to >= :first_day
```

---

## Permissions to add

```php
'view-tax-ppn'     => 'report-tax-ppn',
'view-neraca'      => 'report-neraca',
'view-receivables' => 'report-receivables',
'view-payables'    => 'report-payables',
'manage-reporting-settings' => 'reporting-settings',
```
