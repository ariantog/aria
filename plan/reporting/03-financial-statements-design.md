# 03 — Financial Statements Design

Neraca, laba rugi, neraca saldo and arus kas from a single-entry system, using the signed balances
as the backbone.

---

## 1. The core idea

Aria Core does not have journal lines, and it should not grow them. It has something almost as
useful: **every addrbook carries a signed balance whose sign already encodes direction**, and that
balance is maintained synchronously and correctly (verified in `01` §6).

What is missing is a single sentence per account saying what it *is*. Add that, and the statements
fall out of a `GROUP BY`:

```
addrbook (+ its operation)  →  classification  →  statement line  →  neraca / laba rugi
```

This is a **mapping layer**, not a ledger rewrite. No change to how transactions are written, no
migration of historical data, no risk to balances. If a mapping is wrong you change one row and
re-run the report.

---

## 2. Classification model

### 2.1 Where classification lives

Two levels, because the existing data already splits this way:

- **`addrbooks.type` gives the default.** Every customer is a receivable, every supplier a payable,
  every bank an asset. This alone classifies the great majority of rows with zero data entry.
- **`operations` gives the detail for `type = 8 (Account)` rows**, which are the expense and income
  accounts and are the only ones that genuinely need judgement.

So: add classification columns to `operations`, add per-addrbook overrides for the exceptions, and
put the type-level defaults in config.

### 2.2 The classification fields

On `operations` (and as a nullable override on `addrbooks`):

| Field | Values | Purpose |
|---|---|---|
| `account_code` | e.g. `1-1100`, `5-2100` | sort order and a stable handle for report lines |
| `account_type` | `asset` \| `liability` \| `equity` \| `revenue` \| `cogs` \| `expense` \| `other_income` \| `other_expense` | which statement, which section |
| `statement_line` | e.g. `kas_bank`, `piutang_usaha`, `beban_operasional` | the line it rolls up to |
| `normal_balance` | `debit` \| `credit` | sign normalisation |
| `is_cash` | boolean | arus kas |
| `fiscal_correction` | `none` \| `positive` \| `negative` | koreksi fiskal, per `02` §8 |

### 2.3 Type-level defaults

Shipped in `config/accounting.php` so a fresh install classifies itself:

| Addrbook type | Default `account_type` | Default line | Sign rule |
|---|---|---|---|
| Bank (3) | `asset` | `kas_bank` | balance as-is; positive = cash held |
| Customer (1) | `asset` | `piutang_usaha` | `−balance`; negative balance = receivable |
| Reseller (7) | `asset` | `piutang_usaha` | `−balance` |
| Supplier (4) | `liability` | `hutang_usaha` | `+balance`; positive = payable |
| Account (8) | from `operations` | from `operations` | per `normal_balance` |
| Warehouse (2) | — | — | excluded; value comes from `warehouse_items` |
| V.Warehouse (5) | — | — | excluded |
| V.Account (6) | `other` | `suspense` | flagged for classification |
| Other (99) | `other` | `suspense` | flagged for classification |

Anything unclassified lands in `suspense` and is shown, never silently dropped — see §5.

### 2.4 Sign normalisation

One function, used by every statement:

```
signed_amount(addrbook) =
    normal_balance == 'debit'  ?  −balance   // customers: −(−1.000.000) = +1.000.000 piutang
                               :  +balance   // suppliers: +555.000 hutang
```

with `is_cash` accounts (banks) treated as debit-normal but read directly, because a bank's positive
balance already means cash held rather than an amount owed. The special case is worth one explicit
config flag rather than an implicit exception.

**A customer with a positive balance is not an error** — it is an advance received (uang muka
pelanggan), a liability. Likewise a supplier with a negative balance is a prepayment, an asset.
The report must split each population by sign rather than assume it, or the neraca will net a
receivable against a payable and understate both sides.

---

## 3. Neraca

Comparative, two periods, as of a period-end date.

