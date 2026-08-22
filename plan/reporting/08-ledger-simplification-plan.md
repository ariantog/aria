# Ledger & Category Simplification Plan

> **Status:** Plan only — no code.  
> **Source data:** `database/customers.sql` (152 active ledgers, type=8) + `database/operations.sql` (22 categories).  
> **Context:** Maintainer confirmed reporting config belongs in a **separate table**, not flags on `addrbooks`. Entity/PKP/bank/channel mapping deferred to that reporting layer.

---

## 1. What we have today

| Metric | Count |
|--------|------:|
| Active categories (`operations`) | 22 |
| Active ledgers (`customers.type = 8`) | 152 |
| Already soft-deleted ledgers | 49 |
| Ledgers with no `parent_id` | 0 |

### Main problems

1. **Channel costs are scattered** across 5 categories: Marketing (13), Operational Luar (5), Non-Operational (1), Logistik (2), Sewa (3).
2. **Production costs split** between Gaji dan Upah (14) and Ongkos Produksi (6), plus Jahit Luar under Jasa Profesional.
3. **Entity-specific tax ledgers** (17 accounts: PPH Crystal, SPT CORE, PPN PT INDOSPORT, etc.) belong in **reporting**, not the chart of accounts.
4. **Catch-all categories** overlap: General, Lain-lain, Non-Operational, Operational Luar, Biaya Lain2, Biaya Operational.
5. **Misclassified**: Pendapatan FitBox SBY under Jasa Profesional (revenue in expense tree).

---

## 2. Design principle: separate concerns

```
addrbooks (type=Account)     →  simplified ledger names + one category (operation)
operations                   →  simplified category tree (soft-delete old ones)
reporting_* tables           →  entity, PKP, bank↔channel, tax lines, material flags
transactions                 →  unchanged; CashOut points at ledger id
```

**Do not** put entity name, PKP, or channel bank on `addrbooks` as reporting switches.

---

## 3. Proposed simplified categories (operations)

Reduce **22 → 15** active categories. Split digital marketplace costs from physical **toko** (shop) costs.

| # | New name | Slug | Purpose |
|---|----------|------|---------|
| 1 | **Biaya Marketplace** | `marketplace` | Online channel fees — compare Shopee vs TikTok vs Lazada cost |
| 2 | **Biaya Toko** | `toko` | Physical shop upkeep — WTC, Citos (rent + transport + misc in one ledger per shop) |
| 3 | **Marketing Umum** | `marketing` | Non-channel marketing: iklan, pameran, sponsor, CSR |
| 4 | **Gaji & Upah** | `gaji` | Payroll |
| 5 | **Produksi** | `produksi` | Material, biaya produksi, permak |
| 6 | **Logistik** | `logistik` | HQ/generic shipping, bensin, tol (not shop-specific) |
| 7 | **Kantor & Utilitas** | `kantor` | Office + utilities + subscriptions |
| 8 | **Perawatan & Mesin** | `maintenance` | Repair & maintenance |
| 9 | **Jasa Profesional** | `jasa` | Konsultan, print |
| 10 | **Kesejahteraan Karyawan** | `sdm` | Staff welfare |
| 11 | **Pajak & Retribusi** | `pajak` | Generic tax only (SSP, PBB, etc.) |
| 12 | **Perbankan** | `bank` | Bank fees |
| 13 | **Penyesuaian** | `penyesuaian` | Adjustments |
| 14 | **Lain-lain** | `lain` | Donasi, entertain — minimal |
| 15 | **Sewa HQ** | `sewa` | **Optional:** HQ/building rent only (Gedung Cost) — see §4.1 |

**Drop old category "Sewa Menyewa" per-shop ledgers** — folded into Biaya Toko (see maintainer note §9C).

### Categories to **soft-delete** (after migrating child ledgers)

