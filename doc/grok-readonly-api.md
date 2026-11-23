# Grok read-only export API

Bulk **GET-only** exports for the `grok` service account: item master (cost, price, channel links) and warehouse stock. No writes to MySQL, Jubelio, or Shopee.

See also **`doc/grok-aria-ops.md`** (browser rules) and **`doc/item-catalog-hierarchy.md`** (how cost/price resolve).

---

## Permissions

| Permission | Purpose |
|------------|---------|
| `export-readonly` | Call `/api/readonly/*` and `GET /exports/items.csv` |
| `cost-view` | Optional **Base Cost** column on `/items` and `/assetlancar` lists |

Generate permissions after deploy:

```bash
php artisan tinker --execute="app(\App\Services\PermissionGenerator::class)->generateAll();"
```

Assign **`export-readonly`** (and optionally **`cost-view`**) to user **`grok`**. Do **not** grant superadmin or write permissions (`items-edit`, transaction creates, etc.). Users with only `export-readonly` are blocked from POST/PATCH/DELETE and `/edit` routes by middleware.

---

## Authentication

### Browser (CSV)

Log in as `grok` (username, not email), then:

`GET /exports/items.csv?type=all`

Session cookie auth; CSRF not required for GET.

### API (JSON)

Set a long random secret on the server (Grok bot vault — **never** in chat):

```env
READONLY_EXPORT_BEARER_TOKEN=your-long-random-secret
READONLY_EXPORT_USERNAME=grok
```

The bearer token authenticates as that Aria user (must have `export-readonly` and must **not** be superadmin).

Request header:

`Authorization: Bearer <READONLY_EXPORT_BEARER_TOKEN>`

Accept: `application/json`

Session cookie auth also works if the bot logs in via the browser first.

---

## Rate limit

60 requests per minute per user (or IP when unauthenticated). Throttle name: `readonly-export`.

---

## Endpoints (GET only)

### Item master — JSON

`GET /api/readonly/items`

| Query | Description |
|-------|-------------|
| `type` | `item` \| `assetlancar` \| `all` (default `all`) |
| `search` | Partial match on SKU code, legacy code, id, pcode, alias, **display name** (uses catalog hierarchy — parent/colorway titles) |
| `updated_since` | `YYYY-MM-DD` — `items.updated_at` |
| `page`, `per_page` | Pagination; `per_page` max **1000** |

**Pricing / cost:** `price` and `base_cost` use **`ItemPricing::resolve()`** (SKU → parent `item_parent_prices`). Legacy `item_group` price columns are **not** used. If you search only raw `items.name` in SQL you may miss rows — use `search=` on this API instead.

**Cost timestamp:** `cost_updated_at` is **`items.updated_at`** (`cost_updated_at_source` labels this). There is no separate cost audit table yet.

**`cost_flag`:** `missing` \| `placeholder` (≤ Rp 1,000 or cost/price &lt; 10%) \| `above_price` \| `ok`

### Item master — CSV

`GET /exports/items.csv`

Same query params as JSON (except pagination). Streams all matching SKUs in chunks (suitable for ~11k+ rows).

### Stock by warehouse

`GET /api/readonly/stock`

| Query | Description |
|-------|-------------|
| `code` | SKU code, legacy code, or numeric item id |
| `warehouse` | Warehouse id or partial name |

Reads **`warehouse_item`** joined to addrbook warehouses. Does not call Jubelio/Shopee.

### Restock context (Grok)

See **`doc/grok-restock-api.md`** — `GET /api/readonly/restock/context`, insight period, optional apply.

### Shopee shop OAuth (access token only)

`GET /api/readonly/shopee/oauth`

Returns the current **CORENATION ADMIN** shop `access_token` and expiry for the Grok bot to call Shopee Open API directly (Ads automation, diagnostics). Uses the same stored row as Omnichannel / Shopee Ads / warehouse stock (`shopee_admin_oauth`).

| Field | Notes |
|-------|--------|
| `access_token` | Valid token; refreshed proactively when within ~5 minutes of `expires_at` (same logic as `ShopeeAdminOpenApiService::getAccessToken()` and cron `shopee:refresh-oauth`) |
| `expires_at` | ISO-8601; also `expires_at_unix` |
| `shop_id` | Authorized shop id |
| `partner_app` | Always `shopee_admin` |

**Never returned:** `refresh_token`, partner keys, `SHOPEE_*` env secrets.

**Errors (JSON body, no token in logs):**

| HTTP | `error` | Meaning |
|------|---------|---------|
| 401 | `shopee_not_connected` | No tokens or re-authorize in browser required |
| 503 | `shopee_not_configured` | Missing `SHOPEE_ADMIN_PARTNER_ID` / key on server |
| 503 | `shopee_token_unavailable` | Refresh failed — retry or fix OAuth in Aria |

**Security / ops:**

- Same auth as other readonly routes: `Authorization: Bearer READONLY_EXPORT_BEARER_TOKEN`, user **`grok`** with **`export-readonly`** only (no extra permission).
- A successful refresh **writes** the updated token to the shared `settings` row (expected; identical to hourly cron). It does **not** call Shopee inventory or post transactions.
- Treat the bearer token and API `access_token` as **production secrets**; never paste in chat or commit.
- Rate limit: `readonly-export` (60/min).

```bash
curl -sS -H "Authorization: Bearer $GROK_TOKEN" \
  "https://aria3.corenationactive.com/api/readonly/shopee/oauth"
```

### TikTok Shop OAuth (access token only)

`GET /api/readonly/tiktok/oauth`

Returns the current TikTok Shop seller `access_token` for the Grok bot (same stored row as omnichannel order detail: `tiktok_shop_oauth` in `settings`).

| Field | Notes |
|-------|--------|
| `access_token` | Valid token; refreshed proactively within ~5 minutes of `expires_at` (`TikTokShopOAuthService::getAccessToken()`) |
| `expires_at` | ISO-8601; also `expires_at_unix` when known |
| `partner_app` | Always `tiktok_shop` |
| `oauth_setting_slug` | `tiktok_shop_oauth` |

**Never returned:** `refresh_token`, `app_secret`, env secrets.

**Permissions:** `export-readonly` **and** `api.readonly.tiktok.oauth` (generate via `PermissionGenerator` on `ReadonlyExport`).

**Errors:** `tiktok_not_connected` (401), `tiktok_not_configured` (503), `tiktok_token_unavailable` (503). Emergency env-only fallback may return `source: env_fallback` when `TIKTOK_SHOP_ACCESS_TOKEN` is set but OAuth row is empty.

Aria authorize: `GET /tiktok-shop/authorize` (Connections tab on `/omnichannel/settings`). See **`doc/omnichannel.md`**.

```bash
curl -sS -H "Authorization: Bearer $GROK_TOKEN" \
  "https://aria3.corenationactive.com/api/readonly/tiktok/oauth"
```

### Cost history (stub)

`GET /api/readonly/items/{id}/cost-history`

Returns an empty `data` array and `history_available: false` until a dedicated history table exists.

---

## Example (curl)

```bash
curl -sS -H "Authorization: Bearer $GROK_TOKEN" \
  "https://aria.example.com/api/readonly/items?type=item&search=KNEESTRAP&per_page=50"
```

```bash
curl -sS -b "aria_session=..." \
  "https://aria.example.com/exports/items.csv?type=assetlancar" -o items.csv
```

---

## Production setup

No extra database tables. After deploy, set `READONLY_EXPORT_BEARER_TOKEN` in `.env`, run permission generate, and assign `export-readonly` to user `grok`.
