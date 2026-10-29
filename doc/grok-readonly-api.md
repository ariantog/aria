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

**Laravel Sanctum** personal access token on user `grok`:

```bash
php artisan tinker --execute="
\$u = \App\Models\User::where('username', 'grok')->firstOrFail();
echo \$u->createToken('grok-bot', ['read-only'])->plainTextToken;
"
```

Store the token in the Grok bot vault — **never** paste it in chat.

Request header:

`Authorization: Bearer <token>`

Accept: `application/json`

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

## Production migration

Sanctum table (guarded):

`php artisan migrate --path=database/migrations/2026_10_29_200827_create_personal_access_tokens_table.php`

Also included in the production bootstrap bundle when that migration is wired into `2026_08_13_100000_production_database_bootstrap.php`.
