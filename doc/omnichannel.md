# Omnichannel dashboard (phase 1)

## Sidebar navigation (v1 unified group)

Collapsible **Omnichannel** menu (like Transactions):

| Label | Route | Permission |
|-------|-------|------------|
| **Orders** | `GET /omnichannel/orders` | `omnichannel-orders` or `omnichannel-view` |
| **Webhook Inbox** | `GET /marketplace/webhooks` | `marketplace-webhook-view`, `tiktok-shop-view`, or `omnichannel-view` |
| **Compare** | `GET /omnichannel` | `omnichannel-view` (shadow Shopee vs Jubelio — kept separate from Orders) |
| **Settings** | `GET /omnichannel/settings?tab=…` | `omnichannel-settings` or `omnichannel-view` |

**Compare vs Orders:** **Orders** reads `marketplace_channel_orders` (all channels, webhook-derived state). **Compare** remains the Shopee shadow/Jubelio line-compare dashboard on `marketplace_order_shadows` — not folded into Orders so lazy API enrich on shadow detail stays isolated.

### Orders lazy detail

- List: summary columns only from `marketplace_channel_orders` (no Shopee/TikTok/Jubelio API on first paint).
- Expand row or `GET /omnichannel/orders/{channel}/{order_ref}` (HTML fragment or `?format=json`) loads webhook events, stored shadow snippet, ingress timeline, and Jubelio invoice links from DB only.

### Settings tabs

- **Warehouse mapping** — read-only preview of `jubeliosyncs` / `shopeesyncs` with links to full Jubelio/Shopee map pages (`jubelio-sync`, `shopee-stock-sync`).
- **Low stock (Shopee)** — persisted `omnichannel_settings.shopee_low_stock_threshold` (v1: threshold + explanation only; no outbound stock push).
- **Connections** — read-only OAuth/status cards + reconnect links to existing authorize routes.

Permissions: `omnichannel-view`, `omnichannel-orders`, `omnichannel-settings`, `omnichannel-settings-edit` via `App\Models\Omnichannel` + `PermissionGenerator`.

**Legacy route:** `/omnichannel` (shadow compare index)

**Read-only guarantee (phase 1):** Shopee push **immediately upserts** a minimal `marketplace_order_shadows` row on HTTP (order status push code 3). **Full** Shopee `get_order_detail` + Jubelio line compare runs **on demand** when ops open `/omnichannel/shopee/{ordersn}` or post **Refresh** — not on every push. Neither path creates `transactions`, changes `warehouse_item`, inserts/updates `jubelioorders`, or calls `ProcessJubelioOrder`. Jubelio webhooks, cron, and manual Process are unchanged.

Disable shadow entirely: `SHOPEE_PUSH_SHADOW_ENABLED=false` (push callback still returns 200).

Jubelio remains the posting path. Shopee **Order Status push (3)**:

1. **Sync stub** — upsert `marketplace_order_shadows` with `ordersn`, `shop_id`, push status, `compare_status=pending` (no queue worker; list may show `belum dimuat` for lines until detail is opened).
2. **Lazy enrich** — `OmnichannelController@showShopee` (and `refreshShopee`) calls `ShopeeOrderShadowRecorder::recordFromPush` → Shopee `get_order_detail`, line summary, Jubelio compare (`SP-{ordersn}`). Cached detail reuses the row for `SHOPEE_PUSH_SHADOW_DETAIL_TTL_SECONDS` (default 300).

Detail page: `/omnichannel/shopee/{ordersn}` — **Refresh** forces a new API fetch.

## Requirements

- `SHOPEE_PUSH_*` + verified callback
- `SHOPEE_PUSH_SHADOW_ENABLED=true` (default)
- Shopee **Admin** OAuth (`shopee_admin_oauth` setting / `SHOPEE_ADMIN_*`) for `get_order_detail`, **Shopee Ads** API, and **stock / product** shop APIs on aria3 — one authorize covers all three UIs.
- **CORENATION ADMIN (aria3):** CDN relay `shopeecoreadminbot.php` → `/shopee-admin/oauth/callback`; env `SHOPEE_ADMIN_PARTNER_ID=2053195`, `SHOPEE_ADMIN_REDIRECT_URL` = CDN URL (see `doc/shopee-push-test.md`). Legacy relays `shopeebot.php` / `shopeestockbot.php` callbacks still work but also persist tokens under `shopee_admin_oauth`.
- Queue worker **not required** for shadow list or detail (sync fetch on detail view)