| Old ID | Name | Action |
|--------|------|--------|
| 28 | Operational Luar | Marketplace + Toko split |
| 7 | Sewa Menyewa | Per-shop sewa → Biaya Toko; optional HQ-only sewa remains |
| 25 | General | Dissolve |
| 24 | Non-Operational | Split per ledger table |
| 11 | Research & Development | CleanEat → Marketing Digital; R&D → Marketing |
| 15 | Jasa Training | Biaya HRD → SDM |
| 26 | Sumbangan | → Lain-lain |
| 19 | Entertain | → Lain-lain |
| 22 | Lain-lain (old) | Replace |
| 3 | Marketing (old) | Split → Marketplace vs Marketing Umum |

**Keep op 18 (Pajak)** but gut entity-specific children — see §5.

---

## 4. Proposed simplified ledger names

### 4.1 Biaya Toko vs Biaya Marketplace (key split)

**Physical shops (rented outside HQ)** — one ledger per shop for **all upkeep** (rent, transport, utilities, misc). Staff use transaction **notes** for detail (e.g. "sewa Juli", "ojek sample").

| New name | Keep ID | Absorbs | Description (for autocomplete) |
|----------|--------:|---------|-------------------------------|
| **Biaya Toko WTC** | 2889 | 2184 WTC Transport Cost | All WTC shop costs: rent, transport, utilities, supplies. Staff at WTC post here. |
| **Biaya Toko Citos** | 2842 | 2854 FX Cost, 2844 Biaya Sewa Citos | All Citos shop costs including rent. |

**Soft-delete after merge:** 2184, 2854, 2844 (separate sewa ledgers — too tedious per maintainer)

**HQ / other rent:** 830 Biaya Sewa Sambisari → keep under **Sewa HQ** only if still paid separately; else fold into Kantor. 2959 Gedung Cost → Sewa HQ.

**Why not separate Sewa per store?** Maintainers found `Biaya Sewa {toko}` tedious — every payment needs the right ledger. One **Biaya Toko {name}** ledger + notes is simpler and still gives WTC vs Citos totals in reports.

---

### 4.2 Biaya Marketplace (~20 ledgers — compare channel costs)

Online / partner channel fees. Name pattern: **`Biaya {Channel}`**.

| New name | Keep ID | Absorbs |
|----------|--------:|---------|
| Biaya Shopee | 2234 | |
| Biaya TikTok | 2788 | |
| Biaya Lazada | 2881 | |
| Biaya Tokopedia | 2273 | rename Toped Cost |
| Biaya BSD | 2899 | |
| Biaya Metro | 2099 | offline partner? keep for comparison |
| Biaya Sogo | 2178 | |
| Biaya Central | 2633 | |
| Biaya FitBox | 2719 | absorb 2729 FitBox JKT |
| Biaya MUKU | 2957 | |
| Biaya AF | 2963 | |
| Biaya Prop | 2964 | |
| Biaya Marketing Digital | **new** | 2250 Social Media, 2640 Collab, 2691 Rangers, 2070 Counter, 2724 CleanEat |

**Not marketplace** (moved to Toko): WTC Cost, Citos Cost, WTC Transport.

Reports can show: "which marketplace costs us most" (Shopee vs TikTok…) separately from "WTC shop vs Citos shop upkeep".

**Soft-delete after migration:** 2729, 2250, 2640, 2691, 2070, 2724

| New name | Keep ID | Notes |
|----------|--------:|-------|
| Biaya Iklan | 838 | |
| Biaya Pameran | 833 | |
| Biaya Sponsor | 832 | |
| Biaya Katalog | 834 | |
| Biaya Banner | 839 | |
| Biaya Promosi | 901 | |
| Biaya CSR | 904 | |
| Biaya Perjalanan Marketing | 835 | |
| Biaya Model | 837 | |
| Biaya Fotografi | 2149 | rename from "Fotografi" |
| Biaya Packing | 896 | |
| Cashback | 995 | |

**Move to reporting table (soft-delete ledger):** 2809 PEMBAYARAN PPN - PT CORE, 2862 PPH INDOSPORT

**Soft-delete vague/duplicate:** 2814 Konsultan Pak Dian → Jasa; 2835 Operational Lain2 → Lain

### 4.3 Marketing Umum (~28 → ~12 ledgers)

### 4.4 Gaji & Upah (14 → 5 ledgers)

