# Pre-Implementation Questions

Answer these before Composer starts Phase 0. Defaults proposed where reasonable.

---

## A. Company entities & banks

### A1. How many entities do you operate today?

Draft count from bank names: **4–5** (Crystal, Core, Indosport, Citos/BSD, plus non-operating).

- [ ] Confirm entity names
- [ ] Mark each as **PKP** or **non-PKP**

### A2. Bank → entity assignment

Please confirm or correct this draft:

| Bank ID | Name | Entity | PKP? | Active in reports? |
|---------|------|--------|------|-------------------|
| 1158 | BCA CRYSTAL | ? | ? | ? |
| 2821 | BCA CIPTA | ? | ? | ? |
| 2929 | BCA AGM | ? | ? | ? |
| 2704 | BCA 510 | ? | ? | ? |
| 2853 | BCA INDOSPORT | ? | ? | ? |
| 2940 | BCA UAI | ? | ? | ? |
| 2856 | Kas Kecil Citos | ? | ? | ? |
| 2893 | BCA 9889 | ? | ? | ? |
| 263 | Kas Kecil Sambisari | ? | ? | ? |
| 452 | Kas Kecil WTC | ? | ? | ? |
| 2793 | Investment | Exclude? | — | — |
| 2930 | Transfer Pending | Exclude? | — | — |
| 300 | Hutang Direksi | Liability not cash? | — | — |

### A3. Inactive banks

Should inactive banks be hidden from reports but still show in transaction history? **(recommended: yes)**

---

## B. Tax rules (PPN / PPH Final)

### B1. When is output tax recognized?

Credit sales exist (Sell today, CashIn weeks later). Which date drives PPN keluaran?

- **(a)** Invoice date (`Sell.date`) — accrual, standard PKP
- **(b)** Payment date (`CashIn.date`) — cash basis per your bank model
- **(c)** Hybrid: accrue on Sell for PKP entities, but only when customer pays to PKP bank

**Your bank-centric model suggests (b) or (c).** Which one?

### B2. PPH Final 0.5%

- Apply to **all** CashIn into non-PKP entity banks?
- Or only specific channels (Shopee, Tokopedia, TikTok customers)?
- Base amount: gross CashIn total?

### B3. PPN on Buy / masukan

- Keep **supplier.ppn flag** as gate for masukan? **(only 2 suppliers flagged today)**
- Should masukan also be split per entity based on **which bank paid** (CashOut sender)?

### B4. Sell transaction PPN fields today

Current code sets `tax_amount` on Sell from customer `ppn` flag (almost always false).

**Revamp options:**

- **(a)** Stop calculating PPN on Sell; compute only at report time from CashIn + entity
- **(b)** Keep Sell PPN for PKP customers; bank entity overrides for marketplaces
- **(c)** Add `company_entity_id` on Sell at entry time (user picks entity)

### B5. Returns

When retur penjualan: inherit tax from original Sell's entity + PKP status? **(recommended: yes, link by invoice_number)**

---

## C. Cash & ledger categorization

### C1. Report category taxonomy

Use **operation name** as category, or a separate higher-level grouping?

Draft groups:

| Category slug | Operations (op id) |
|---------------|-------------------|
| `marketing` | 3 |
| `gaji` | 4 |
| `sewa` | 7 |
| `kantor` | 8, 9, 16 |
| `transport` | 17 |
| `pajak` | 18 |
| `maintenance` | 13 |
| `produksi` | 14 + orphans (Material Produksi, Gaji Mingguan) |
| `pemasukan_lain` | 22 |

OK to start with this mapping?

### C2. Orphan accounts (23 without operation_id)

- Auto-assign to categories in migration?
- Or leave for manual cleanup in UI?

Notable orphans: `Material Produksi`, `Gaji Mingguan` (wait - 2696 has op 4), `Shopee Cost`, `BSD Cost`, `WTC Cost`, `Pengeluaran PT CORE`.

### C3. CashIn from marketplace customers

Customers like "Shopee - CRYSTAL Customer" (2224) — is revenue always tied to a specific bank per channel?

e.g. Shopee → BCA CRYSTAL, Shopee Core → BCA AGM?

If yes, we can **default entity from customer name** when CashIn bank is ambiguous.

---

## D. Persediaan

### D1. Material purchase sources (confirm)

Proposed formula:

```
+ Buy transactions (all suppliers, or only fabric suppliers?)
+ CashOut to accounts: [1558 Material Produksi, 814 Biaya Outsource]
− Sell COGS (qty × item.cost)
− Borongan.total (period overlap)
− CashOut to Gaji Mingguan ledger (2696) — or is borongan table enough?
```

**Question:** Count borongan table AND Gaji Mingguan ledger, or only one (they may duplicate)?

### D2. Per-entity persediaan

Single company-wide persediaan, or per entity? **(recommend: company-wide v1)**

### D3. Opening persediaan awal

What is a reasonable seed value + effective month to start roll-forward?

---

## E. Neraca & ekuitas

### E1. Modal per entity

Please provide approximate modal per entity (can be rough), or use plug for v1.

### E2. Laba ditahan

- **(a)** Manual setting "retained earnings at 2026-01-01" per entity
- **(b)** Pure plug (system calculates)
- **(c)** Roll forward from monthly laba rugi once that report exists

**Recommendation:** (a) for opening + (c) going forward.

### E3. Historical snapshots

OK to backfill month-end snapshots from transaction replay (slow once) for last 12–24 months?

---

## F. Data quality

### F1. Legacy inaccuracy

You noted old supplier data is unreliable. For reports:

- Add `reporting.cutover_date` — only trust transactions after this date?
- Or show all data with a warning banner?

### F2. Consolidated vs entity

Inter-company transfers (Transfer between banks of different entities) — treat as internal or exclude?

---

## G. Priority confirmation

Suggested build order:

1. Entity + bank setup UI
2. Revamp aggregate job
3. PPN/PPH report per entity
4. Persediaan + neraca
5. Laba rugi

Agree?
