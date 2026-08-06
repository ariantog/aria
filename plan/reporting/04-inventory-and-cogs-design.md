# 04 — Inventory Valuation, Persediaan and HPP

Answering the persediaan awal question directly, then generalising it into something that also
produces HPP and a finished-goods figure for the neraca.

---

## 1. The problem, stated precisely

Three inventory buckets exist physically. None has a value in the system.

| Bucket | Where the quantity lives | Value today |
|---|---|---|
| **Bahan baku** (fabric, accessories) | nowhere — bought and expensed, never stocked as items | none |
| **Barang dalam proses** (cut, being sewn) | `produksis` rows with `status ∈ {1, 2}` | none |
| **Barang jadi** (finished garments) | `warehouse_items.quantity` | none — production posts at `price = 0` |

And the reason the raw material bucket is hard is real, not a data-entry failure: you cannot
practically count how many metres of fabric went into a given batch. Any solution has to estimate
consumption rather than measure it.

---

## 2. The proposed formula, and where it holds up

> current month's persediaan awal = last month's persediaan awal + buying material from ledger
> (can be 1 ledger account or many) − production cost (proxied by gaji mingguan)

The structure is right — it is the standard rolling identity:

```
Persediaan Akhir = Persediaan Awal + Pembelian − Pemakaian
Persediaan Awal(bulan n) = Persediaan Akhir(bulan n−1)
```

Two refinements are needed before it can be implemented.

**The formula computes persediaan *akhir*, not *awal*.** Awal is simply the prior month's akhir. The
distinction matters because it means only one number per month needs storing and one needs seeding,
not two.

**The wage bill is a proxy for production *volume*, not for material *cost*.** Wages paid ≠ fabric
consumed. Using it directly would deduct Rp 10 juta of fabric because Rp 10 juta of wages were paid,
which is only correct if fabric and labour happen to cost the same. What is needed is a conversion:

```
Pemakaian Bahan = Biaya Tenaga Kerja × material_to_labour_ratio
```

where the ratio is a setting you calibrate (if fabric is roughly 3.5× the sewing fee, ratio = 3.5).
This is a legitimate estimate and it is defensible, as long as the ratio is recorded and reviewed.

---

## 3. A better estimator is already in the data

Rather than going through wages, go through **units produced**, which the system knows exactly.

`produksis` records `quantity`, `item_id` and `gudang_date` per batch, and `borongan_details`
records `quantity` and `ongkos` per finished item. So for any month you know precisely how many
garments of each type were finished. Attach a standard material cost per garment and the estimate
becomes direct:

```
Pemakaian Bahan = Σ (qty produced × standard_material_cost of that item)
```

Compared with the wage proxy this is more accurate (it varies by product rather than assuming a
uniform ratio), it degrades gracefully (an item with no standard cost falls back to its group's, then
to the global ratio), and — the important part — **the same number values the finished goods**.

That is the leverage. One piece of master data, standard cost per item, solves four problems at once:

| Problem | Solved by |
|---|---|
| Material consumption unknown | `Σ qty produced × material component` |
| Finished goods have no value | `warehouse_items.quantity × unit cost` → Persediaan Barang Jadi |
| Production posts at `price = 0` | post `SendToWarehouse` at standard cost |
| No HPP | `qty sold × unit cost` |

Keep the wage-ratio method as the fallback for months before standard costs exist, and as a
cross-check afterwards — if the two estimates diverge sharply, a standard cost is stale.

---

## 4. Standard cost

Three components per finished item, because they behave differently and are separately useful:

| Component | Source | Maintenance |
|---|---|---|
| `standard_material_cost` | fabric + accessories per garment | reviewed when fabric prices move |
| `standard_labour_cost` | already exists — `tags.price` on the jahit tag, the piece rate | already maintained for borongan |
| `standard_overhead_cost` | allocated fixed cost per garment | a setting, reviewed occasionally |

`unit_cost = material + labour + overhead`.

Store on `items`, with fallback to `item_groups`, with a global default in settings — so a new item
is immediately valued at something reasonable and can be refined later. Keep an effective date so
a cost change does not silently restate closed periods.

Note that `standard_labour_cost` requires no new data entry at all: `tags.price` on the TYPE_JAHIT
tag is exactly the piece rate, and `BoronganController` already reads it.

---

## 5. Anchor, movement, opname

Estimates drift. The design has to expect it and provide a correction point, otherwise the error
compounds forever.

```
      ┌─ opening anchor (entered once, from a physical count)
      │
      ├─ + purchases        (from the ledger accounts you nominate)
      ├─ − consumption      (from units produced × standard material cost)
      │
      └─ = computed closing  ──┐
                               ├─ if a stock opname exists for the period, it WINS,
                               │  and the difference is posted as a selisih
      opname closing  ─────────┘
```

Implemented as a `period_inventory` table (`05`) with one row per period per bucket, carrying both
`computed_value` and `opname_value`, a `method` flag saying which was used, and the resulting
`selisih`. The selisih goes to laba rugi as an inventory adjustment and stops the estimate from
accumulating error indefinitely.

