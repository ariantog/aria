# 01 — Current State and Gaps

What the reporting layer does today, what it actually computes, and the defects that have to be
fixed before any new report can be believed.

---

## 1. What exists

### Report pages (`/reports/*`)

| Route | Controller | Source | Notes |
|---|---|---|---|
| `reports.nett-cash-sby` | `Reports\NettCashController` | live `transactions` | cash/sell/return per customer, reseller, bank |
| `reports.cash-flow` | `CashFlowController` | live `transactions` | flows grouped by addrbook type |
| `reports.purchase` | `Reports\PurchaseReportController` | live `transactions` | supplier buy / return / cash |
| `reports.expense` | `Reports\ExpenseReportController` | live `transactions` | account ↔ bank movements only |
| `reports.warehouse-item` | `Reports\WarehouseItemReportController` | `warehouse_items` | qty and a rough cost per warehouse |
| `reports.compare` | `Reports\CompareReportController` | `warehouse_items` | stock matrix across chosen warehouses |
| `reports.item-sales` | `Reports\ItemSaleReportController` | `stat_sells` | monthly sales by item group and customer |
| `reports.inventory-health` | `ReportController@inventoryHealth` | live correlated subqueries | fast/slow/dead classification |
| `reports.stock-intelligence` | `ReportController@stockIntelligence` | `stok_reports` + `stock_data` | scored snapshots |
| `reports.rebalance-detail` | `ReportController@rebalanceDetail` | live | inter-warehouse move suggestion |

Plus entity-level pages: `items.stats`, `items.group-stats`, `addrbook.stats`,
`addrbook.item-sales`, and the journal module (`journals/operations`,
`journals/account-list`, `journals/account-list/{id}/ledger`).

### What is genuinely absent

No neraca, no laba rugi, no neraca saldo, no arus kas, no jurnal umum, no aging, no stock card,
no COGS or margin report, no fixed-asset or depreciation register, and **no tax report of any
kind**. There is also no export from any report page — the only Excel writer in the codebase is
`Services/Restock/RestockSheetExportService`.

### Three aggregation strategies coexist

Live queries, incremental summary tables, and snapshot tables — and they disagree with each other.

- `TransactionService` synchronously maintains `addrbook_stats.balance`,
  `transactions.sender_balance` / `receiver_balance`, and `addrbook_dailies`.
- `UpdateTransactionSummaries` (queued, only on `status = Completed`) maintains
  `monthly_account_summaries`, `monthly_category_summaries`, `daily_inventory_summaries`,
  `stat_sells`.
- `GenerateStockIntelligence` writes `stok_reports` / `stock_data`.

Of the four summary tables the job maintains, **only `stat_sells` is read by any page**.
`addrbook_dailies` has no reader at all. `monthly_item_sales` is maintained by a separate command
and read by nothing.

`daily_inventory_summaries` is worse than unused — it is a live crash. Migration
`2026_04_13_085605_simplify_daily_inventory_summaries_table` reduced the table to `qty_sell` and
`stock_on_hand`:

```sql
CREATE TABLE "daily_inventory_summaries" (
  "id" integer primary key autoincrement not null, "date" date not null,
  "warehouse_id" integer not null, "item_id" integer not null,
  "qty_sell" numeric not null default '0', "stock_on_hand" numeric not null default '0', ...)
```

but `UpdateTransactionSummaries` still increments seven columns that no longer exist:

```php
TransactionType::Buy            => $s->increment('qty_buy', $qty),
TransactionType::Move           => $side === 'receiver' ? $s->increment('qty_move_in', $qty)
                                                        : $s->increment('qty_move_out', $qty),
TransactionType::Return         => $s->increment('qty_return_in', $qty),
TransactionType::ReturnSupplier => $s->increment('qty_return_out', $qty),
TransactionType::Adjust         => $qty > 0 ? $s->increment('qty_adjust_in', $qty)
                                            : $s->increment('qty_adjust_out', abs($qty)),
```

So every completed Buy, Move, Return, ReturnSupplier or Adjust queues a job that throws on the
missing column. Only Sell survives. If `queue:listen` is running, `failed_jobs` should be filling
up; if it is not running, the summaries were never being written in the first place. Either way the
monthly and daily summary tables cannot be trusted as a starting point.

---

## 2. Defect 1 — cash movements are invisible to every money report

This is the single largest reason the reporting "doesn't work", and it is mechanical.

`CreateItemTransaction` writes both columns: `total` (sum of line totals, pre-tax) and
`grand_total` (after discount, adjustment and PPN, signed).

`CreateCashTransaction`, `CreateTransferTransaction` and `CreateAdjustTransaction` write **only**
`grand_total`. `total` is never set, so it keeps its schema default of `0`:

```php
// app/Actions/Transactions/CreateCashTransaction.php
'grand_total' => $grandTotal, 'total_items' => 0,
'adjustment' => 0, 'discount' => 0, 'tax_amount' => 0,
```

