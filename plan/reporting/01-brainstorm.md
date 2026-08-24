# Brainstorm: Tax, Neraca & Basic Financial Reporting

## 1. What you already have (usable today)

| Data | Source | Notes |
|------|--------|-------|
| DPP (tax base) | `transactions.total` − header `discount` already baked into flow; use `total - discount` or derive `grand_total - tax_amount` | Pre-PPN amount |
| PPN amount | `transactions.tax_amount` | Only non-zero when `addrbooks.ppn = true` on the relevant party |
| Grand total | `transactions.grand_total` | Signed; includes PPN |
| PPN rate | `settings.ppn_rate` | Default 11% |
| Who gets PPN | `addrbooks.ppn` | Buy → supplier; Sell → customer/reseller |
| Monthly rollups | `monthly_account_summaries`, `monthly_category_summaries` | **No tax columns**; uses `total` not `tax_amount` |
| Contact balances | `addrbook_stats.balance` | Point-in-time; good for piutang/hutang |
| Bank balances | Same `addrbook_stats` on type=Bank | Kas position |
| Warehouse stock value | `WarehouseItemReportController` formula | `qty × cost` (asset lancar) or `qty × price × 0.3` (finished goods) |
| Production labour cost | `borongans.total` (weekly-ish date ranges) | Piece-rate jahit payroll |
| Office payroll | `gajis.total_gaji` | Monthly karyawan |
| Expense by account | `ExpenseReportController` | Bank ↔ Journal Account cash flows |
| Fixed assets | `items.type = ASSET_TETAP` + depreciation tx type 18 | Partial |
| Book closing day | `settings.tutup_buku` | Day-of-month cutoff |

## 2. Indonesian tax reporting (PPN) — what you need

### 2.1 Core reports (Phase 1 — highest value)

These map directly to **SPT Masa PPN** working papers:

#### A. Rekapitulasi PPN Keluaran (Output VAT)

Transactions where **we charge PPN** on sales:

- **Sell** (type 2): `tax_amount > 0`, customer `ppn = true`
- **Return** (type 15): reduces keluaran (positive `tax_amount`, treat as credit)
- **ReturnSupplier** is masukan, not keluaran

Columns per faktur/row:

| Column | Source |
|--------|--------|
| Tanggal | `transactions.date` |
| No. Faktur / Invoice | `invoice_number` |
| Nama lawan transaksi | `receiver.name` (customer/reseller) |
| NPWP lawan transaksi | **MISSING** — need `addrbooks.npwp` |
| DPP | `grand_total - tax_amount` (absolute value for display) |
| PPN | `tax_amount` (absolute) |
| DPP + PPN | `abs(grand_total)` |

Summary: `Σ DPP keluaran`, `Σ PPN keluaran`

#### B. Rekapitulasi PPN Masukan (Input VAT)

Transactions where **we pay PPN** on purchases:

- **Buy** (type 1): `tax_amount > 0`, supplier `ppn = true`
- **ReturnSupplier** (type 17): reduces masukan

Same columns; lawan transaksi = `sender` (supplier).

Summary: `Σ DPP masukan`, `Σ PPN masukan`

#### C. Ringkasan SPT Masa PPN

```
PPN Keluaran (dikreditkan)     = Σ PPN keluaran − retur penjualan PPN
PPN Masukan (dapat dikreditkan)= Σ PPN masukan − retur pembelian PPN
PPN Kurang/(Lebih) Bayar       = Keluaran − Masukan
```

Filter: bulan + tahun (calendar month or `tutup_buku` period — decide one convention).

#### D. Daftar Penjualan Tanpa PPN / Pembelian Tanpa PPN

Useful for reconciliation — same queries with `tax_amount = 0` OR `addrbooks.ppn = false`, split by whether it's intentional (no PPN customer) vs data error.

### 2.2 Data gaps for tax (must decide / add)

| Gap | Recommendation | Priority |
|-----|----------------|----------|
| **NPWP** on customers/suppliers | Add `addrbooks.npwp` (nullable string, validated format) | High if e-Faktur export needed |
| **Nomor Faktur Pajak** | Add `transactions.tax_invoice_number` (nullable) OR separate `tax_invoices` table | Medium — manual entry OK at first |
| **PKP status** | Company PKP flag in settings (`company_npwp`, `company_name`, `is_pkp`) | High for header on reports |
| **PPN on Return types** | Verify `CreateItemTransaction` applies PPN same as parent sell/buy | Verify in code |
| **Aggregations lack tax** | Extend `monthly_*_summaries` OR new `monthly_tax_summaries` table | Medium (performance) |
| **Export format** | CSV/Excel for accountant; later XML for e-Faktur | Phase 2 |

