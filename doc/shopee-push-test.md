# Shopee Push Mechanism (test inbox)

Observability-only endpoint for Shopee Open Platform **Push Mechanism**. Does **not** create transactions or change stock — Jubelio remains the order path.

## Callback URL

Register this exact HTTPS URL in Open Platform Console → **Push Mechanism → Set Push**:

```text
https://{your-aria-host}/shopee/push
```

Local dev needs a public tunnel (ngrok, Cloudflare Tunnel, etc.) — Shopee must reach the host to verify the URL.

## OAuth redirect (CORENATION ADMIN — one app for aria3)

On **aria3**, Omnichannel, Shopee Ads, and warehouse stock API clients share **one** shop OAuth row (`shopee_admin_oauth`) and **one** Open Platform app (**CORENATION ADMIN**). Authorize once from any of those pages; token refresh and API signing all use `SHOPEE_ADMIN_*`.

| Console app | Live Partner ID | OAuth redirect (`.env` / authorize link) | Aria routes using this token |
|-------------|-----------------|----------------------------------------------|------------------------------|
| **CORENATION ADMIN** | `2053195` | `https://aria3.corenationactive.com/shopee-admin/oauth/callback` | `/omnichannel`, `/shopee-ads`, `/shopee-sync` |

### Console: two different things

1. **Live Redirect URL Domain** (App → Authorization Information) — **host only**. Shopee’s UI only accepts the scheme + domain (e.g. `https://aria3.corenationactive.com/`). You **cannot** put `/shopee-admin/...` in that field; that is normal.
2. **`SHOPEE_ADMIN_REDIRECT_URL`** — **full HTTPS URL including path** that Aria puts on the `redirect=` parameter when you click Authorize. The **host** must match the domain declared above; the **path** is only in `.env`, not in Console.

Shopee validates that `redirect` / `redirect_uri` uses the same domain as Console — not that Console lists every path.

**Banned substrings on the full redirect URL:** `bot` and `.php` (Console / Shopee checks on authorize — use the Laravel route, not CDN `*.php` relays).

Optional legacy CDN relays under `public/` remain for old hosts only; do not register `*.php` URLs in Console.

**aria3 deploy:**

```env
SHOPEE_ADMIN_PARTNER_ID=2053195
SHOPEE_ADMIN_PARTNER_KEY=...   # Live Open API Partner Key (CORENATION ADMIN)
SHOPEE_ADMIN_REDIRECT_URL=https://aria3.corenationactive.com/shopee-admin/oauth/callback
SHOPEE_PUSH_PARTNER_ID=2053195
SHOPEE_PUSH_PARTNER_KEY=...    # Live Push Partner Key (not the Open API key)
```

`SHOPEE_STOCK_*` / `SHOPEE_PARTNER_*` (old COREADS) are **deprecated on aria3** — do not authorize separate stock/ads apps there. `SHOPEE_PUSH_*` is unchanged (webhook HMAC only).

**Legacy aria (STOCK CHECKER-only host)** may still use a separate Console app until migrated; L12 on aria3 assumes admin-only OAuth.

## Environment

Shopee uses **three** live keys on a go-live app (API, Push, SPI). Webhook `Authorization` is signed with the **Live Push Partner Key** — shown on **Push Mechanism → Set Push** / App detail — **not** the Live Open API Partner Key used for `ShopeeAdminOpenApiService` (omnichannel) or `ShopeeStockOpenApiService` (warehouse).

```env
SHOPEE_PUSH_PARTNER_ID=...        # Live Partner ID (same app; may match SHOPEE_STOCK_PARTNER_ID if one In-house app)
SHOPEE_PUSH_PARTNER_KEY=...       # Live Push Partner Key — required for signature verify
```

Do **not** put `SHOPEE_STOCK_PARTNER_KEY` here unless Shopee explicitly issued the same value for push (after go-live they are usually different; resetting one does not reset the other).

If `SHOPEE_PUSH_PARTNER_KEY` is empty, verification fails closed (401) when `SHOPEE_PUSH_VERIFY_SIGNATURE=true`.

If signature verification fails but the URL is correct, set the canonical callback string:

