# Production NOT NULL audit — `old.sql` vs `new.sql`

L12 partial inserts fail on production MySQL when a column is `NOT NULL` and has no `DEFAULT`.
Both `database/old.sql` (L10 prod) and `database/new.sql` (L12 export) share most of these gaps — this is not a table-name mismatch, it is legacy schema strictness.

## Fix layers

| Layer | What |
|-------|------|
| **Migration** | `2026_08_13_120000_add_production_not_null_column_defaults.php` — adds `DEFAULT` on MySQL for every `NOT NULL` column without one (except `users` + primary keys). Included in production bootstrap. |
| **Models** | `App\Support\ProductionColumnDefaults` + `FillsProductionColumnDefaults` trait on models L12 creates into — fills known legacy columns when still `null` on MySQL. |

Run on an existing prod DB (after bootstrap):

```bash
php artisan migrate --path=database/migrations/2026_08_13_120000_add_production_not_null_column_defaults.php --force
```

---

## Tables L12 writes to — risky columns

Columns that are `NOT NULL` with **no DEFAULT** in prod (`old.sql`). L12 often omits these on `create` / `firstOrCreate`.

### `customers` (`Addrbook`)

| Column | Default applied |
|--------|-----------------|
| `description`, `phone`, `phone2`, `email`, `fax` | `''` |
| `discount`, `return_p`, `parent_id`, `portalId` | `0` |
| `memberId`, `password` | `''` |

L12 form only requires `name` + `type`; other legacy cols are ignored in UI but must exist in DB.

### `customer_class` (`AddrbookDaily`) — **cash in/out hit this**

| Column | Default |
|--------|---------|
| `class` | `''` |
| `adjust`, `depreciation` | `0` |
| bucket cols (`sell`, `buy`, …) | `0` |

### `customerstat` (`AddrbookStat`)

| Column | Default |
|--------|---------|
| `balance` | `0` |

### `items` (`Item`)

| Column | Default |
|--------|---------|
| `tag_ids`, `description`, `description2`, `variant`, `pcode` | `''` |

### `item_group` (`ItemGroup`)

| Column | Default |
|--------|---------|
| `master`, `variant`, `description`, `alias`, `description2` | `''` |

### `transactions` (`Transaction`)

| Column | Default | Notes |
|--------|---------|-------|
| `description`, `detail_ids` | `''` | `description` also copied from `notes` |
| `cogs`, `location_id` | `0` | |
| `due`, `real_total` | set in `Transaction::creating` from `date` / payload |

### `transaction_details` (`TransactionDetail`)

| Column | Default |
|--------|---------|
| `transaction_disc` | `0` |

Prod already has `DEFAULT 0` on `transaction_disc` in many dumps; included for safety.

### `deleted` / `deleted_details` (archive on transaction delete)

| Table | Columns |
|-------|---------|
| `deleted` | `invoice`, `description`, `detail_ids`, `sync_hide`, … |
| `deleted_details` | `transaction_disc` |

Archive rows are built from live transaction attributes; defaults cover any missing legacy fields.

### `settings` (`Setting`)

| Column | Default |
|--------|---------|
| `location_id` | `0` |
| `value` | `''` (prod `varchar(50)`; L12 may store JSON after align) |

### `prod_produksi` (`Produksi`)

Potong entry creates rows with only `temp_name`, `size_id`, `quantity`, `customer`, `warna`, `potong_id`, … — prod requires `item_id`, `jahit_id`, `invoice`, `detail_id`, `surat_jalan_potong`, `description`, etc.

| Column | Default |
|--------|---------|
| ints | `0` |
| strings | `''` |

### `operations`, `tags`

| Table | Column | Default |
|-------|--------|---------|
| `operations` | `description` | `''` |
| `tags` | `price` | `0` |

### `warehouse_item` (`WarehouseItem`)

All NOT NULL cols are always provided on `firstOrCreate` (`warehouse_id`, `item_id`, `quantity`) — low risk.

---

## Tables L12 reads but rarely inserts

Legacy-only tables (`borongan`, `cron`, `jubelio*`, etc.) are covered by the blanket migration if L12 ever touches them. No model trait needed unless we add create paths later.

---

## `users` — excluded on purpose

User id `1` is superadmin. The defaults migration **skips the entire `users` table**; user creation must remain explicit.

---

## Verification

After migrate, smoke-test on prod clone:

1. Cash out / cash in
2. Buy / sell
3. Create addrbook contact
4. Create item
5. Potong produksi entry
6. Delete a manual transaction (archive to `deleted`)

Any remaining `1364 Field 'x' doesn't have a default value` → add `x` to `ProductionColumnDefaults::TABLE_DEFAULTS` and/or confirm the defaults migration ran.