### 2.3 What we can ignore for now

- PPh (21, 23, 25, 29) — separate module later
- e-Faktur real-time API integration — manual export first
- PPN tidak dipungut / PMK exemptions — rare for your customer base; add flag later if needed

## 3. Neraca (Balance Sheet)

Indonesian neraca format (simplified):

```
AKTIVA
  Aktiva Lancar
    Kas dan setara kas
    Piutang usaha
    Persediaan
    Aktiva lancar lainnya
  Aktiva Tetap
    Peralatan / kendaraan (net of penyusutan)
  TOTAL AKTIVA

KEWAJIBAN
  Hutang usaha
  Hutang gaji / borongan (unpaid)
  Hutang pajak (PPN kurang bayar)
  Kewajiban lancar lainnya
TOTAL KEWAJIBAN

EKUITAS
  Modal
  Laba ditahan
TOTAL EKUITAS

TOTAL KEWAJIBAN + EKUITAS  (= TOTAL AKTIVA)
```

### 3.1 Mapping from Aria (no double-entry)

| Neraca line | Derivation | Confidence |
|-------------|------------|------------|
| **Kas** | Sum `addrbook_stats.balance` for `type = Bank` where balance represents cash on hand | High — verify sign (bank receiver on CashIn gets +) |
| **Piutang usaha** | Sum of **negative** `addrbook_stats.balance` for Customer + Reseller | High |
| **Hutang usaha** | Sum of **positive** `addrbook_stats.balance` for Supplier | High |
| **Persediaan** | See §4 below | Medium — needs formula + opening balance |
| **Aktiva tetap** | `items.type = ASSET_TETAP`: `cost × qty` or dedicated asset register; minus accumulated depreciation from type-18 transactions | Medium |
| **Hutang PPN** | Unpaid PPN from SPT summary (or setting) | Low — manual until SPT module |
| **Modal / Laba ditahan** | **Plug / setting** — see balancing below | Required |

### 3.2 Auto-balancing without double-entry

Because there's no journal to force `Aktiva = Kewajiban + Ekuitas`, use:

1. **Compute known lines** from addrbook balances + inventory + fixed assets.
2. **Ekuitas (or Laba ditahan)** = `Total Aktiva − Total Kewajiban − Modal` (modal from settings).
3. Alternatively: **Persediaan plug** if everything else is trusted — less ideal.

Show a **"selisih / tidak seimbang"** warning when `|Aktiva − (Kewajiban + Ekuitas)| > threshold` so user knows data is incomplete.

### 3.3 Point-in-time vs period-end

Neraca is **as of a date** (usually last day of month):

- Piutang/hutang: need **historical balance** at date, not just current `addrbook_stats`. Options:
  - (A) Replay `transactions` running balance per contact as of date — accurate, slower
  - (B) Nightly snapshot table `balance_snapshots(addrbook_id, date, balance)` — better long-term
- Kas: same issue — current stat is OK only for "today"

**Recommendation:** Phase 1 neraca uses **current balances** + inventory as-of today; Phase 2 adds month-end snapshots.

## 4. Persediaan awal — your proposed formula

You described:

```
persediaan_akhir_bulan_lalu = persediaan_awal_bulan_lalu
                              + pembelian_bahan (dari ledger account tertentu)
                              − biaya_produksi (proxy: borongan / gaji jahit)
```

### 4.1 Refined formula

```
Persediaan Akhir (bulan M) =
    Persediaan Awal (bulan M)          ← setting or rolled forward
  + Pembelian Bahan Baku (bulan M)     ← Buy tx material items OR cash from material accounts
  + Biaya Produksi Dialokasikan (bulan M)  ← optional: borongan.total for period
  − HPP / COGS (bulan M)               ← Sell at cost OR proxy
  ± Penyesuaian                        ← manual adjustment setting
```

For **garment manufacturing** where material usage isn't tracked per piece:

**Pragmatic COGS proxy (pick one):**

| Method | Source | Pros | Cons |
|--------|--------|------|------|
| **Borongan total** | `SUM(borongans.total)` where `from/to` overlaps month | Matches your payroll reality | Ignores fabric cost in COGS |
| **Sell × cost %** | `SUM(sell detail qty × item.cost)` | Tied to sales | Needs reliable `items.cost` |
| **Sell × 30% price** | Same as warehouse report rule | Already used elsewhere | Rough |
| **Combined** | `borongan + material purchases − persediaan change` | Best approximation | More settings |