| New name | Keep ID | Absorbs |
|----------|--------:|---------|
| Gaji Bulanan | 822 | |
| Gaji Mingguan | 2696 | **reporting production cost** |
| Gaji Outsourcing | 814 | Outsource + 909 Helper + 899 Jahit Luar + 825 Finishing |
| Bonus & Insentif | 905 | 820 Insentif + 937 Lembur + 813 THR |
| Gaji Lain | **new** | 816 SPG + 821 Pembantu + 2509 Guru + 903 Pesangon |

**Soft-delete (unused / merged):** 817 Gaji Harian (**not used**), 909, 899, 825, 820, 937, 813, 816, 821, 2509, 903

### 4.5 Produksi (6 → 3 ledgers)

| New name | Keep ID | Notes |
|----------|--------:|-------|
| Material Produksi | 1558 | pembelian bahan / persediaan |
| Biaya Produksi | 2846 | non-quantifiable production |
| Perlengkapan Produksi | 2799 | absorb 2800 Mesin Pelengkap, 863 Aksesoris Mesin |
| Permak | 885 | move from Non-Operational |

**Soft-delete:** 1644 Plotter (obsolete machine), 2818 Pengeluaran PT CORE → reporting

Entity-specific PPN/PPH/SPT moves to **`reporting_tax_lines`** (or similar). Keep only generic:

| Keep | ID | Name |
|------|---:|------|
| ✓ | 857 | SSP |
| ✓ | 1168 | PBB |
| ✓ | 856 | Pajak Kendaraan |
| ✓ | 2364 | Cukai |
| ✓ | 2405 | Pajak Bunga |
| ✓ | 2841 | Pajak Sewa |

**Soft-delete (17 accounts) → reporting table:**

2106 PPH Crystal, 2797 SPT PRIBADI, 2802 PPN, 2805 PPN PT CORE, 2806 SPT PT CORE, 2808 PENYESUAIAN PPN - PT CORE, 2849 PPN PT INDOSPORT, 2861 PPH CIPTA, 2863 PPH PT CAKRA, 2865 PPH Pribadi, 2883 SPT CRYSTAL, 2884 SPT CIPTA, 2885 SPT INDOSPORT, 2896 PPH CV CAKRA, 2941 PPH AGM, 2944 PPH UAI

Historical CashOut to deleted tax ledgers: keep transaction history; map old id → reporting dimension via migration mapping table.

### 4.6 Pajak — gut entity ledgers (22 → 6 ledgers)

| New name | Keep ID |
|----------|--------:|
| Penyesuaian Umum | 880 |
| Pembulatan | 2252 |
| Kembalian | 2857 |
| Penghapusan Hutang | 879 |

**Soft-delete:** 2938 TF Misteri (investigate first), 891 Biaya Operational, 900 Biaya Lain2

**`reporting_tax_accounts`** maps deleted ledger id → entity + tax_type. Entities from legacy names:

| Entity slug | From legacy ledgers |
|-------------|---------------------|
| `cv-crystal` | PPH Crystal, SPT CRYSTAL |
| `cv-cipta` | PPH CIPTA, SPT CIPTA |
| `pt-core` | PPN/SPT/PENYESUAIAN PT CORE, PEMBAYARAN PPN |
| `pt-indosport` | PPN PT INDOSPORT, SPT INDOSPORT, PPH INDOSPORT |
| `cv-cakra` | PPH PT CAKRA, PPH CV CAKRA |
| `agm` | PPH AGM |
| `uai` | PPH UAI |
| `pribadi` | PPH Pribadi, SPT PRIBADI |

Historical CashOut auto-attributes to correct entity in reports (locked §9E).

### 4.7 Penyesuaian (new category)

Keep most accounts, standardise prefix **`Biaya `** where missing. Examples:

- 858 Ongkos Kirim → Biaya Ongkir
- 855 Biaya Bensin (ok)
- 873 Perijinan (ok)

### 4.8 Other categories — light rename only

| ID | Current | Action |
|----|---------|--------|
| 2731 | Pendapatan FitBox SBY | **Soft-delete** — revenue should not be expense ledger; track via Sell/CashIn to customer |

