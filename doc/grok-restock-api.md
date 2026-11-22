# Grok restock API

Read-only **context** endpoints for the Grok bot (same auth as `doc/grok-readonly-api.md`). Optional **apply** endpoint updates restock sheet cells when ops enables it.

Bot task prompt: **`doc/grok-restock-bot-prompt.md`**.

---

## Permissions

| Permission | Purpose |
|------------|---------|
| `export-readonly` | `GET /api/readonly/restock/*` |
| `restock-grok-apply` | `POST /api/readonly/restock/apply*` (separate from read-only middleware; still no superadmin) |

After deploy:

```bash
php artisan tinker --execute="app(\App\Services\PermissionGenerator::class)->generateAll();"
```

Give user **`grok`**: `export-readonly` always; add `restock-grok-apply` only when automated apply is approved.

---

## Authentication

Same bearer token as other readonly exports:

```env
READONLY_EXPORT_BEARER_TOKEN=…
READONLY_EXPORT_USERNAME=grok
```

```http
Authorization: Bearer <READONLY_EXPORT_BEARER_TOKEN>
Accept: application/json
```

Throttle: `readonly-export` (60/min).

---

## GET `/api/readonly/restock/context`

Inventory health + item insights + current restock sheet pipeline qty for Grok calculations.

### Query parameters

| Param | Default | Description |
|-------|---------|-------------|
| `item_type` | `assetlancar` | `assetlancar` / `asset_lancar` / `2` (asset lancar only for apply). Also `item` / `1` if needed. |
| `best_selling_only` | `false` | When true, only SKUs ranked in latest Item Insights **best_selling** month. |
| `top_n` | (none) | Limit to top N **best_selling** ranks (also excludes unranked SKUs when set). |
| `sales_window` | health window | `365` / `12m` for rolling 12-month sell stats instead of inventory-health window. |
| `from`, `to` | (health default) | Optional health date range (`YYYY-MM-DD`). |
| `warehouse_id` | restock default gudang | Warehouse for health stock / net sell. |

### Response shape

```json
{
  "data": [
    {
      "sku": "ELBOW-03-BLUE-S",
      "item_id": 123,
      "name": "SOFT EDITION - BLUE - S",
      "type": 2,
      "type_label": "Asset Lancar",
      "best_selling_rank": 4,
      "stock_qty": 12.0,
      "days_of_cover": 18.5,
      "health_key": "low",
      "health_label": "Low stock",
      "net_period": 90.0,
      "monthly_net": 30.0,
      "sales_window_days": 90,
      "qty_restock": 5,
      "qty_production": 0,
      "qty_shipped": 0,
      "sheet_links": [],
      "aria_suggested_qty": 27,
      "aria_suggested_qty_note": "Reference only — Grok may override."
    }
  ],
  "meta": {
    "item_type": 2,
    "item_type_label": "Asset Lancar",
    "best_selling_only": false,
    "top_n": null,
    "sales_window": 0,
    "health_windows": { "period_from": "…", "period_to": "…", "period_days": 90 },
    "insight_period": { "year": 2026, "month": 5 },
    "row_count": 1
  }
}
```

`aria_suggested_qty` uses Aria’s 8-week cover formula (`RestockRecommendationApplyService::suggestedQtyEightWeekCover`) — **reference only**.

### Example

```bash
curl -sS -H "Authorization: Bearer $GROK_TOKEN" \
  "https://aria.example.com/api/readonly/restock/context?item_type=assetlancar&best_selling_only=true&top_n=50"
```

---

## GET `/api/readonly/restock/insight-period`

Latest calculated **monthly** Item Insights period (used for `best_selling_rank`).

```json
{
  "data": { "year": 2026, "month": 5, "calculated_at": "2026-05-02T00:00:00+00:00" },
  "meta": { "calculated": true }
}
```

---

## POST apply (optional, off by default)

Writes restock sheet **`qty_restock`** via the same validation as UI flat import (`direction: set_restock`, **replace** semantics — not additive).

```env
READONLY_RESTOCK_GROK_APPLY_ENABLED=false
```

When `false`, `POST /api/readonly/restock/apply` returns **403** even with `restock-grok-apply`. Default ops path: bot produces CSV/XLSX for a human to upload on **Restock → Import flat**.

When `true` and user has `restock-grok-apply`:

### `POST /api/readonly/restock/apply/preview`

```json
{
  "direction": "set_restock",
  "rows": [
    { "sku": "ELBOW-03-BLUE-S", "qty": 25 }
  ]
}
```

Returns the same preview structure as UI `import-flat/preview` (per-line status, duplicate SKU errors, etc.).

### `POST /api/readonly/restock/apply`

Same body. Applies all `ok` lines (optional `lines: [1,3]` to apply subset by preview line number).

**Constraints:** asset lancar SKUs on an existing restock sheet row only; see `RestockRecommendationApplyService::resolveRestockTarget`.

---

## Manual upload (always available)

If apply is disabled, output a flat file for **Restock → Import flat → Set restock qty**:

| Column | Required | Notes |
|--------|----------|--------|
| `sku` | yes | `items.code` (uppercase) |
| `quantity` | yes | Non-negative integer; **sets** `qty_restock` (not add) |

See `doc/grok-restock-bot-prompt.md` for bot rules (skip overstock/dead, skip qty 0, skip unchanged, no duplicate SKUs).