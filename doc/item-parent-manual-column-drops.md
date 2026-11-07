# Manual column drops (maintainer) — parent / colorway catalog flatten

L12 code stops **reading** these for catalog/pricing where noted. Drop on production MySQL only after catalog backfill and verification (phase 3 reads parent/SKU only).

## `item_group` (colorway row)

| Column | Action |
|--------|--------|
| `price` | Drop when parent + SKU pricing verified |
| `reseller_price` | Drop |
| `cost` | Drop |
| `cost_cnh` | Drop |
| `description` | Drop |
| `description2` | Drop |
| `name` | Drop only if you no longer need legacy L10 reads; L12 display title uses `item_parents.product_name` + SKU alias |
| `alias` | Drop (legacy colorway title) |
| `url` | Drop |
| `brand` | Drop |
| `genre` | Drop |

**Keep on `item_group`:** `id`, `parent_id`, `master`, `variant`, `image` (and any columns L10 still requires until Phase 2).

## `item_parents` (formerly `item_parent_prices`)

| Column | Action |
|--------|--------|
| `parent_key` | Drop after all rows have `item_group.parent_id` and code no longer uses key (optional interim: keep nullable) |

## `items` (SKU)

**Keep:** `price`, `reseller_price`, `cost`, `cost_cnh`, `description`, `description2`, `alias`, identity/stock columns.

## Table rename

- `item_parent_prices` → `item_parents` is handled by migration `2026_11_07_100000_item_parents_and_group_parent_id.php` on environments that still have the old name.
