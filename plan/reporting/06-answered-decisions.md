# Locked Decisions (maintainer answers)

## Tax

| # | Decision |
|---|----------|
| B1 | **Payment date** (`CashIn.date`) drives output tax recognition (faktur date can differ) |
| B2 | **All CashIn** to non-PKP entity banks → **PPH Final 0.5%**, no PPN |
| B3 | Configure via **addrbook edit** (bank → entity; entity has PKP flag) |
| NPWP | **Optional** on customer/supplier/reseller |
| Returns | Mirror original tax treatment; contact `ppn` = "requires faktur / PKP counterparty" for masukan |

## Persediaan

| # | Decision |
|---|----------|
| D1 | **Gaji Mingguan** ledger (account 2696) = actual production cost; **borongan table is NOT used** in reports (calculation only) |
| D2 | Roll-forward starts **January 2026** |
| Formula | Awal + Buy (materials) − Sell COGS − CashOut to Gaji Mingguan / material accounts |

## Neraca / ekuitas

| # | Decision |
|---|----------|
| E1 | Modal: manual **or** guesstimate from **2025 year-end data** (helper command) |
| E2 | Laba ditahan: same approach — guesstimate from 2025 if possible |
| Period | Calendar month; views: 1 / 3 / 6 / 12 months |
| As-of | **Historical month-end** (balance snapshots required) |

## Data & channels

| # | Decision |
|---|----------|
| C1 | Marketplace channels → **fixed bank** per customer contact (already separate Shopee/TokTok entries) |
| F1 | **Omit 2024** from reporting cutover |
| F2 | Customer investment/lending = **internal only** (exclude from consolidated revenue or flag separately) |

## Implementation order

**Phase 0 (first):** Addrbook edit page + company entities + mappings — **before any report code**.