### 4.2 Settings needed

| Setting slug | Type | Purpose |
|--------------|------|---------|
| `reporting.persediaan_awal` | decimal | Seed value for first month ever |
| `reporting.material_account_ids` | json array of addrbook ids | Which journal accounts count as "pembelian bahan" |
| `reporting.production_cost_source` | enum: `borongan` \| `sell_cost` \| `manual` | How to estimate production consumption |
| `reporting.cogs_method` | enum | For laba rugi later |
| `reporting.modal` | decimal | Owner equity for neraca |
| `reporting.company_npwp` | string | Report header |
| `reporting.company_name` | string | Report header |

### 4.3 Monthly roll-forward

Store computed `persediaan_akhir` per month in new table `monthly_inventory_values(year, month, opening, purchases, production_cost, cogs, closing, adjustment, notes)` so next month's opening = last month's closing.

## 5. Other reports to suggest (basic set)

Beyond tax + neraca, build toward a minimal **financial reporting suite**:

### Phase 1 (with tax + neraca)

| Report | Indonesian name | Primary source |
|--------|-----------------|----------------|
| PPN Keluaran / Masukan / Ringkasan | Laporan PPN | `transactions` |
| Neraca | Neraca | balances + inventory settings |
| Piutang aging | Umur piutang | transactions by customer, bucket 0-30/31-60/61-90/90+ |
| Hutang aging | Umur hutang | same for suppliers |

### Phase 2 (profitability)

| Report | Name | Source |
|--------|------|--------|
| Laba Rugi sederhana | Laporan Laba Rugi | Penjualan − HPP − beban (expense report) − borongan/gaji |
| Penjualan per periode | (exists: item-sales) | extend with DPP/PPN columns |
| Pembelian per periode | (exists: purchase) | extend with PPN |

### Phase 3 (management)

| Report | Source |
|--------|--------|
| Cash flow (exists) | already have |
| Produksi efficiency | produksi status + borongan per item |
| Stock valuation history | `daily_inventory_summaries` + cost |

## 6. Architecture recommendation

```
┌─────────────────────────────────────────────────────────┐
│  Report UI (Blade + Alpine)                             │
│  /reports/tax/ppn, /reports/neraca, /reports/receivables│
└────────────────────────┬────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────┐
│  Report Services (new)                                │
│  TaxReportService, NeracaService, InventoryValuation  │
└────────────────────────┬────────────────────────────────┘
                         │
        ┌────────────────┼────────────────┐
        ▼                ▼                ▼
  transactions    addrbook_stats    settings + new
  + addrbooks     + snapshots       monthly_inventory_values
```

**Principles:**

1. **Query source of truth** (`transactions`) for tax — don't trust aggregations until extended.
2. **New `Reports/` namespace** — ignore legacy report controller patterns.
3. **Permissions** — extend `Report::getPermissions()`.
4. **Period filter** — month/year everywhere; respect `tutup_buku` optionally.
5. **Export** — `maatwebsite/excel` or CSV via Laravel response.

## 7. Open questions for you (before coding)

1. **Periode pajak:** calendar month (1–30/31) or follow `tutup_buku` (e.g. 29th–28th)?
2. **NPWP:** do you issue e-Faktur? If yes, need NPWP on contacts + faktur numbers.
3. **Persediaan:** confirm COGS proxy — borongan only, or borongan + material purchases from specific accounts?
4. **Material accounts:** which journal accounts (IDs/names) represent fabric/material purchases?
5. **Neraca date:** "as of today" OK for v1, or must be historical month-end?
6. **Modal / laba ditahan:** single setting for modal, rest as plug? Or track retained earnings from laba rugi?
7. **Returns:** do retur penjualan/pembelian always mirror PPN of original invoice?

## 8. Sign convention cheat sheet (for reports)

```
Contact balance (addrbook_stats):
  balance < 0  →  they owe us   →  Piutang (asset)
  balance > 0  →  we owe them   →  Hutang (liability)

Sell grand_total: negative (reduces customer balance → more negative → they owe more)
Buy grand_total: positive (increases supplier balance → we owe more)
```

When displaying amounts on tax reports, use **absolute values** with a separate "jenis" column (faktur / retur).