```env
SHOPEE_PUSH_CALLBACK_URL=https://your-production-host/shopee/push
```

## Console “Verify” failed (not 2xx / empty logs)

Shopee’s verify button POSTs a test push and requires **HTTP 2xx + empty body** within ~3 seconds. This app returns **401** when signature verification fails — Shopee reports that as “not 2xx”.

**Typical causes**

1. **`SHOPEE_PUSH_PARTNER_KEY` missing or Open API key** — use **Live Push Partner Key** only.
2. **Callback URL in signature ≠ Console URL** — behind nginx/Cloudflare, set exactly:
   `SHOPEE_PUSH_CALLBACK_URL=https://your-host/shopee/push` (no trailing slash unless Console has one).
3. **Code/migration not deployed** on the host Shopee calls (request never hits Laravel → still empty `shopee_push_inbox`).
4. **Log level** — after the latest handler change, every hit logs `Shopee push HTTP hit` at **info** in `storage/logs/laravel.log`.

**Unblock verify once (then turn verification back on)**

```env
SHOPEE_PUSH_VERIFY_SIGNATURE=false
```

Click Verify in Console, confirm `Shopee push HTTP hit` in logs, run migration if needed, set the correct Live Push key + `SHOPEE_PUSH_CALLBACK_URL`, set `SHOPEE_PUSH_VERIFY_SIGNATURE=true`, verify again.

**Sanity check from your laptop**

```bash
curl -sS -o /dev/null -w "%{http_code}\n" -X POST "https://your-host/shopee/push" \
  -H "Content-Type: application/json" -d '{}'
```

Expect `401` with verify on and no Authorization; if you get `404`/`502`, Shopee never reaches Laravel.

## Migration

```bash
php artisan migrate --path=database/migrations/2026_11_19_140000_create_shopee_push_inbox_table.php
```

## Logging / inbox (production)

Inbox persistence is **on by default** (`SHOPEE_PUSH_STORE_INBOX` defaults to true). Set `SHOPEE_PUSH_STORE_INBOX=false` to skip `shopee_push_inbox` writes.

Every accepted push code is stored (no per-code allowlist). Trim old rows with `php artisan marketplace:prune-webhook-events` (same retention as `MARKETPLACE_WEBHOOK_EVENTS_RETENTION_DAYS`).

**Code 0 does not create omnichannel shadows** — only code **3** (and the simulate artisan command) upsert `marketplace_order_shadows`.

## Simulate order status push (aria3 / maintainer)

Without calling HTTP:

```bash
php artisan shopee:simulate-order-status-push
```

Optional: `--ordersn=220810QSK8S7BX --status=PROCESSED --shop-id=727720655 --dispatch-job`

See `doc/omnichannel.md` for code 0 vs 3 and empty-table diagnosis.

## What’s next (Jubelio still authoritative)

1. **Console** — enable Order Status Push (3) and TrackingNo Push (4); keep shop OAuth on the same In-house app.
2. **Shadow phase** — on push code 3, queue job → `v2.order.get_order_detail` → compare `ordersn` / lines / status to `jubelioorders` + API (no Aria sell post).
3. **Resi** — replace Jubelio tracking lookup for Shopee with Shopee logistics APIs when you drop Jubelio for that channel.
4. **Post path** — only when shadow matches: queue row + post like `ProcessJubelioOrder` (new action), `submit_type` distinct, feature flag per shop.
5. **Stock** — after transactions, async push listing qty (threshold / Shopee-only-low rules); separate from push ingest.

## Inspecting events

```bash
php artisan tinker --execute="App\Models\ShopeePushInbox::query()->latest('id')->limit(5)->get(['id','shop_id','push_code','ordersn','order_status','created_at']);"
```

Push codes: `3` = order status, `4` = tracking number, `1` = shop authorized, etc. ([Shopee push guide](https://open.shopee.com/developer-guide/18))

## HTTP contract

- Method: `POST`
- Body: raw JSON from Shopee (do not transform before verify)
- Header: `Authorization` = HMAC-SHA256 hex of `{callbackUrl}|{rawBody}` with Partner Key
- Response: **2xx** with **empty body** (required by Shopee)