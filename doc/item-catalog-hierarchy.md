# Item catalog hierarchy (parent → colorway → SKU)

Three levels describe how **catalog attributes** (names, descriptions, prices, brand, etc.) are stored and resolved. Identity fields (pcode, master, variant, SKU `code`, tags) are unchanged — see AGENTS.md **Item group & item identity**.

## Levels

| Level | Identity example | Storage |
|-------|------------------|---------|
| **Parent group** | `AJJ-CX90032`, `GLOVE-02` | `item_parent_prices` (`parent_key` from `ItemIdentityBuilder::itemParentKey()`) |
| **Colorway** | `AJJ-CX90032-01`, `GLOVE-02-BLACK` | `item_group` row keyed by `(master, variant)` |
| **Size / SKU** | `AJJ-CX90032-01-M`, `GLOVE-02-BLACK-S` | `items` row (size optional — all-size SKUs omit the size segment) |

## Read order (always)

For catalog attributes that support overrides, resolve **SKU → colorway → parent**. Empty or “inherit” values at a level fall through to the next.

**Pricing (sell / reseller / cost / cost CNY)** resolves **SKU → parent group only** — legacy amounts on `item_group` are not used for reads.

| Attribute | SKU | Colorway | Parent |
|-----------|-----|----------|--------|
| Sell / reseller / cost / cost CNY | `items.*` | *(legacy column — ignored)* | `item_parent_prices.*` |
| Description / description2 | `items.*` (only if different from colorway) | `item_group.*` | *(no column yet — UI aggregates colorways on parent detail)* |
| Bare product title | `items.alias` | `item_group.name` | `item_parent_prices.product_name` |
| Brand / genre | `items.*` (legacy mirror) | `item_group.*` | — |
| Full display `items.name` | Built from bare title + warna + size (`ItemProductTitle::buildDisplayName()`) | | |

**Bare title when nothing custom is stored:** resolve order is unchanged (SKU alias → colorway name if not a pcode placeholder → parent `product_name` → pcode fallback). Fallback differs by type:

- **Manufactured:** `{TYPE}-{parent master pcode}` — color segment omitted (e.g. `AJD-CX93023`, not `CX93023-01` or `AJD-CX93023-01`). See `ItemIdentityBuilder::manufacturedCatalogTitleFallback()`.
- **Asset lancar:** item `pcode` (parent-style code, e.g. `GLOVE-01`).

**Colorway product name cleared on save:** an empty colorway title field clears `item_group.name` (and SKU/group aliases) so inheritance falls through to parent; it does not re-store the colorway pcode on `name`.

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

Single-item edit and the colorway pricing matrix use this scope.

### Colorway scope (pricing)

Colorway-level pricing is **not** used for resolution. The colorway editor stores per-SKU amounts only; column-header **Apply** fills every size input client-side before **Save colorway**.

Catalog-only writes still use colorway scope for non-price fields on **`item_group`** (descriptions, brand, etc.) via `ItemCatalog::applyToGroup()`.

Examples:

- Colorway editor **Save colorway** → catalog/attributes + per-SKU pricing matrix (`ItemPricing::SCOPE_SIZE`).

### Parent group scope

Set the value on **`item_parent_prices`** for the parent key. **Product name** and **pricing** use separate save actions on the parent detail page.

- **Product name:** optional checkbox to reset every colorway title to inherit the parent (clears SKU `alias`). Without it, only the parent row changes; colorways with their own title are kept. All SKUs get `items.name` rebuilt from current catalog resolution.
- **Pricing:** parent **0** means “no group default” and does **not** change colorway/SKU columns. Values **> 0** update the parent; clearing lower overrides requires an explicit **cascade** checkbox.

Examples:

- Group parent “Save product name” → `ItemService::applyParentGroupProductName()` (optional reset of colorway titles + SKU aliases).
- Group parent “Save group pricing” → `ItemPricing::applyForParent()` with cascade only when amount > 0 and user opts in.

**UI:** each submit shows a confirmation tailored to the selected options.

## Pages

| Page | Route | Default write scope |
|------|-------|---------------------|
| Single SKU edit | `items/{id}/edit`, asset edit | SKU (shared fields can target colorway via pricing scope radios) |
| Colorway edit | `items-group/colorway/{group}/edit` | Catalog/attributes on **Save colorway**; colorway pricing via **Apply to all sizes** above the matrix; per-size overrides in the matrix |
| Parent detail | `items-group/parent/{group}` | Separate **Save product name** / **Save group pricing** |

Single-item **create/edit** uses a tabbed **Catalog & pricing** panel (`form-details`): tabs choose which catalog level you are editing (SKU / colorway / whole group); pricing scopes stay per-field with quick buttons and a “all colorway” switch.

## Production notes

- Leftover `items.description`, `brand`, etc. may still exist for L10 parity; new shared attributes belong on `item_group` / `item_parent_prices`.
- Do not drop or rename identity columns (`items.legacy_code`, `item_group.master`, etc.) — see AGENTS.md.
