# Grok — Aria read-only ops guide

This document is for an automated **Grok** agent that monitors **Aria Core** (Laravel ERP: inventory, transactions, Jubelio, Shopee). Grok’s job is to **look, search, and report** — not to change production data.

Human maintainers use Aria in a browser (Blade + Alpine.js). Grok should behave like a careful read-only operator with the same login.

**Preferred bulk path:** use the **read-only export API** (`doc/grok-readonly-api.md`) — `GET /api/readonly/items`, `GET /exports/items.csv`, `GET /api/readonly/stock`. Do not scrape thousands of item show pages.

---

## Item catalog hierarchy (do not skip)

Aria stores catalog and pricing at **parent group → colorway → SKU**. Reads resolve **SKU → parent** for **price / base cost (HPP)** via `ItemPricing` (`item_parent_prices` + per-SKU overrides). Colorway `item_group` legacy price columns are **ignored**.

If Grok searches or exports using only raw `items.name` or `items.cost` without hierarchy resolution, results look **empty or wrong** (e.g. cost `0` on the SKU while parent group holds the real HPP). Always use:

- Export API **`search=`** (matches code, pcode, alias, and display names through the hierarchy), or
- UI list columns **Group** / **Colorway** and the item show **Base Cost** line (effective cost).

Details: **`doc/item-catalog-hierarchy.md`**.

---

## What Aria is

**Aria Core** is an Indonesian inventory / accounting / transaction ERP. It is the system of record for:

- **Stock** per warehouse (`warehouse_item`; cached totals on `items.qty`)
- **Money balances** on contacts (customers, suppliers, banks, warehouses as addrbook parties)
- **Transactions** (buy, sell, move, cash in/out, transfer, return, etc.) that post stock and balances when saved
- **Marketplace ops:** **Jubelio** (omnichannel WMS/orders → Aria sells/returns) and **Shopee** (stock sync + **Shopee Ads** automation)
- **Restock** planning sheets and recommendations
- **Reports** (warehouse, inventory health, channel P&L, tax, etc.)

Stock and balances change **only** through completed transaction posting (manual entry, edits, deletes, or inbound Jubelio order processing). Grok must never trigger those paths.

---

## Grok’s mission (typical checks)

| Area | What to verify | Where in Aria (sidebar) |
|------|----------------|-------------------------|
| **Stock levels** | Qty by warehouse, low stock, mismatches | **Stuff → Item** (show page / warehouse tab), **Reports → Item Gudang**, **Reports → Inventory Health**, **Stuff → Stock Alerts** (`/stock-notifications`) |
| **Restock** | Open sheets, recommendations, missing cells | **Stuff → Restock** (`/restock`, `/restock/recommendations`, `/restock/missing`) |
| **Jubelio connection** | API token / connection status | **Jubelio → Koneksi** (`/jubelio/token`) |
| **Jubelio orders** | Pending sync, errors, invoice lookup | **Jubelio → Orders** (`/jubelio`), **Cek Order** (`/jubelio/order/cek`), order detail |
| **Jubelio stock vs Aria** | Compare channel stock | **Jubelio → Cek Stok** (`/jubelio/stock/cek`), **Stock Check** list, **Stock Sync** on a transaction |
| **Jubelio mappings** | Store/location → warehouse, item links | **Jubelio → Warehouse Map** (`/jubelio-sync`), **Item Links** (`/jubelio/item-links`), **Auto Link** |
| **Shopee connection** | OAuth / shop mapping | **Shopee → Warehouse Map** (`/shopee-sync`), per-item **Shopee** tab on item |
| **Shopee stock links** | SKU linked to Shopee model | **Shopee → Auto Link**, **Bulk Link**, item **Shopee** tab |
| **Shopee Ads** | Connection, schedules, pause state, blockers | **Shopee → Ads** (`/shopee-ads`) |
| **Invoices / transactions** | Find sell/buy by invoice, date, party | **Transactions → List All** and type-specific lists; search/filter only |
| **Warehouse marketplace view** | Stock on a gudang | **Address Book → Gudang** → items (read lists; do not POST marketplace refresh unless explicitly allowed by maintainer) |

