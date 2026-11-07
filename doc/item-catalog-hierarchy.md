# Item catalog hierarchy (parent → colorway → SKU)

## Levels

| Level | Identity | Storage |
|-------|----------|---------|
| **Parent** | `item_parents.id` | `item_parents` (pricing, product name, description, NB) |
| **Colorway** | `(master, variant)` on `item_group` | Identity + **image only** (no catalog/pricing authority) |
| **SKU** | `items` | Per-size overrides (price, description, alias, …) |

`item_group.parent_id` → `item_parents.id`.

## Read order (phase 3)

**Pricing (sell / reseller / cost / cost CNY):** SKU override → parent (`ItemPricing::resolve`). Colorway `item_group` price columns are not read.

**Description / NB:** SKU override, else parent row only (`ItemCatalog::description` / `description2`).

**Bare product title:** SKU `alias` → parent `product_name` → pcode fallback (`ItemProductTitle`).

**Url / brand / genre:** parent row, then SKU where applicable — not `item_group` catalog columns.

**Image:** colorway `item_group` (and SKU paths) via `ItemImageResolver` — unchanged.

## Writes (phase 2+)

- **Shared catalog + parent-tier pricing:** `ItemCatalog::applySharedCatalog()` → `item_parents` only.
- **Colorway:** identity (`master`/`variant`), optional per-colorway `name`, **image** (one file per colorway `item_group.id`, shared by every size SKU in that group; colorway edit upload).

## Routes

- Parent detail: `/items-group/parent/{item_parents.id}`
- Legacy colorway id: `/items-group/parent-by-group/{item_group.id}` → 301 to parent id
- Legacy parent slug: `/items-group/parent/{parentSlug}` → 301 to parent id (slug encodes old `parent_key`)

## Production rollout (complete)

1. Migrations including `item_parents` + `item_group.parent_id`.
2. One-off catalog backfill on production (artisan command removed after backfill).
3. Drop obsolete columns per `doc/item-parent-manual-column-drops.md` when verified.
