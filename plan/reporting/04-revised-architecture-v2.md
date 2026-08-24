# Revised Reporting Architecture (v2)

Incorporates feedback from Opus brainstorm + maintainer answers (Aug 2026).

## Core design shifts from v1

| Topic | v1 plan | v2 (agreed direction) |
|-------|---------|-------------------------|
| PPN driver | `addrbooks.ppn` on customer/supplier | **Company entity + bank PKP status**; payment bank determines tax treatment |
| Cash In/Out | Ignored in money reports | **Must classify** via ledger account + operation parent → report category |
| Persediaan | Warehouse valuation formula | **Buy increases**, sell + borongan + material cash-out **decreases** |
| Period | Calendar month | Calendar month; also **quarter / semester / year** views |
| Neraca date | Point-in-time current balance | **Historical month-end** (requires balance snapshots) |
| NPWP | Required for e-Faktur | **Optional** on contacts |
| Production cost | Borongan table | Borongan **and** ledger **Gaji Mingguan** (id 2696, op 4) |
| Material purchases | Configured account IDs | **Borongan + material purchases** (Buy + CashOut to material accounts) |

---

## 1. Company entities (new concept)

Multi-company within one Aria instance. Each legal/business unit has its own tax treatment.

### Proposed table: `company_entities`

```
id, name, slug, is_pkp (bool), npwp (nullable), notes, is_active, timestamps
```

### Proposed pivot: `company_entity_banks`

```
company_entity_id, addrbook_id (bank), is_active
UNIQUE(addrbook_id)
```

**Rules:**

- Every **active bank** belongs to exactly one entity (or "unassigned" bucket shown in setup warnings).
- `is_pkp = true` → normal PPN rules on revenue through that bank.
- `is_pkp = false` → no PPN; apply **PPH Final 0.5%** on qualifying inflows (marketplace / non-PKP entity — confirm scope).

### Tentative entity groupings (from bank names — **needs confirmation**)

| Entity (draft) | Banks | PKP? |
|----------------|-------|------|
| Crystal / Cipta | BCA CRYSTAL (1158), BCA CIPTA (2821), Kas Kecil Sambisari (263), Kas Pameran SBY (982) | ? |
| Core / AGM | BCA AGM (2929), BCA 510 (2704), Supplier Umum PT CORE | ? |
| Indosport / UAI | BCA INDOSPORT (2853), BCA UAI (2940) | ? |
| Citos / BSD retail | Kas Kecil Citos (2856), Deposit Citos (2843), BCA 9889 (2893) | ? |
| Non-operating | Investment (2793), Transfer Pending (2930), Hutang Direksi (300) | Exclude from ops reports? |

---

## 2. Tax (PPN / PPH Final) — bank-centric model

### Output tax (penjualan)

**Primary rule:** classify revenue by the **bank that receives payment** (CashIn `receiver_id` when type=Bank).

```
CashIn → receiver bank → company_entity → is_pkp?
  PKP entity    → DPP + PPN 11% (on qualifying revenue)
  Non-PKP entity → no PPN; PPH Final 0.5% on gross (confirm base)
```

**Open design choice:** credit sales (Sell before CashIn) — see questions doc.

`addrbooks.ppn` on customer/supplier/reseller becomes a **secondary flag**:
- "This contact expects a tax invoice / is a PKP counterparty" (for masukan + documentation)
- Does **not** override entity PKP status for output tax

### Input tax (pembelian)

CashOut / Buy where supplier has `ppn = true` (only 2 suppliers today) OR paid from PKP entity bank?

**Proposed:** PPN masukan when **supplier.ppn = true** on Buy; bank entity used for **per-entity masukan report** when paid via CashOut from that entity's bank.

### Returns

Retur should mirror original tax treatment:
- Store `tax_amount` on return tx (already done if same `shouldApplyPpn` logic)
- v2: also store `company_entity_id` on transaction once allocation is known

---

## 3. Cash movements → report categories

Cash In/Out always pairs **Bank ↔ Contact** (customer, supplier, reseller, or ledger account).

### Classification paths

| Cash flow | Report bucket |
|-----------|---------------|
| CashIn from Customer/Reseller | **Pemasukan** — subcategory by customer channel or default |
| CashIn from Account | Internal transfer — exclude from P&L |
| CashOut to Supplier | **Pembelian bahan** if supplier; else expense |
| CashOut to Account (ledger) | **Biaya** — category from `operation` parent |
| CashOut to Customer/Reseller | Refund / adjustment — separate bucket |

### Operation → report category

Add to `operations` table (or new `report_categories`):

```
operations.report_category_id  (nullable FK)
```

Or enum slug on operation: `marketing`, `gaji`, `sewa`, `pajak`, `produksi`, `pemasukan`, `pembelian_bahan`, etc.

**Example:** op 3 (Marketing) → accounts: Biaya Iklan, Shopee Cost, Biaya Sponsor → all roll into "Marketing" on expense report.

Accounts **without** `operation_id` (23 orphans in production data) need cleanup or manual category assignment.

---

## 4. Persediaan (inventory) ledger