```
AKTIVA
  Aktiva Lancar
    Kas & Bank                       Σ balance of type=3 (+ any is_cash account)
    Piutang Usaha                    Σ −balance of type∈{1,7} where balance < 0
    Piutang Lain-lain                Σ −balance of type=8 accounts classified as receivable
    Uang Muka Pembelian              Σ −balance of type=4 where balance < 0
    Persediaan Bahan Baku            from period_inventory        ← see 04
    Persediaan Barang Dalam Proses   from period_inventory        ← see 04
    Persediaan Barang Jadi           from period_inventory        ← see 04
    PPN Masukan (belum dikompensasi) from the tax module          ← see 02
  Aktiva Tetap
    Harga Perolehan                  fixed-asset register         ← see 05
    Akumulasi Penyusutan             (contra)
  Aktiva Lain-lain

PASIVA
  Kewajiban Lancar
    Hutang Usaha                     Σ balance of type=4 where balance > 0
    Uang Muka Pelanggan              Σ balance of type∈{1,7} where balance > 0
    Hutang Gaji & Borongan           unpaid gajis / borongans     ← see 04 §6
    Hutang Pajak                     PPN kurang bayar + PPh terutang
    Hutang Bank                      accounts classified as such
  Kewajiban Jangka Panjang
  Ekuitas
    Modal Disetor                    a setting / opening balance
    Laba Ditahan                     prior periods' accumulated profit
    Laba (Rugi) Tahun Berjalan       from laba rugi, current year
    Selisih Belum Teridentifikasi    the balancing figure          ← see §5
```

### Why the suspense line is the design, not a workaround

In a true double-entry system assets equal liabilities plus equity by construction. Here they will
not, because the double entry is partial: a Buy records the supplier payable but not the inventory
increase; a Sell records the customer receivable but not the inventory decrease or the cost of
sale. Inventory value comes from a separate estimation (`04`), and equity has no source at all
except an opening figure.

Two honest options:

- **Plug equity.** Derive `Laba Ditahan` as whatever balances the sheet. The neraca always balances
  and tells you nothing about its own accuracy.
- **Show the gap.** Take equity from its own sources (opening modal + accumulated profit) and
  present the residual as an explicit `Selisih Belum Teridentifikasi` line.

Take the second. The residual is the single most informative number the system can produce: it is
the measure of how much of the business the books do not yet explain, it shrinks as the inventory
valuation and labour posting land, and it is immediately visible when something regresses. A
balanced-by-construction neraca hides exactly the errors you most want to see.

Show it as both a rupiah amount and a percentage of total assets, and let it be drilled into via
the neraca saldo.

---

## 4. Laba Rugi

Monthly, with a YTD column and a prior-year comparative.

```
Penjualan Bruto                Σ |grand_total| of Sell
  (−) Retur Penjualan          Σ |grand_total| of Return
  (−) Potongan Penjualan       Σ discount on Sell
= Penjualan Neto

Harga Pokok Penjualan                                            ← see 04
    Persediaan Awal
  + Pembelian Bahan            Σ grand_total of Buy − ReturnSupplier
  + Biaya Tenaga Kerja Langsung  borongan + gaji produksi
  + Biaya Overhead Produksi
  − Persediaan Akhir
= Laba Kotor

Beban Operasional              type=8 accounts, account_type = expense,
                               grouped by operation
= Laba Usaha

Pendapatan / (Beban) Lain-lain
= Laba Sebelum Pajak
  (−) PPh
= Laba Bersih
```

### Amount column

Use `grand_total`, absolute value, and let the classification carry the direction. Do **not** use
`total`, for the reason in `01` §2, and not only because of the cash bug: `total` also excludes PPN,
and revenue should be recognised net of PPN while the receivable is gross. Two different figures
for two different purposes:

| Purpose | Column |
|---|---|
| Revenue in laba rugi | `grand_total − tax_amount` (net of VAT) |
| Receivable in neraca | `grand_total` (gross, matches the balance) |
| Output VAT in the tax report | `tax_amount` |