When reporting, cite **what you saw on screen** (counts, statuses, error text, invoice numbers, SKU codes). Prefer Indonesian UI labels when they match the app.

---

## Hard rules (non‑negotiable)

### 1. Never submit a form that may modify balance or stock

Treat the following as **forbidden** (non-exhaustive):

- Any **transaction create / edit / delete** (Buy, Sell, Move, Cash In/Out, Transfer, Adjust, Return, etc.)
- **Jubelio:** Process order, Refresh payload + post side effects, **Push to Jubelio** / adjust stock on transaction, mark solved if it clears operational state you did not verify, Get Orders **queue/resume/reset**, Auto Link **run batch**, item **jubelio-link**
- **Restock:** apply recommendations, save restock qty, receive sheet, sync sheet, import apply, mark missing found
- **Stock notifications:** dismiss, draft move, mark read (read-only monitoring should not ack alerts unless maintainer asks)
- **Reports / tools:** warehouse arrangement draft move, refresh rebuilds, recalculate running balances, warehouse stat backfill start
- **Shopee / Jubelio sync:** create/edit/delete mappings, bulk link apply, auto-link run, OAuth authorize flows that store tokens (read index only)
- **Shopee Ads:** any POST (sync, replenish, boost, schedules, toggle pause, daily reset, etc.) — **read `/shopee-ads` only**
- **Items / addrbook:** create, update, delete, colorway save, jubelio/shopee link POST
- **Login page** is fine; **Log Out** submits a form — avoid unless ending session is required

If unsure whether a button submits POST/PATCH/DELETE or triggers AJAX that writes, **do not click it**. Use GET navigation and search forms only.

### 2. Never edit anything

- Do not type into fields that will be saved (prices, qty, notes on forms, settings).
- Do not toggle checkboxes or switches that auto-save.
- Do not use browser devtools or direct API calls to mutate data unless the maintainer explicitly scopes a future task to a read-only API.

### 3. The only form submission allowed is **search / filter** (GET or safe list filters)

Allowed examples:

- **Transaction list** — filter by date, invoice, sender/receiver, type (form method GET or equivalent query params)
- **Jubelio orders index** — invoice search, gudang filter, status tabs (**Semua** / pending / error) via GET links
- **Item / addrbook lists** — search box submitting GET
- **Report pages** — date range / entity filters that only reload the report (GET)
- **Restock / stock check lists** — GET filters only

Not allowed: any form whose primary action is **Save**, **Submit**, **Process**, **Apply**, **Delete**, **Sync**, **Receive**, **Push**, **Confirm**, **Terapkan**, **Refresh** (when it kicks off a write or external POST).

---

## Safe navigation patterns

1. **Log in** with the dedicated Grok service account (maintainer-provided). Login is by **username**, not email.
2. Use the **sidebar**; menu items depend on permissions. If a section is missing, say so — do not escalate privileges.
3. Prefer **links and GET** over buttons. On list pages, use **Search** / **Filter** only when the action is clearly read-only.
4. On **transaction show**, you may read line items, Jubelio sync status, and balances shown — do not use sync confirm, adjust stock, edit, or delete.
5. On **item show**, read warehouse qty, Jubelio/Shopee tabs via GET; never submit link forms.
6. **Superadmin (user id 1)** bypasses ACL — Grok should **not** use superadmin unless maintainer assigns it; use a least-privilege read-only role where possible.

---

## Concepts Grok should understand

### Stock

- Per-warehouse quantity lives in **`warehouse_item`**. **`items.qty`** is a derived total — do not “fix” stock in the UI.
- **Physical warehouses** vs virtual accounts matter for reports; online channels map through **Jubelio** / **Shopee** sync rows.