Signed-balance intuition applied to inventory **flow** (not double-entry):

```
Persediaan Akhir = Persediaan Awal
                 + Pembelian (Buy tx, materials)
                 + [optional: material CashOut to suppliers — confirm]
                 − Pemakaian Persediaan
```

**Pemakaian persediaan** =
- Sell COGS (qty × cost, or configured method)
- **+ Borongan total** (gaji mingguan jahit) for period
- **+ CashOut** to material/production ledger accounts (e.g. Material Produksi 1558)

**Persediaan awal:** seed in settings for first month; roll forward monthly in `monthly_inventory_values`.

Per-entity persediaan: only if buys/payments can be attributed to entity (via bank on CashOut or warehouse assignment).

---

## 5. Neraca (historical month-end)

Requires **`balance_snapshots`** table:

```
date (month-end), addrbook_id, balance, company_entity_id (nullable)
```

Nightly or on-demand command: `reporting:snapshot-balances --date=2026-07-31`

| Line | Source |
|------|--------|
| Kas | Sum bank balances per entity, snapshot date |
| Piutang | Negative customer/reseller balances |
| Hutang | Positive supplier balances |
| Persediaan | `monthly_inventory_values.closing` |
| Aktiva tetap | ASSET_TETAP items |
| Hutang / kewajiban | + unpaid tax payable per entity |
| Modal | Setting per entity |
| Laba ditahan | See §6 |

**Consolidated neraca** = sum across entities (with inter-entity eliminations later if needed).

---

## 6. Modal vs Laba ditahan (explanation)

| Term | Meaning | How to set in Aria |
|------|---------|-------------------|
| **Modal** | Owner's invested capital — money put into the business | Manual setting per entity (`reporting.modal` or on `company_entities`). Changes rarely (new investment, withdrawal). |
| **Laba ditahan** | Accumulated profit from past years kept in the business | Ideally: prior years' net income summed. Without full history: **plug** = Aktiva − Kewajiban − Modal − laba tahun berjalan |
| **Laba tahun berjalan** | Current year profit | From Laba Rugi report (penjualan − HPP − beban) |

**Recommended v1 approach:**

1. Set **Modal** manually per entity (ask your accountant once).
2. Compute **Laba tahun berjalan** from reports (year-to-date).
3. **Laba ditahan** = plug for opening balance sheet equity (or manual setting for "retained earnings at start of year").
4. Show **selisih** if plug doesn't close — flags incomplete data.

This is normal for businesses migrating without full historical journals.

---

## 7. Reporting dimensions

Every financial report supports:

| Dimension | Values |
|-----------|--------|
| Entity | Single entity / **Konsolidasi** (all) |
| Period | Bulan / **3 bulan** / **6 bulan** / **Tahun** |
| As-of | Month-end date for neraca; range for P&L/tax |

---

## 8. UpdateTransactionSummaries — revamp scope

Current job problems:
- Uses `transaction.total` (pre-tax), ignores `tax_amount`
- Cash In/Out partially tracked but not tied to operations/categories
- No entity dimension
- No monthly tax rollups
- Inventory summary simplified (movement columns removed in later migration)

**Revamp → new job or replace:** `RebuildReportAggregates`

Per transaction, update:
- `monthly_tax_summaries` (by entity, year, month): keluaran/masukan DPP + PPN
- `monthly_category_summaries` extended: by operation report_category
- `monthly_cash_flow_summaries`: bank + account + entity
- `monthly_inventory_values` components
- Queue remains async; add **full recalc command** for backfill

---

## 9. Material accounts — draft recommendation (from addrbooks.sql)

**Tier 1 — definite material / production inputs:**

| ID | Name | Notes |
|----|------|-------|
| 1558 | Material Produksi | Direct material; no operation parent |
| 2696 | Gaji Mingguan | Borongan mirror in ledger (op 4 Gaji) |
| 814 | Biaya Outsource | External production |

**Tier 2 — purchases via suppliers (Buy tx + CashOut to type=4):**

| ID | Name | PPN |
|----|------|-----|
| 6 | PT.Sinar Pang Jaya (kain) | no |
| 42 | Cicil Pang Jaya | no |
| 1003 | CV TRIJAYA GEMILANG | no |
| 1443 | New Patinda | no |
| 2811 | PT Coats Rejo | **yes** |
| 2813 | Supplier Umum PT CORE | **yes** |

**Tier 3 — confirm with user:**

| ID | Name |
|----|------|
| 899 | Jahit Luar |
| 885 | Permak |
| 1644 | Plotter |

**Exclude from material** (operating expense): marketing op 3, sewa op 7, pajak op 18, etc.

---

## 10. Implementation phases (updated)

| Phase | Deliverable |
|-------|-------------|
| **0** | `company_entities`, bank assignment UI, report categories on operations, balance snapshots |
| **1** | Revamp aggregate job + full recalc command |
| **2** | PPN/PPH reports per entity + consolidated; period presets |
| **3** | Persediaan roll-forward + neraca (historical) |
| **4** | Laba rugi, piutang aging, exports |

**Blocked until questions in `05-pre-implementation-questions.md` are answered.**
