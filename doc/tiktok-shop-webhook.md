# TikTok Shop webhooks (receive-only inbox)

Observability-only endpoint for TikTok Shop **Event API** webhooks. Does **not** create transactions, change `warehouse_item`, or call Jubelio — mirror phase of `shopee_push_inbox`.

## Callback URL

Register this HTTPS URL in **Partner Center → your app → Webhooks** (or via `PUT …/event/202309/webhooks`):

```text
https://{your-aria-host}/tiktok-shop/webhook
```

Example on aria3:

```text
https://aria3.corenationactive.com/tiktok-shop/webhook
```

Local dev needs a public tunnel — TikTok must reach the host to deliver events.

## Suggested first topics

| `event_type` | Notes |
|--------------|--------|
| `ORDER_STATUS_CHANGE` | Primary order ingest signal; `type` in body is often `1` |
| (optional later) `PACKAGE_UPDATE`, `CANCELLATION_STATUS_CHANGE` | Fulfillment / cancel visibility |

Start with **ORDER_STATUS_CHANGE** only until ops confirms payload shape on production.

## Environment

Webhooks use the same app credentials as Open API — **not** the outbound API request `sign` algorithm.

```env
TIKTOK_SHOP_APP_KEY=...
TIKTOK_SHOP_APP_SECRET=...
TIKTOK_SHOP_DRIVER=mock          # OK — webhook receive does not require live driver
TIKTOK_SHOP_WEBHOOK_ENABLED=true
TIKTOK_SHOP_WEBHOOK_VERIFY_SIGNATURE=true
TIKTOK_SHOP_WEBHOOK_STORE_INBOX=true
```

If `TIKTOK_SHOP_APP_SECRET` is empty, verification fails closed (401) when verify is on.

**Unblock Partner Center URL test once** (then turn verify back on):

```env
TIKTOK_SHOP_WEBHOOK_VERIFY_SIGNATURE=false
```

## Signature

- Method: `POST`, body = raw JSON (do not re-encode before verify)
- Header: `Authorization` = lowercase hex **HMAC-SHA256**, key = `app_secret`, message = `app_key` + raw body (no separator)
- On mismatch: **401** empty body (TikTok may retry)
- On success: **200** empty body within ~3s

## Migration

```bash
php artisan migrate --path=database/migrations/2026_11_21_120000_create_tiktok_shop_webhook_inbox_table.php
```

## Inspecting events

```bash
php artisan tinker --execute="App\Models\TikTokShopWebhookInbox::query()->latest('id')->limit(5)->get(['id','event_type','order_id','order_status','tts_notification_id','created_at']);"
```

Idempotency: duplicate deliveries with the same `tts_notification_id` keep a single inbox row.

## Sanity curl (expect 401 without Authorization)

```bash
curl -sS -o /dev/null -w "%{http_code}\n" -X POST "https://your-host/tiktok-shop/webhook" \
  -H "Content-Type: application/json" -d '{}'
```

## Partner Center setup (checklist)

1. Open [Partner Center](https://partner.tiktokshop.com) → **App & Service** → your Aria app.
2. Ensure **Order** (and Events) API scopes are approved for the shop cipher on aria3.
3. **Webhooks** → set callback URL to `https://{host}/tiktok-shop/webhook`.
4. Subscribe **`ORDER_STATUS_CHANGE`** (add others later if needed).
5. Run the migration on the host TikTok calls; keep `TIKTOK_SHOP_WEBHOOK_VERIFY_SIGNATURE=true`.
6. Trigger a test notification or wait for a real order transition; confirm a row in `tiktok_shop_webhook_inbox`.

## What’s next (not in this PR)

- Shadow compare to `jubelioorders` (like Shopee omnichannel) — read-only `GET` order detail behind a separate flag, default off.
- Optional cron poll as backup (TikTok docs: do not rely on webhooks alone).
- Post path / stock push — separate features, not from this webhook handler.