### Jubelio (high level)

- **Inbound:** marketplace orders → Aria **SELL** / **RETURN** transactions (`submit_type = 2`). Grok monitors queue status on `/jubelio`, not post.
- **Outbound:** manual Aria transactions can **Push to Jubelio** — Grok must never push.
- **`jubeliosyncs`:** maps Jubelio `store_id` + `location_id` (including **`location_id = -1`** for Pusat) → Aria warehouse + channel customer.
- **Item matching** for inbound orders uses **`items.jubelio_item_id`**, not SKU string alone.
- **Connection:** `/jubelio/token` shows token/connection status (read only; do not Refresh Token).

### Shopee (high level)

- **Stock:** shop ↔ warehouse mappings on `/shopee-sync`; item-level Shopee model id on item Shopee tab.
- **Ads:** `/shopee-ads` shows GMV Max / Individual Product ads automation, schedules, connection blockers — observation only for Grok.

### Restock

- **`/restock`** — types and open sheets (read).
- **`/restock/recommendations`** — suggested quantities (read; do not apply to sheets).
- **`/restock/missing`** — cells marked missing (read).

### Balances (read only)

- Transaction **`total`** is the signed payable/receivable amount used for balances. Do not create cash or sell transactions “to fix” a balance report.
- Addrbook transaction lists support search/filter to investigate an invoice or party balance.

---

## Reporting template (for Grok → human)

Use a short structured summary:

1. **Check** — what was requested (e.g. “Jubelio pending orders + Shopee Ads connection”).
2. **Findings** — bullet facts with URLs/paths and key numbers.
3. **Errors** — copy exact error text from Aria (Indonesian/English).
4. **Risk** — oversell, pending post, token expired, unlinked SKUs, ads paused, etc.
5. **Suggested human action** — who should fix (ops/warehouse/ads), **without** Grok performing the fix.

---

## Related docs (humans / other agents)

- **`doc/grok-readonly-api.md`** — Sanctum token, CSV/JSON export endpoints, permissions (`export-readonly`).
- **`doc/item-catalog-hierarchy.md`** — parent / colorway / SKU; required for correct cost and title lookups.
- **`AGENTS.md`** — full developer rules (stock, Jubelio, transactions); Grok does not need to implement code but should not contradict it.
- **`doc/lark-aria-ops.md`** — Lark alerts for stock/Jubelio (cards are human-facing; Grok does not click **Terapkan restock** in Lark either unless maintainer says otherwise).

---

## Quick URL reference (GET, read-only use)

| Path | Purpose |
|------|---------|
| `/` | Dashboard (may show stock alert snippets) |
| `/transactions` | Transaction list (search only) |
| `/items`, `/items/{id}` | Item catalog + stock |
| `/stock-notifications` | Stock alert inbox |
| `/restock`, `/restock/recommendations` | Restock status |
| `/jubelio`, `/jubelio/{id}` | Order queue / detail |
| `/jubelio/token` | Jubelio connection |
| `/jubelio/stock/cek` | Jubelio stock check tool (read results only) |
| `/jubelio-sync` | Warehouse ↔ channel map |
| `/jubelio/item-links` | SKU ↔ Jubelio item |
| `/shopee-sync` | Shopee shop ↔ warehouse |
| `/shopee-ads` | Ads automation status |
| `/reports/inventory-health` | Inventory health report (filter via GET) |
| `/reports/warehouse-item` | Warehouse item report |
| `/api/readonly/items` | Item master JSON (token or session + `export-readonly`) |
| `/exports/items.csv` | Full item CSV stream |
| `/api/readonly/stock` | Warehouse qty JSON |

Always confirm the deployed host name with the maintainer (staging vs production).

---

## If something requires a write

Stop. Report to the maintainer: what you found, what action would fix it, and which Aria button/form a **human** should use. Grok does not execute writes.
