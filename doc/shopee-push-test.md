# Shopee Push Mechanism (test inbox)

Observability-only endpoint for Shopee Open Platform **Push Mechanism**. Does **not** create transactions or change stock — Jubelio remains the order path.

## Callback URL

Register this exact HTTPS URL in Open Platform Console → **Push Mechanism → Set Push**:

```text
https://{your-aria-host}/shopee/push
```

Local dev needs a public tunnel (ngrok, Cloudflare Tunnel, etc.) — Shopee must reach the host to verify the URL.

## Environment

Shopee uses **three** live keys on a go-live app (API, Push, SPI). Webhook `Authorization` is signed with the **Live Push Partner Key** — shown on **Push Mechanism → Set Push** / App detail — **not** the Live Open API Partner Key used for `ShopeeStockOpenApiService`.

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

By default pushes are **not** written to DB or info-logged (only signature failures warn). Optional:

```env
SHOPEE_PUSH_STORE_INBOX=true
SHOPEE_PUSH_STORE_INBOX_CODES=3,4
```

Codes: `3` order status, `4` tracking number.

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