Every consumer reads `total`:

| Consumer | Code |
|---|---|
| `CashFlowController` | `SUM(CASE WHEN type = CASH_IN THEN total ELSE 0 END)` |
| `Reports\NettCashController` | `SUM(total) as total`, then bucketed into `cashIn` / `cashOut` |
| `Reports\PurchaseReportController` | `SUM(total) as total` |
| `Reports\ExpenseReportController` | `SUM(total) as total` over account ↔ bank rows |
| `journals/account-list/ledger.blade.php` | `$debit = $isReceiver ? $trx->total : 0` |
| `UpdateTransactionSummaries` | `$summary->increment($column, (float) $transaction->total)` ×2 |

So: every Cash In, Cash Out, Transfer and Adjust row contributes **zero** to the Cash Flow report,
the Nett Cash cash columns, the Pembelian cash columns, the whole Laporan Biaya (which consists
*only* of account ↔ bank cash movements), the buku besar debit/credit columns, and both monthly
summary tables.

### Evidence

Five transactions posted through the real HTTP endpoints, then the stored columns read back:

```
== TRANSACTIONS (as written by the app) ==
type                      total   tax_amount    grand_total
Sell                  1,000,000      110,000     -1,110,000
Sell                  1,000,000            0     -1,000,000
Buy                     500,000       55,000        555,000
CashIn                        0            0      1,110,000
CashOut                       0            0       -555,000

== what a report summing `total` sees for cash rows ==
SUM(total) over cash rows      = 0
SUM(grand_total) over cash rows= 555,000
```

The Laporan Biaya is therefore structurally an empty table, and the buku besar shows `0` debit and
`0` credit on every line while the balance column beside it moves — because the balance column
comes from `sender_balance` / `receiver_balance`, which *are* maintained correctly.

### Consequence for the fix

`grand_total` is the reliable column. Either backfill `total` for the affected rows, or make the
new reporting layer read a single normalised amount and never touch `total` again. Both are
specified in `06`; the recommendation is to backfill *and* standardise, because the old pages
should start working too.

---

## 3. Defect 2 — nothing classifies an account

To produce a neraca you must know that "BCA" is cash, "PT Sumber Kain" is a payable, and "Biaya
Listrik" is an expense. Today the only classifiers are:

- `addrbooks.type` — customer / warehouse / bank / supplier / v_warehouse / v_account / reseller /
  account / other. Useful, but coarse: `type = 8 (Account)` covers every expense and income account
  indiscriminately.
- `addrbooks.operation_id` → `operations` — a flat, free-text grouping with only `name` and
  `description`. No code, no normal balance, no statement line, no hierarchy.

There is no account code, no asset/liability/equity/revenue/expense dimension, and no mapping from
either to a statement line. `03` proposes adding that as a mapping layer rather than a new ledger.

---

## 4. Defect 3 — inventory has quantity but no value

- `Produksi\SendToWarehouse` posts a `Production` transaction with `price = 0`, `total = 0`, calls
  `InventoryService::add()` directly (bypassing `TransactionService`), and leaves `status` at its
  default `0 (pending)` — which also means `TransactionObserver` never dispatches the summary job
  for it. Finished goods enter the warehouse at zero value.
- `items.cost` is set only from the item form (`ItemService`) and is never recalculated by a
  purchase. There is no FIFO, no weighted average, no `COGS`/`HPP` logic anywhere in `app/`.
- Nothing consumes raw material. There are no `Use` (type 8) transactions — that enum value has no
  create path at all, and confusingly the `addrbook_dailies.use` column is fed by
  `Production` (16) instead.
- Sewing labour lives in `borongan_details.ongkos`, priced from `tags.price` on the jahit tag, and
  never reaches `items.cost` or any ledger account.

Net effect: `warehouse_items` is a quantity ledger with no money attached, so neraca has no
Persediaan and laba rugi has no HPP. `04` addresses this.

---

## 5. Defect 4 — labour and payroll never reach the books

`borongans` / `borongan_details` (piece-rate sewing) and `gajis` (monthly salary) are standalone
records. Neither creates a transaction, neither touches an addrbook balance, and neither posts to
an expense account. `gajis.bank_id` records the *intended* disbursement bank but no cash movement
follows.

So the largest production cost in the business is absent from every financial figure the system
can currently produce. This is also why the user's proposed persediaan formula reaches for "gaji
mingguan" as a proxy — the wage data exists, it is just not connected.

---

## 6. Confirmed: the signed-balance convention is sound

The convention is consistent and can carry the whole receivable/payable side of the neraca.
Verified empirically with the same five transactions:

```
== addrbook_stats.balance ==
Cust PPN       Customer                  0   <- settled
Cust NonPPN    Customer         -1,000,000   <- they owe us
Supplier       Supplier                  0   <- settled
Bank           Bank                555,000   <- cash held
WH             Warehouse                 0   <- never touched
```

