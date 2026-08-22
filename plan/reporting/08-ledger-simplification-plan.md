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

Reduce **22 → 14** active categories. Old categories soft-deleted after ledger migration.

| # | New name | Slug | Absorbs old operation(s) |
|---|----------|------|--------------------------|
| 1 | **Biaya Channel** | `channel` | Marketing channel costs, Operational Luar, Shopee Cost, scattered `* Cost` |
| 2 | **Marketing Umum** | `marketing` | Marketing non-channel: iklan, pameran, sponsor, CSR, packing promosi |
| 3 | **Gaji & Upah** | `gaji` | Gaji dan Upah (op 4) |
| 4 | **Produksi** | `produksi` | Ongkos Produksi (op 27) + Permak + Jahit Luar |
| 5 | **Sewa** | `sewa` | Sewa Menyewa — **rent only**, not store operating costs |
| 6 | **Logistik** | `logistik` | Ongkir, bensin, tol, kendaraan (not channel-specific) |
| 7 | **Kantor & Utilitas** | `kantor` | Perlengkapan Kantor + Utilitas + Biaya Langganan |
| 8 | **Perawatan & Mesin** | `maintenance` | Repair & Maintenance (op 13) |
| 9 | **Jasa Profesional** | `jasa` | Konsultan, print, personal (no revenue accounts) |
| 10 | **Kesejahteraan Karyawan** | `sdm` | Biaya Karyawan (op 21) |
| 11 | **Pajak & Retribusi** | `pajak` | Generic pajak only — **not** entity-specific PPN/PPH/SPT |
| 12 | **Perbankan** | `bank` | Perbankan (op 10) |
| 13 | **Penyesuaian** | `penyesuaian` | Penyesuaian, pembulatan, kembalian, penghapusan hutang |
| 14 | **Lain-lain** | `lain` | Donasi, sumbangan, entertain — minimal use |

### Categories to **soft-delete** (after migrating child ledgers)

| Old ID | Name | Action |
|--------|------|--------|
| 28 | Operational Luar | Merge all into **Biaya Channel** |
| 25 | General | Dissolve (see ledger table) |
| 24 | Non-Operational | Split: Shopee→Channel; Penyesuaian→Penyesuaian; Permak→Produksi; rest→Lain |
| 11 | Research & Development | CleanEat Cost → Channel; R&D → Marketing Umum |
| 15 | Jasa Training | Biaya HRD → SDM |
| 26 | Sumbangan | → Lain-lain |
| 19 | Entertain | → Lain-lain (or keep if you use it often) |
| 22 | Lain-lain (old) | Replace with new minimal **Lain-lain** |
| 12 | Asuransi | → Kantor (single account) or keep as 15th category |

**Keep op 18 (Pajak)** but gut entity-specific children — see §5.

---

## 4. Proposed simplified ledger names

### 4.1 Biaya Channel (consolidate ~25 → ~15 ledgers)

One ledger per sales channel / location cost centre. Rename for consistency: **`Biaya {Channel}`**.

| New name | Keep ID | Absorbs / soft-delete |
|----------|--------:|------------------------|
| Biaya Shopee | 2234 | (move from Non-Operational) |
| Biaya TikTok | 2788 | |
| Biaya Lazada | 2881 | |
| Biaya Tokopedia | 2273 | rename from "Toped Cost" |
| Biaya WTC | 2889 | absorb 2184 WTC Transport Cost |
| Biaya BSD | 2899 | |
| Biaya Citos | 2842 | absorb 2854 FX Cost |
| Biaya Metro | 2099 | rename "Metro Costs" |
| Biaya Sogo | 2178 | |
| Biaya Central | 2633 | rename "Central - Cost" |
| Biaya FitBox | 2719 | absorb 2729 FitBox JKT Cost |
| Biaya MUKU | 2957 | |
| Biaya AF | 2963 | |
| Biaya Prop | 2964 | |
| Biaya Marketing Digital | **new** | absorb 2250 Social Media, 2640 Collab, 2691 Rangers, 2070 Counter, 2724 CleanEat |

**Soft-delete after migration:** 2184, 2729, 2854, 2250, 2640, 2691, 2070, 2724

**Sewa stays separate:** 2844 Biaya Sewa Citos, 830 Biaya Sewa Sambisari, 2959 Gedung Cost → category **Sewa** (not channel).

### 4.2 Marketing Umum (~28 → ~12 ledgers)

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

### 4.3 Gaji & Upah (14 → 6 ledgers)

