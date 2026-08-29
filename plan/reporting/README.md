# Reporting System — Planning Hub

Status: **Phase 3 done** on `main` (tax 1a–1d, neraca `#476`, laba rugi + aging `#481`).

Next: **[Phase 4 kickoff](./10-phase4-kickoff.md)** — Excel pack, PPh Final, mapping UI.

## Production deploy (schema only)

Do **not** rely on bare `php artisan migrate` on production. Run the guarded install script:

```bash
php artisan migrate --path=database/migrations/2026_08_22_110000_install_reporting_tables.php --force
php artisan db:seed --class=ReportingBootstrapSeeder
php artisan reporting:apply-ledger-plan --dry-run
php artisan reporting:apply-ledger-plan
```

The install migration is idempotent (`hasTable` / `hasColumn`), uses `INT(11)` for all `customers` references (no BIGINT), and adds `DEFAULT` values on new NOT NULL customer columns.

**Revenue / tax entity:** derived from the **receiver bank on each Cash In** (`reporting_entity_banks`), not a per-customer default bank.

## Documents

| File | Purpose |
|------|---------|
| [01-brainstorm.md](./01-brainstorm.md) | What we need, data gaps, Indonesian tax & neraca concepts |
| [02-data-model.md](./02-data-model.md) | How existing tables map to reports; new tables/settings needed |
| [03-composer-implementation-plan.md](./03-composer-implementation-plan.md) | Phased build plan for Composer agent (v1 — superseded in parts) |
| [04-revised-architecture-v2.md](./04-revised-architecture-v2.md) | **Current** architecture: entities, bank-PKP tax, cash categories |
| [05-pre-implementation-questions.md](./05-pre-implementation-questions.md) | Answered — see 06 |
| [06-answered-decisions.md](./06-answered-decisions.md) | Locked maintainer decisions |
| [07-phase0-addrbook-mapping.md](./07-phase0-addrbook-mapping.md) | Superseded in part by 08 |
| [08-ledger-simplification-plan.md](./08-ledger-simplification-plan.md) | Ledger/category simplification (mostly applied) |
| [09-phase3-kickoff.md](./09-phase3-kickoff.md) | Phase 3 kickoff — Laba Rugi + aging (**shipped** `#481`) |
| [10-phase4-kickoff.md](./10-phase4-kickoff.md) | **Paste-ready Phase 4 kickoff** — Excel, PPh Final, mapping UI |

## Context (read first)

Aria is **not** double-entry accounting. Balances on `addrbook_stats` are **signed running totals**:

- **Negative balance** → the contact owes **us** (piutang / receivable)
- **Positive balance** → **we** owe the contact (hutang / payable)

Transaction `grand_total` signs: Buy/Return/CashIn positive; Sell/ReturnSupplier/CashOut negative.

PPN is already calculated at transaction time (`tax_amount`, `grand_total`) and gated by `addrbooks.ppn` (supplier on Buy, customer on Sell). Rate from `settings.ppn_rate` (default 11%).

Operational reports (`/reports/*` cash flow, purchases, warehouse valuation) still aggregate `transaction.total` (pre-tax). Tax lives at `/reports/tax/ppn` and `/reports/tax/faktur`. Neraca is `/reports/neraca`. Laba rugi / piutang / hutang are `/reports/laba-rugi`, `/reports/receivables`, `/reports/payables` (CSV only). Excel, PPh Final, and mapping UI are next — see [10-phase4-kickoff.md](./10-phase4-kickoff.md).
