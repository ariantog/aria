# Reporting System — Planning Hub

Branch: `cursor/reporting-d945`  
Status: **Planning only** — no implementation yet.

## Documents

| File | Purpose |
|------|---------|
| [01-brainstorm.md](./01-brainstorm.md) | What we need, data gaps, Indonesian tax & neraca concepts |
| [02-data-model.md](./02-data-model.md) | How existing tables map to reports; new tables/settings needed |
| [03-composer-implementation-plan.md](./03-composer-implementation-plan.md) | Phased build plan for Composer agent (v1 — superseded in parts) |
| [04-revised-architecture-v2.md](./04-revised-architecture-v2.md) | **Current** architecture: entities, bank-PKP tax, cash categories |
| [05-pre-implementation-questions.md](./05-pre-implementation-questions.md) | **Answer before coding** |

## Context (read first)

Aria is **not** double-entry accounting. Balances on `addrbook_stats` are **signed running totals**:

- **Negative balance** → the contact owes **us** (piutang / receivable)
- **Positive balance** → **we** owe the contact (hutang / payable)

Transaction `grand_total` signs: Buy/Return/CashIn positive; Sell/ReturnSupplier/CashOut negative.

PPN is already calculated at transaction time (`tax_amount`, `grand_total`) and gated by `addrbooks.ppn` (supplier on Buy, customer on Sell). Rate from `settings.ppn_rate` (default 11%).

Current reports (`/reports/*`) are operational (cash flow, purchases, warehouse valuation) — they aggregate `transaction.total` (pre-tax subtotal), **not** `tax_amount`. No tax or financial-statement reports exist.