| New name | Keep ID | Absorbs |
|----------|--------:|---------|
| Gaji Bulanan | 822 | |
| Gaji Mingguan | 2696 | **reporting production cost** |
| Gaji Harian | 817 | |
| Gaji Outsourcing | 814 | rename Biaya Outsource; absorb 909 Helper, 899 Jahit Luar, 825 Finishing |
| Bonus & Insentif | 905 | absorb 820 Insentif, 937 Lembur, 813 THR |
| Gaji Lain | **new** | absorb 816 SPG, 821 Pembantu, 2509 Guru, 903 Pesangon |

**Soft-delete:** 909, 899, 825, 820, 937, 813, 816, 821, 2509, 903 (after balance migration to targets)

### 4.4 Produksi (6 → 4 ledgers)

| New name | Keep ID | Notes |
|----------|--------:|-------|
| Material Produksi | 1558 | pembelian bahan / persediaan |
| Biaya Produksi | 2846 | non-quantifiable production |
| Perlengkapan Produksi | 2799 | rename; absorb 2800 Mesin Pelengkap, 863 Aksesoris Mesin |
| Permak | 885 | move from Non-Operational |

**Move to reporting / soft-delete:** 2818 Pengeluaran PT CORE (entity-specific → reporting)

**Soft-delete / merge:** 1644 Plotter → Perlengkapan Produksi or Maintenance (your call)

### 4.5 Pajak — gut entity ledgers (22 → 6 ledgers)

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

### 4.6 Penyesuaian (new category)

| New name | Keep ID |
|----------|--------:|
| Penyesuaian Umum | 880 |
| Pembulatan | 2252 |
| Kembalian | 2857 |
| Penghapusan Hutang | 879 |

**Soft-delete:** 2938 TF Misteri (investigate first), 891 Biaya Operational, 900 Biaya Lain2

### 4.7 Other categories — light rename only

Keep most accounts, standardise prefix **`Biaya `** where missing. Examples:

- 858 Ongkos Kirim → Biaya Ongkir
- 855 Biaya Bensin (ok)
- 873 Perijinan (ok)

### 4.8 Misclassified — fix

| ID | Current | Action |
|----|---------|--------|
| 2731 | Pendapatan FitBox SBY | **Soft-delete** — revenue should not be expense ledger; track via Sell/CashIn to customer |

---

## 5. Reporting tables (separate from addrbook)

Replace entity flags on addrbook with:

### `reporting_entities`
`id, name, slug, is_pkp, npwp, modal, laba_ditahan_awal, is_active`

### `reporting_entity_banks`
`entity_id, bank_addrbook_id` — PKP derived from entity, not bank column

### `reporting_channel_banks`
`customer_addrbook_id, bank_addrbook_id` — marketplace customer → payment bank (C1)

### `reporting_ledger_roles`
`addrbook_id, role` — enum: `material`, `production_cost`, `channel_cost`, `tax_payment`, `adjustment`, `exclude`

Example: 1558 → `material`; 2696 → `production_cost`; 2234 → `channel_cost`

### `reporting_tax_accounts` (optional)
Maps old deleted tax ledger ids to `entity_id` + `tax_type` for historical transaction interpretation.

### `reporting_settings`
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
| Categories active (new/kept) | 14 |
| Ledgers kept (renamed) | ~95 |
| Ledgers soft-deleted (incl. tax) | ~35 |
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

## 9. Decisions needed from you before coding

### A. Simplification level

- **Moderate** (this plan): ~95 ledgers, 14 categories  
- **Aggressive**: collapse all channel costs into one "Biaya Marketplace" + one "Biaya Offline" — fewer names, less detail  

Which do you prefer?

### B. Plotter (1644)

Maintenance asset or Produksi consumable?

### C. Sewa vs Channel

Confirm: **Citos Cost / FX Cost** = channel operating cost (Biaya Citos), while **Biaya Sewa Citos** = rent (Sewa)?

### D. Gaji consolidation

OK to merge SPG/Pembantu/Guru/Pesangon into **Gaji Lain**, or keep separate?

### E. Tax ledger history

When we soft-delete PPN PT CORE etc., OK to show historical amounts under reporting entity "PT CORE" automatically (no manual re-entry)?

### F. Already-deleted 49 ledgers

Leave as-is in DB, or purge from import when syncing `customers.sql` → `addrbooks`?

---

## 10. Next step after approval

1. You confirm §9 decisions  
2. Composer implements **reporting tables + ledger/category migration** (not tax reports yet)  
3. You map channel→bank in reporting UI  
4. Then aggregate job + PPN reports  

**No addrbook PKP/entity flags.** All tax entity logic lives in `reporting_*`.