### 4.9 Misclassified — fix

Replace entity flags on addrbook with:

### `reporting_entities`
`id, name, slug, is_pkp, npwp, modal, laba_ditahan_awal, is_active`

### `reporting_entity_banks`
`entity_id, bank_addrbook_id` — PKP derived from entity, not bank column

### `reporting_channel_banks`
`customer_addrbook_id, bank_addrbook_id` — marketplace customer → payment bank (C1)

### `reporting_ledger_roles`
`addrbook_id, role` — enum: `material`, `production_cost`, `marketplace_cost`, `toko_cost`, `tax_payment`, `adjustment`, `exclude`

### Ledger descriptions in Cash In/Out UI (locked §9D)

Legacy `customers.description` is mostly empty. During migration:

1. Populate `addrbooks.description` for every active ledger (one-line purpose + hint).
2. Cash transaction autocomplete (`transactions/cash.blade.php`): show **name** + **description** subtitle under selected ledger.
3. Optional: `reporting_ledger_roles.hint` for longer help text.

Example autocomplete row:
```
Biaya Toko WTC
Semua biaya toko WTC: sewa, transport, utilitas. Isi catatan untuk detail.
```

---

## 5. Reporting tables (separate from addrbook)
Cutover date `2025-01-01` (omit 2024), persediaan awal Jan 2026, etc.

---

## 6. Soft-delete policy

1. **Never hard-delete** ledgers with transaction history.
2. Set `addrbooks.deleted_at` (already supported on Operation + Addrbook).
3. Before soft-delete: **merge balances** via Adjust transaction if non-zero (or leave mapping for reports to aggregate old+new ids).
4. Maintain `ledger_merge_map(old_id, new_id)` for report queries during transition.

### Summary counts (moderate simplification)

| Action | ~Count |
|--------|-------:|
| Categories soft-deleted | 8 |
| Categories active (new/kept) | 15 |
| Ledgers kept (renamed) | ~93 |
| Ledgers soft-deleted (incl. tax, plotter, gaji harian) | ~38 |
| New ledgers to create | ~3 (Biaya Marketing Digital, Gaji Lain, optional merges) |

---

## 7. Migration sequence (when coding — not now)

```
Step 1  Create reporting_* tables
Step 2  Seed reporting_entities + channel→bank mappings (manual UI)
Step 3  Create new operations (categories) with report slugs
Step 4  Rename ledgers + re-parent operation_id
Step 5  Merge ledger transaction history pointers (merge_map)
Step 6  Soft-delete obsolete operations + ledgers
Step 7  Populate reporting_ledger_roles
Step 8  Verify expense report totals match pre-migration (2025 sample month)
```

---

## 8. Reporting impact (locked from prior answers)

| Topic | Rule |
|-------|------|
| Output tax | CashIn **payment date**; non-PKP entity bank → PPH Final 0.5% |
| Production cost | **Gaji Mingguan** ledger only (not borongan table) |
| Material | Material Produksi + Buy from suppliers |
| Persediaan start | January 2026 |
| Data cutover | Omit 2024 |
| Internal lending | Flag in reporting table as you go |

---

## 9. Locked decisions (Aug 2026)

| # | Decision |
|---|----------|
| A | **Moderate** — keep per-marketplace ledgers to compare which channels cost most |
| B | **Plotter (1644)** — soft-delete (obsolete machine) |
| C | **Sewa vs Toko** — drop per-shop sewa ledgers; use **Biaya Toko WTC/Citos** for all shop costs incl. rent; notes for detail |
| D | **Ledger UX** — populate descriptions; show in Cash In/Out autocomplete under selected ledger |
| E | **Tax history** — auto-attribute deleted entity tax ledgers to CV CRYSTAL, CV CIPTA, PT CORE, INDOSPORT, CAKRA, AGM, UAI, PRIBADI |
| F | **49 deleted ledgers** — leave in DB (may have related transactions) |
| — | **Gaji Harian (817)** — not used; soft-delete |

---

## 10. Next step
