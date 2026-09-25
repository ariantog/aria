# Item catalog hierarchy (parent → colorway → SKU)

Three levels describe how **catalog attributes** (names, descriptions, prices, brand, etc.) are stored and resolved. Identity fields (pcode, master, variant, SKU `code`, tags) are unchanged — see AGENTS.md **Item group & item identity**.

## Levels

| Level | Identity example | Storage |
|-------|------------------|---------|
| **Parent group** | `AJJ-CX90032`, `GLOVE-02` | `item_parent_prices` (`parent_key` from `ItemIdentityBuilder::itemParentKey()`) |
| **Colorway** | `AJJ-CX90032-01`, `GLOVE-02-BLACK` | `item_group` row keyed by `(master, variant)` |
| **Size / SKU** | `AJJ-CX90032-01-M`, `GLOVE-02-BLACK-S` | `items` row (size optional — all-size SKUs omit the size segment) |

## Read order (always)

For any attribute that supports overrides, resolve **SKU → colorway → parent**. Empty or “inherit” values at a level fall through to the next.

| Attribute | SKU | Colorway | Parent |
|-----------|-----|----------|--------|
| Sell / reseller / cost / cost CNY | `items.*` | `item_group.*` | `item_parent_prices.*` |
| Description / description2 | `items.*` (only if different from colorway) | `item_group.*` | *(no column yet — UI aggregates colorways on parent detail)* |
| Bare product title | `items.alias` | `item_group.name` | `item_parent_prices.product_name` |
| Brand / genre | `items.*` (legacy mirror) | `item_group.*` | — |
| Full display `items.name` | Built from bare title + warna + size (`ItemProductTitle::buildDisplayName()`) | | |

**Pricing:** numeric `0` means inherit (`ItemPricing::resolve()`).

**Descriptions:** shared text is authoritative on `item_group`; leftover `items.description` is a per-SKU override only when it differs from the group (`ItemCatalog::description()`).

Implementation entry points:

- `App\Support\ItemCatalogHierarchy` — scope constants and field map (this document).
- `App\Support\ItemPricing` — money fields read/write + scope clears.
- `App\Support\ItemCatalog` — description / brand / genre.
- `App\Support\ItemProductTitle` — bare title and display names.

## Write rules

### SKU (size) scope

Set the value on the **`items`** row only. Other SKUs and the colorway/parent rows are untouched.

Single-item edit and colorway matrix “size override” columns use this scope.

### Colorway scope

Set the value on the **`item_group`** row for that colorway, then **clear the same field at SKU level** for every item in that group so reads fall back to the colorway value.

Examples:

- Colorway editor save → `ItemCatalog::applyToGroup()` + `ItemCatalog::resetDescriptionMirrorsForColorway()` for descriptions.
- Colorway pricing → `ItemPricing::apply(..., SCOPE_COLORWAY)` zeros `items.price` (etc.) in the group.

**UI:** colorway edit submit shows a confirmation — bulk clears per-size overrides for catalog/pricing saved at colorway scope.

### Parent group scope

Set the value on **`item_parent_prices`** for the parent key, then **clear that field on every colorway (`item_group`) and SKU (`items`) under the parent** so reads fall back to the parent row.

Examples:

- Group parent “Save group catalog” → `ItemService::applyParentGroupCatalog()` (product name on `product_name`, pricing via `ItemPricing::applyForParent()`, colorway titles reset to pcode placeholders, SKU `alias` cleared).
- Parent pricing → zeros matching columns on all related `item_group` and `items` rows.

**UI:** group parent submit shows a confirmation — affects every colorway and SKU in the product group.

## Pages

| Page | Route | Default write scope |
|------|-------|---------------------|
| Single SKU edit | `items/{id}/edit`, asset edit | SKU (shared fields can target colorway via pricing scope radios) |
| Colorway edit | `items-group/colorway/{group}/edit` | Colorway (+ per-size matrix overrides) |
| Parent detail | `items-group/parent/{group}` | Parent only (pricing fixed to group scope on form) |

## Production notes

- Leftover `items.description`, `brand`, etc. may still exist for L10 parity; new shared attributes belong on `item_group` / `item_parent_prices`.
- Do not drop or rename identity columns (`items.legacy_code`, `item_group.master`, etc.) — see AGENTS.md.
