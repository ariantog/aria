# Jubelio proxy API (bots)

Authorized external bots call Aria instead of Jubelio directly. Aria attaches the server-side Jubelio token, enforces an allowlisted API map, masks sensitive fields, and audits each call.

Base URL (production): `https://aria3.corenationactive.com/api`

## Authentication

Send `Authorization: Bearer <token>` from `php artisan jubelio-proxy:client create <name>`. The plain token is shown once; only a hash is stored.

## Endpoints

| Method | Path | Body |
|--------|------|------|
| POST | `/api/jubelio-proxy` | `{"api":"<name>","params":{...}}` |
| GET | `/api/jubelio-proxy/{api}` | query string = params |

### Success response

```json
{
  "ok": true,
  "api": "orders.list",
  "status": 200,
  "data": { },
  "fetched_at": "2026-11-15T17:42:00+07:00"
}
```

### Error response

```json
{
  "ok": false,
  "error": "Human-readable message",
  "status": 403,
  "api": "orders.list"
}
```

Never includes Jubelio credentials, stack traces, or raw Laravel errors.

## Phase 1 read APIs

| API name | Description |
|----------|-------------|
| `orders.list` | Paginated sales orders |
| `orders.detail` | Single order — param `id` (path) |
| `orders.ready_to_pick` | Ready-to-pick queue |
| `returns.list` | Sales returns list |
| `returns.detail` | Single return — param `id` |
| `inventory.list` | Product/inventory catalog |
| `inventory.search` | Search `inventory/items/to-stock` — param `q` |
| `locations.list` | Warehouses/locations |
| `locations.detail` | Single location — param `id` |

Write APIs (e.g. `stock.adjust_warehouse`) exist in config but are **disabled** unless both `JUBELIO_PROXY_ALLOW_WRITE=true` and the client has `write_enabled`.

## Scopes

| Scope | Effect |
|-------|--------|
| *(none)* | Cost/HPP fields nulled; PII masked as `[redacted]` |
| `finance` | Unmasked cost-style fields |
| `pii` | Unmasked phone/email/address fields |

Assign with `--scopes=pii` (Channels bot) or `--scopes=finance` when creating a client.

## Rate limits

Default **60 requests/minute** per client (`JUBELIO_PROXY_RATE_LIMIT` / per-client `--rate-limit`). `pageSize` is capped at `JUBELIO_PROXY_MAX_PAGE_SIZE` (default 200).

## Examples

```bash
# List orders (Channels bot token)
curl -sS -H "Authorization: Bearer jbp_..." \
  -H "Content-Type: application/json" \
  -d '{"api":"orders.list","params":{"page":1,"pageSize":50,"transactionDateFrom":"2026-11-01"}}' \
  https://aria3.corenationactive.com/api/jubelio-proxy

# Order detail via GET
curl -sS -H "Authorization: Bearer jbp_..." \
  "https://aria3.corenationactive.com/api/jubelio-proxy/orders.detail?id=12345"

# Locations (read-only)
curl -sS -H "Authorization: Bearer jbp_..." \
  "https://aria3.corenationactive.com/api/jubelio-proxy/locations.list?page=1&pageSize=20"
```

## HTTP status codes (proxy layer)

| Status | Meaning |
|--------|---------|
| 401 | Missing/invalid/revoked bearer token |
| 403 | Unknown API, out of scope, or write blocked |
| 422 | Invalid/disallowed params |
| 429 | Rate limit exceeded |
| 502 | Jubelio auth/transport failure from Aria |

The JSON `status` field also reflects the upstream Jubelio HTTP code when the call reached Jubelio.

## Operations

```bash
# Create clients (run once per bot — save the printed token)
php artisan jubelio-proxy:client create channels-bot --scopes=pii
php artisan jubelio-proxy:client create aria-bot

php artisan jubelio-proxy:client list
php artisan jubelio-proxy:client revoke channels-bot
php artisan jubelio-proxy:logs --client=channels-bot --limit=50
```

## Production migration

```bash
php artisan migrate --path=database/migrations/2026_11_15_100000_install_jubelio_proxy_tables.php --force
```

Config map: `config/jubelio_proxy.php`.