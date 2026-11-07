# Item catalog hierarchy (parent → colorway → SKU)

## Levels

| Level | Identity | Storage |
|-------|----------|---------|
| **Parent** | `item_parents.id` | `item_parents` (pricing, product name, description, NB) |
| **Colorway** | `(master, variant)` on `item_group` | Identity + **image only** (no catalog/pricing authority) |
| **SKU** | `items` | Per-size overrides (price, description, alias, …) |

`item_group.parent_id` → `item_parents.id`.

## Read order

**Pricing (sell / reseller / cost / cost CNY):** SKU → parent (`ItemPricing::resolve`).

**Description / NB:** SKU override, else parent row.

**Bare product title:** SKU `alias` → parent `product_name` → pcode fallback (`ItemProductTitle`).

**Image:** colorway `item_group` (and SKU paths) via `ItemImageResolver` — unchanged.

Colorway `item_group` must not be used as a source for price or shared catalog text in L12 reads.

## Routes

- Parent detail: `/items-group/parent/{item_parents.id}`
- Legacy colorway id: `/items-group/parent-by-group/{item_group.id}` → 301 to parent id

## Production rollout

1. Run migration `2026_11_07_100000_item_parents_and_group_parent_id.php`.
2. `php artisan items:backfill-group-parent-id` (use `--dry-run` first).
3. Drop obsolete columns per `doc/item-parent-manual-column-drops.md`.