Practically: do a real count once or twice a year, enter it, and let the estimate run in between.
That is what most garment businesses of this size actually do, and it is what the SPT Tahunan
daftar persediaan needs anyway.

### Which ledger accounts count as material purchases

The user's phrasing — "can be 1 ledger account or many" — is exactly right, so make it a setting:
`inventory.material_purchase_account_ids`, a list of `addrbooks.id` where `type = 8`. Any `CashOut`
or `Buy` hitting one of those accounts in the period is material purchase. Same pattern as the
existing `restock.default_warehouse_ids` setting, so it needs no new mechanism.

---

## 6. Getting labour into the books

`borongans` and `gajis` currently touch nothing (`01` §5). Two ways to connect them, and the choice
affects how much bookkeeping discipline is required.

**Option A — auto-post on payment (recommended).** When a borongan or gaji is marked paid, create a
`CashOut` transaction from the paying bank to a nominated expense account. Uses the existing
transaction machinery, so balances, the ledger and every report pick it up for free. Needs
`payroll.borongan_expense_account_id` and `payroll.gaji_expense_account_id` settings and a `paid_at`
/ `transaction_id` column on each table.

**Option B — accrue only.** Report borongan and gaji totals directly into laba rugi from their own
tables, and show unpaid ones as Hutang Gaji in the neraca, without creating transactions. Less
invasive, but the expense then exists in two places with no reconciliation between them, and the
cash side still never appears in arus kas.

Option A is better and only slightly more work, because it reuses `CreateCashTransaction`. It also
makes the PPh 21/23 withholding in `02` §7 natural: the withheld amount is the difference between
the gross expense and the cash paid, which is precisely a second posting to a Hutang PPh account.

---

## 7. HPP

Once unit costs exist:

```
HPP = Σ over sold lines (transaction_details.quantity × unit_cost at date of sale)
```

Cross-check against the period identity from §2:

```
HPP = Persediaan Awal + Pembelian + Biaya Produksi − Persediaan Akhir
```

The two should agree within the selisih. Showing both on the laba rugi, with the difference, is a
better control than showing either alone.

This unlocks **gross margin per item, per item group and per customer** — arguably the most
commercially valuable report in this whole design set, and one the business currently has no way to
see at all. It is why `04` should not be deferred indefinitely in favour of tax work.

---

## 8. Fixing the production posting

`Produksi\SendToWarehouse` needs three changes, all small:

1. Post the detail at standard cost rather than `price = 0`, so `total` and `grand_total` carry
   value.
2. Set `status = Completed` so `TransactionObserver` dispatches the summary job — currently it stays
   pending and is skipped.
3. Route through `TransactionService::handleTransaction()` instead of calling `InventoryService::add()`
   directly, so stock, balances and daily aggregates stay consistent with every other transaction
   type.

Change 3 needs care: `updateBalances()` has no branch for `Production`, so it will post no balance
movement, which is correct — a production receipt is an internal transfer, not a receivable. And
`updateGlobalStock()` ignores `Production`, so `items.qty` would still not move; whether that
matters depends on whether `items.qty` is meant to be authoritative (it is a denormalised global
total, and `warehouse_items` is the real store). Worth a test either way.

---

## 9. Barang dalam proses

The cheapest defensible WIP figure, and worth having because it is a required line on the neraca:

```
WIP = Σ over produksis with status ∈ {1 Produksi, 2 Setor} of
        (quantity × (standard_material_cost + standard_labour_cost × completion_factor))
```

with `completion_factor` a setting per status — material is committed at cutting, labour accrues
through sewing. Something like 0.0 at Produksi and 0.8 at Setor is a reasonable starting point.
Precision here matters far less than having the line present and consistent between periods.

---

## 10. Settings this introduces

| Group | Slug | Purpose |
|---|---|---|
| Inventory | `inventory.material_purchase_account_ids` | which ledger accounts are material purchases |
| Inventory | `inventory.opening_bahan_baku` | the one-time anchor value |
| Inventory | `inventory.opening_period` | the period the anchor applies to |
| Inventory | `inventory.material_to_labour_ratio` | fallback estimator |
| Inventory | `inventory.default_overhead_per_unit` | overhead when not set on the item |
| Inventory | `inventory.wip_completion_factor` | per produksi status |
| Payroll | `payroll.borongan_expense_account_id` | expense account for piece-rate wages |
| Payroll | `payroll.gaji_expense_account_id` | expense account for salaries |

All follow the existing `Setting::getValue()` / JSON-cast pattern, so `SettingSeeder` and the
system-settings UI need only new rows.

---

## 11. Order of work

1. Standard cost fields on `items` / `item_groups` + settings. No behaviour change yet.
2. `period_inventory` table and the computation service. Read-only report first — see the numbers
   before anything depends on them.
3. Persediaan report with all three buckets, computed vs opname.
4. Fix `SendToWarehouse` to post at cost (§8).
5. Post borongan and gaji to the ledger (§6).
6. HPP and margin reports (§7).

Steps 1–3 are safe: they add data and a report without changing how any transaction is written.
Steps 4 and 5 change posting behaviour and need feature tests covering stock, balances and the
existing transaction tests staying green.
