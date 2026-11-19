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

Temporary local debugging only (Console verify may still require valid signatures):

```env
SHOPEE_PUSH_VERIFY_SIGNATURE=false
```

## Migration

```bash
php artisan migrate --path=database/migrations/2026_11_19_140000_create_shopee_push_inbox_table.php
```

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