This is exactly the split that today's single `total` column fails to express, and getting it right
is what makes the equalisasi report in `02` §6.7 possible.

---

## 5. Neraca Saldo (trial balance) — build this first

Before either statement, build the plain listing: every account, its opening balance, its movement
for the period, its closing balance, and its classification.

It is the least glamorous report and the most useful one, because:

- it is the drill-down target for every neraca and laba rugi line,
- it is where you discover unclassified accounts, which are otherwise invisible,
- it exposes the suspense figure account by account,
- it is trivially verifiable against `addrbook_stats` and the existing ledger page.

Columns: `account_code`, `name`, `account_type`, `statement_line`, `saldo_awal`, `mutasi_debit`,
`mutasi_kredit`, `saldo_akhir`. Filter by period, with an "unclassified only" toggle.

---

## 6. Arus Kas

Direct method, which suits this data far better than the indirect method: cash accounts are
identifiable (`is_cash`), and every cash movement is a `CashIn` / `CashOut` / `Transfer` transaction
with a counterparty whose type says what kind of flow it is.

```
Arus Kas Operasi
  Penerimaan dari pelanggan        CashIn where counterparty type ∈ {1,7}
  Pembayaran ke pemasok            CashOut where counterparty type = 4
  Pembayaran beban operasional     CashOut where counterparty type = 8, expense
  Pembayaran gaji & borongan       CashOut to payroll accounts
  Pembayaran pajak                 CashOut to tax accounts
Arus Kas Investasi                 CashOut for fixed assets
Arus Kas Pendanaan                 CashIn/CashOut to owner / loan accounts
= Kenaikan (Penurunan) Kas
+ Kas Awal Periode
= Kas Akhir Periode
```

The closing figure must equal the sum of bank balances on the neraca — a free internal check, and
worth asserting in a test.

**This report is blocked until the `total` defect in `01` §2 is fixed**, since every input to it is
a cash transaction.

---

## 7. Perubahan Modal

Small and mostly derived: modal awal, plus laba bersih, minus prive/dividen, equals modal akhir.
Worth building only after the neraca stabilises and the suspense line is small.

---

## 8. Periods and reproducibility

Reports must be reproducible: a neraca printed for March must not change in June. Two mechanisms,
both in `05`.

- **`accounting_periods`** — one row per month with `status` (`open` / `closed`), `closed_at`,
  `closed_by`. Complements the existing `tutup_buku` day-of-month setting, which enforces the cutoff
  but records nothing.
- **`period_balances`** — on close, snapshot every account's opening, movement and closing balance,
  plus the inventory figures from `04`. Reports for a closed period read the snapshot; open periods
  compute live.

`01` §8 shows that prior months are already immutable in practice — today is always past last
month's closing day — so the snapshot is safe to take and cheap to trust.

Keep the recompute path available (`report:rebuild-period {year} {month}`) so a correction after a
reopen is possible, and log it.

---

## 9. Where these fit in the app

Follow the existing shape rather than inventing one:

- Controllers in `app/Http/Controllers/Reports/`, single-action `__invoke` where there is one view,
  matching `ExpenseReportController`.
- Permissions added to `App\Models\Report::getPermissions()` — `report-neraca`, `report-laba-rugi`,
  `report-neraca-saldo`, `report-arus-kas`, `report-ppn-keluaran`, `report-ppn-masukan`,
  `report-spt-ppn`, `report-penjualan-non-ppn` — then `PermissionGenerator::generateAll()`.
- Views in `resources/views/reports/`, plain server-rendered tables, `gray-*` palette, no Tabulator.
- Sidebar links in `resources/views/partials/sidebar-nav.blade.php`, gated on the new permissions or
  superadmin. Group the accounting reports under their own heading — the existing Reports section is
  already crowded.
- Query logic in `app/Services/Reporting/` rather than in controllers, so it is unit-testable
  without HTTP and reusable by the export and the period-close command.

Avoid MySQL-only SQL (`DATE_FORMAT`) so the suite runs on the SQLite dev database.