Reading:

| Addrbook type | Sign meaning | Statement line |
|---|---|---|
| Customer (1), Reseller (7) | negative = they owe us | Piutang Usaha (asset) |
| Supplier (4) | positive = we owe them | Hutang Usaha (liability) |
| Bank (3) | positive = cash we hold | Kas & Bank (asset) |
| Account (8) | accumulated expense or income | Laba Rugi line, via `operations` |
| Warehouse (2), V.Warehouse (5) | always `0` | value comes from `warehouse_items`, not balances |
| Other (99) | either | needs explicit classification |

Two things to note before relying on it.

**The double entry is deliberately partial.** `TransactionService::updateBalances()` updates the
supplier on a Buy but not the receiving warehouse, and the customer on a Sell but not the sending
warehouse. So balances alone do not self-balance — the inventory and equity sides have to come
from elsewhere. That is expected for a single-entry system and is exactly what the suspense line
in `03` is for.

**Warehouse balances are always zero**, so `Move` and `Transfer` between warehouses are invisible
to the balance layer. Inventory movement must be read from `warehouse_items` and
`transaction_details`.

---

## 7. PPN today

```php
// app/Actions/Transactions/Concerns/CalculatesTransactionTotals.php
protected function getPpnRate(): float
{
    return (float) Setting::getValue('ppn_rate', 11) / 100;
}
```

- Rate: a single scalar setting, `ppn_rate = '11'`. Not effective-dated, so recomputing a prior
  period after a rate change would silently use today's rate.
- Applied only to `Buy` (when the supplier has `ppn = true`) and `Sell` (when the customer has
  `ppn = true`). Returns, cash, transfer and adjust are always `tax_amount = 0` — so a customer
  return never reverses the PPN that the original sale charged.
- Stored only on `transactions.tax_amount`, at header level. `transaction_details` has no tax
  columns, so per-item DPP cannot be reconstructed when a header discount is present.
- Computed on `itemsTotal − discount + adjustment`, meaning `adjustment` (used for ongkir, per the
  `ongkir` setting) is inside the tax base.
- Nothing anywhere stores NPWP, NIK, faktur number, faktur date, kode transaksi, or PKP status.

The single most consequential behaviour: **`ppn = false` produces `tax_amount = 0`**, not a
tax-inclusive price. Confirmed above — the non-PPN sale of 1,000,000 carries zero tax. `02`
covers what that means.

---

## 8. Book closing constrains, and helps

`BookClosingService::isDateClosed()` compares *today* against the closing day of the *target
date's* month:

```php
$closingDateOfTargetMonth = $date->copy()->day(min($tutupBukuDay, $date->daysInMonth))->startOfDay();
return $today->startOfDay()->greaterThan($closingDateOfTargetMonth);
```

With the default `tutup_buku = 28`, any date in a prior month is always closed — today is always
past last month's 28th. So back-dating into a closed month is impossible through the UI.

Two consequences. Reports over prior months are effectively immutable, which makes materialising
period snapshots safe and cheap. But there is currently no record of *when* a period was closed or
what its figures were, so a filed SPT cannot be reproduced later. `05` proposes an
`accounting_periods` table to capture that.

---

## 9. Smaller issues worth fixing while nearby

- `ItemsController@itemStats` and `groupStats` use MySQL `DATE_FORMAT`, which errors on the SQLite
  dev database. New reporting code should use Carbon-side formatting or portable expressions so
  the whole suite runs locally.
- `ReportController@stockIntelligence` authorises against `report-inventory-health` although
  `report-stock-intelligence` exists and is what the sidebar checks.
- `reports.inventory-health` has a route but no sidebar link.
- `updateStockSettings` / `resetStockSettings` are stubs that flash success without persisting.
- `AddrbookController` selects `code` and `alias` columns that do not exist on `addrbooks`.
- `Transaction` casts `total`, `discount`, `grand_total` to `decimal:2` but leaves `tax_amount`,
  `sender_balance` and `receiver_balance` uncast.
- `TransactionService::getDailyReportColumn()` maps `CashIn → 'sell'` and `CashOut → 'buy'`, so
  `addrbook_dailies` conflates cash with trade. Any future reader of that table must know this.

---

## 10. Summary of what has to be true before reporting works

| # | Requirement | Where addressed |
|---|---|---|
| 1 | One trustworthy signed amount per transaction, for all types | `06` Phase 0 |
| 2 | Every addrbook classified into a statement line | `03` |
| 3 | Fiscal periods that can be closed and snapshotted | `05` |
| 4 | Tax identity and faktur data captured per transaction | `02`, `05` |
| 5 | Effective-dated tax rates | `02`, `05` |
| 6 | An inventory value, however approximate, with a documented method | `04` |
| 7 | Labour cost posted to the books | `04` |
