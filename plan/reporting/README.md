# Reporting & Accounting — Design Set

Brainstorm and design for a real reporting subsystem in Aria Core: Indonesian tax reporting
(PPN / PPh), financial statements (neraca, laba rugi), and the inventory valuation needed to
make both of them true.

**Status: design only. No application code is changed by this branch.** The last document is
an implementation brief written to be handed to Composer.

## Documents

| # | Document | What it covers |
|---|---|---|
| 01 | [`01-current-state-and-gaps.md`](01-current-state-and-gaps.md) | What reporting exists today, what it actually computes, and the four structural defects that must be fixed before any new report can be trusted |
| 02 | [`02-tax-reporting-design.md`](02-tax-reporting-design.md) | PPN (faktur pajak, SPT Masa, DPP nilai lain), the `addrbooks.ppn` flag and its exposure, PPh 21/23 on borongan and gaji, PPh Badan / SPT 1771 |
| 03 | [`03-financial-statements-design.md`](03-financial-statements-design.md) | Turning signed balances into a chart of accounts, neraca, laba rugi, neraca saldo with an explicit suspense line, arus kas |
| 04 | [`04-inventory-and-cogs-design.md`](04-inventory-and-cogs-design.md) | Persediaan awal/akhir, the material-consumption problem, standard costing, HPP |
| 05 | [`05-data-model-additions.md`](05-data-model-additions.md) | Every new table, column and setting, with migration names |
| 06 | [`06-composer-implementation-plan.md`](06-composer-implementation-plan.md) | Phased, self-contained task briefs for Composer with acceptance criteria |

## TL;DR

**The data is there, but three things block reporting on it.**

1. **Cash movements carry no amount in the column every report reads.** `CreateCashTransaction`,
   `CreateTransferTransaction` and `CreateAdjustTransaction` write the amount to `grand_total`
   and leave `total` at its `0` default. Every money report (Nett Cash, Cash Flow, Pembelian,
   Laporan Biaya), the buku besar ledger, and both monthly summary tables sum `total`. So every
   cash-in, cash-out, transfer and adjust currently contributes **zero** to all of them. This is
   measured, not inferred — see `01`.

2. **There is no account classification.** Nothing says whether an addrbook is an asset, a
   liability, revenue or expense, so no statement can be assembled. The fix is a thin mapping
   layer over the existing `addrbooks` / `operations` tables rather than a new ledger.

3. **Inventory has quantity but no value.** Production receipts post at `price = 0`, `items.cost`
   is a static manual field, and nothing consumes raw material. Without a value, neraca has no
   Persediaan line and laba rugi has no HPP.

**The signed-balance convention works and should be the backbone.** Verified by running real
transactions through the real endpoints: negative balance = they owe us (piutang), positive =
we owe them (hutang), and bank/account balances read directly as cash held. That single rule
generates the whole receivable/payable side of the neraca with no double-entry rewrite.

**On tax, the sharpest issue is not a missing report — it is a missing distinction.** The app
treats a customer with `ppn = false` as *no PPN at all* (`tax_amount = 0`), not as a tax-inclusive
price. If the business is PKP, those sales are still penyerahan BKP and PPN is still owed. The
first thing to build is the report that sizes that exposure. See `02`.

## Recommended order

Foundation (fix the amount column, add the account mapping, add fiscal periods) → tax reports →
inventory valuation → neraca and laba rugi → managerial reports. Detail in `06`.

Phases 1 and 2 are useful on their own: the PPN reports and the aging/omzet reports only need the
foundation, not the valuation work.

## Decisions needed before Phase 1

These change the design and cannot be guessed from the codebase. Answers can be recorded directly
in `02-tax-reporting-design.md`.

1. **Is the business PKP, and since when?** Determines whether output VAT is an obligation or a
   customer-by-customer courtesy, and therefore what the non-PPN sales report means.
2. **Badan or orang pribadi?** Determines SPT Tahunan 1771 vs 1770, and whether PP 55/2022 final
   0.5% applies instead of the 22% corporate rate.
3. **Are the penjahit paid as `pegawai tidak tetap` (PPh 21 TER Harian) or invoiced as a business
   (PPh 23 jasa maklon 2%)?** Determines the withholding module and whether borongan needs
   NPWP/NIK per worker.
4. **How far back should reports go?** Determines whether the historical `transactions.total = 0`
   rows need a backfill command or only new rows are fixed.
5. **Do you want faktur pajak numbers stored in Aria Core, or does Coretax stay the system of
   record with Aria Core only producing the recap?** The recap-only option is much cheaper and is
   what Phase 1 assumes.