## Migrations

Run **both** paths on production (order matters: base table first):

```bash
php artisan migrate --path=database/migrations/2026_11_19_150000_create_marketplace_order_shadows_table.php
php artisan migrate --path=database/migrations/2026_11_20_100000_add_line_fields_to_marketplace_order_shadows.php
```

Optional inbox (debug / audit of raw pushes):

```bash
php artisan migrate --path=database/migrations/2026_11_19_140000_create_shopee_push_inbox_table.php
```

## Permissions

Assign `omnichannel-view` to roles that may open `/omnichannel` and Shopee Admin OAuth (`shopee-admin/authorize`). Generate the permission via `PermissionGenerator` if it is not already in `aria_permissions`. Superadmin bypasses as usual. Jubelio order pages still require `jubelio-view`; the omnichannel dashboard may show a Jubelio link only when the viewer has that permission separately.

## Line compare (phase 1b)

Shadow rows store **Shopee `item_list`** summary (`line_summary`: SKU, qty, amount) and, when a matching `jubelioorders` SELL exists, a read-only Jubelio API payload line summary (`jubelio_line_summary`). Line qty is compared via Aria `items.jubelio_item_id` (Jubelio) and `items.code` / `legacy_code` (Shopee SKU). **No** `ProcessJubelioOrder`, stock, or transaction writes.

- **Index:** `/omnichannel` — filters for overall compare and line compare; badges per row.
- **Detail:** `/omnichannel/shopee/{ordersn}` — Shopee JSON snippet, line tables, link to Jubelio order show (when permitted).

## Push code 0 vs code 3

| Code | Shopee Console action | `shopee_push_inbox` | `marketplace_order_shadows` |
|------|----------------------|---------------------|-----------------------------|
| **0** | URL **Verify** / connectivity sample | Yes, when `SHOPEE_PUSH_STORE_INBOX=true` and code listed in `SHOPEE_PUSH_STORE_INBOX_CODES` (default `0,3,4`) | **No** — not an order event |
| **3** | **Order status push** / **Push Test Data → order_status_push** | Optional (same env) | **Yes** when `SHOPEE_PUSH_SHADOW_ENABLED=true` and `data.ordersn` present |

Console **Verify** succeeding with inbox rows but an empty omnichannel table is normal until a **code 3** push arrives (or you simulate one below).

Console **Push Test Data** uses sample ordersn `220810QSK8S7BX` by default; stubs are tagged `compare_note=shopee_test_push` (configurable via `SHOPEE_PUSH_TEST_ORDERSN`).

## Maintainer simulate (no HTTP, no Jubelio post)

After migrations and deploy:

```bash
php artisan shopee:simulate-order-status-push
```

Options: `--ordersn=`, `--status=PROCESSED`, `--shop-id=`, `--dispatch-job` (optional queue enrich via `RecordShopeePushShadowJob` — normally use detail page instead).

The command upserts the same sync stub as the webhook, prints the canonical JSON body and `Authorization` signature for manual curl debugging.

## Troubleshooting (production)

After a Shopee order push:

```sql
SELECT COUNT(*) FROM shopee_push_inbox;   -- only if SHOPEE_PUSH_STORE_INBOX=true
SELECT COUNT(*) FROM marketplace_order_shadows;
```

| Symptom | Likely cause |
|---------|----------------|
| Inbox count grows, shadows stay 0 | Only **code 0** verify hits, or `SHOPEE_PUSH_SHADOW_ENABLED=false`, migration not run, or push code ≠ 3 / missing `data.ordersn` |
| Both counts 0 | No HTTP hits to `/shopee/push` (Console callback URL, firewall, or signature 401 — check `laravel.log` for `Shopee push rejected`) |
| List shows order but lines **belum dimuat** | Expected until someone opens detail (needs Shopee Admin OAuth for `get_order_detail`) |
| Compare stays **pending** after opening detail | Shopee OAuth missing / API error — check `compare_note` for `shopee_api_fetch_failed` |
| Green omnichannel cards, empty table | Dashboard cards read Jubelio/Shopee **connection** settings, not shadow rows — confirm `marketplace_order_shadows` count or run simulate command |

Verified pushes log **`Shopee push accepted`** with `code`, `ordersn`, and `shadow_stub_upserted` (true when stub row written).

## Later phases

- TikTok / Tokopedia channels on same table
- Channel-specific post flag (cutover from Jubelio)
- Stock push status per channel on the dashboard