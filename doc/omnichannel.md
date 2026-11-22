# Omnichannel dashboard (phase 1)

## Sidebar navigation (v1 unified group)

Collapsible **Omnichannel** menu (like Transactions):

| Label | Route | Permission |
|-------|-------|------------|
| **Orders** | `GET /omnichannel/orders` | `omnichannel-orders`, `omnichannel-view`, `marketplace-webhook-view`, or `tiktok-shop-view` |
| **Compare** | `GET /omnichannel` | `omnichannel-view` (shadow Shopee vs Jubelio — kept separate from Orders) |
| **Settings** | `GET /omnichannel/settings?tab=…` | `omnichannel-settings` or `omnichannel-view` |

**Legacy redirect:** `GET /marketplace/webhooks` and `GET /tiktok-shop/webhooks` redirect to **Omnichannel** (`/omnichannel/orders`) with the full query string preserved (including `view=events|orders`).

**Orders / Webhooks UI:** `GET /omnichannel/orders` defaults to **`view=events`** so every stored webhook (including non-order pushes) is visible first. Switch to **Current orders** for the `marketplace_channel_orders` summary; expand an order row for lazy detail. Channel filter **Tokopedia (TikTok Partner)** matches rows with `commerce_platform=TOKOPEDIA` (case-insensitive) on unified tables; **TikTok Shop** excludes Tokopedia-tagged rows.

**Unified storage flag:** `MARKETPLACE_WEBHOOK_UNIFIED_STORAGE` (`config/marketplace.php`) — default stays **off** in production until maintainer enables dual-write; the UI reads unified tables whenever rows exist (backfill or flag on). Do not change the production default in code.

**Compare vs Orders:** **Orders** reads `marketplace_channel_orders` (all channels, webhook-derived state). **Compare** remains the Shopee shadow/Jubelio line-compare dashboard on `marketplace_order_shadows` — not folded into Orders so lazy API enrich on shadow detail stays isolated.

### Orders lazy detail

- **Events view:** list from `marketplace_webhook_events` only — no marketplace API on first paint; inline payload expand is read-only JSON.
- **Orders view:** summary from `marketplace_channel_orders` (no Shopee/TikTok/Jubelio API on first paint).
- Expand row or `GET /omnichannel/orders/{channel}/{order_ref}` (HTML fragment or `?format=json`) loads webhook events, stored shadow snippet, ingress timeline, and Jubelio invoice links from DB. For TikTok orders, when `commerce_platform` is empty, a **read-only** TikTok Get Order Detail call may run once (`TikTokShopOpenApiService`) and persist `commerce_platform` on the channel order + matching events — no stock or transaction writes.

**TikTok ingest-time enrich (Tokopedia label):** When `TIKTOK_SHOP_ENRICH_COMMERCE_PLATFORM=true` (default), each TikTok Shop webhook with `order_id` dispatches `EnrichMarketplaceTikTokCommercePlatformJob` after unified storage writes — so Orders / Events lists can show **Tokopedia** without opening detail first. Requires `TIKTOK_SHOP_ACCESS_TOKEN` on `TIKTOK_SHOP_DRIVER=live`; mock driver works without a token. Backfill existing rows: `php artisan marketplace:enrich-tiktok-commerce-platform` (`--order=`, `--limit=`, `--since=`).

**TikTok ingest without `tts_notification_id`:** unified recorder and legacy inbox use idempotency `tiktok:body:` + SHA-256 prefix of the raw JSON body (warning logged).

### Settings tabs

- **Warehouse mapping** — read-only preview of `jubeliosyncs` / `shopeesyncs` with links to full Jubelio/Shopee map pages (`jubelio-sync`, `shopee-stock-sync`). **Tokopedia (via Jubelio)** highlights matching `jubeliosyncs` rows and optional maintainer notes (`tokopedia_store_id`, `tokopedia_notes` in `omnichannel_settings`).
- **Low stock (Shopee)** — persisted `omnichannel_settings.shopee_low_stock_threshold` (v1: threshold + explanation only; no outbound stock push).
- **Connections** — read-only OAuth/status cards + reconnect links to existing authorize routes.

Permissions: `omnichannel-view`, `omnichannel-orders`, `omnichannel-settings`, `omnichannel-settings-edit` via `App\Models\Omnichannel` + `PermissionGenerator`.

**Legacy route:** `/omnichannel` (shadow compare index)

**Read-only guarantee (phase 1):** Shopee push **immediately upserts** a minimal `marketplace_order_shadows` row on HTTP (order status push code 3). **Full** Shopee `get_order_detail` + Jubelio line compare runs **on demand** when ops open `/omnichannel/shopee/{ordersn}` or post **Refresh** — not on every push. This path never creates `transactions`, changes `warehouse_item`, or calls `ProcessJubelioOrder`.

Disable shadow entirely: `SHOPEE_PUSH_SHADOW_ENABLED=false` (push callback still returns 200).

### Optional Jubelio queue pre-create (`SHOPEE_PRECREATE_JUBELIO_QUEUE`)

When **`SHOPEE_PRECREATE_JUBELIO_QUEUE=true`** (default **false**), push code **3** with Shopee status in the **awaiting shipment** bucket (`READY_TO_SHIP`, `PROCESSED`, `RETRY_SHIP` by default; override with `SHOPEE_PRECREATE_TRIGGER_STATUSES`) upserts a **`jubelioorders` SELL placeholder**:

| Field | Value |
|-------|--------|
| `invoice` | `SP-{ordersn}` (same as Jubelio / resi scan) |
| `jubelio_order_id` | `shopee-push:{ordersn}` until Jubelio webhook |
| `source` | `4` (Shopee push) |
| `order_status` | `READY TO SHIP` (cron hold — not eligible to post until webhook sets SHIPPED/COMPLETED) |
| `warehouse_id` / store/loc | Best effort from `shopee_syncs` + `jubeliosyncs`; may stay 0 until webhook |

Console test pushes and verify-only statuses are skipped. Jubelio **webhook** promotes a placeholder row (updates `jubelio_order_id` + shipped status) instead of inserting a duplicate. Poll/get-orders and `JubelioSellInvoiceGuard` treat the invoice as already queued. **Still no SELL transaction on push alone.**

When the push payload includes `item_list` and `shopee_syncs` resolves a warehouse, pre-create also increments **`warehouse_item.on_order`** (additive migration) — **not** physical `quantity`. SELL posting via `InventoryService::deduct` releases matching on_order as stock deducts.

#### Dedupe with mobile AWB / resi scan

| Path | Route / code |
|------|----------------|
| Mobile API | `POST /api/mobile/v1/jubelio/resi-scan` → `MobileJubelioResiScanController@store` |
| Service | `JubelioResiScanService::queueInvoices` (AWB → Jubelio tracking lookup → invoice `SP-{…}`) |

All paths key on the same **`SP-{ordersn}`** invoice string (Shopee marketplace config in `jubelio_resi_scan.php`; Lazada/TikTok may append store suffix).

| Order of events | Behavior |
|-----------------|----------|
| Pre-create first, AWB scan later | Scan **does not insert**; existing row appears in API `updated[]` (`READY TO SHIP`, `user_id`, warehouse/sync, promotes placeholder `jubelio_order_id` when Jubelio resolves AWB). |
| AWB scan first (no pre-create) | Scan **creates** `source=3` row. Later push pre-create **upserts** by invoice — no second row. |
| Jubelio webhook SHIPPED/COMPLETED | Promotes placeholder — no duplicate insert. |
| Posted sell `transactions` | Scan **skips** (invoice already in ledger). |

Response includes `created`, `updated`, and `skipped` arrays.

**Stock:** Pre-create, resi scan queue, and push callback **do not** change `warehouse_item.quantity`. Physical deduct remains `ProcessJubelioOrder` after SHIPPED.

### Aria on-order qty (`warehouse_item.on_order`)

| Layer | Behaviour |
|-------|------------|
| Schema | `warehouse_item.on_order` decimal, default `0` — migrate `2026_11_22_150000_add_on_order_to_warehouse_item.php` |
| Reserve | Shopee pre-create (new placeholder only) + optional `item_list` on push |
| Release | Physical deduct path in `InventoryService` (Jubelio SELL post) |
| UI | Gudang **Items** list shows **On order** column (Aria, not Jubelio API) |

TikTok / Tokopedia push on_order hooks are deferred — warehouse mapping for those channels still flows through **Jubelio** `jubeliosyncs` until a dedicated map exists.

### Warehouse mapping audit (v1)

| Channel → Aria gudang | Authoritative table | Notes |
|----------------------|---------------------|--------|
| Jubelio (all marketplaces) | `jubeliosyncs` (`jubelio_store_id` + `jubelio_location_id` → `warehouse_id` + `customer_id`) | Inbound SELL/RETURN posting, resi scan store pick |
| Shopee shop | `shopee_syncs.shop_id` → `warehouse_id` | Push pre-create warehouse, stock sync UI (`shopee-stock-sync`) |
| Shopee ↔ Jubelio | Same physical `warehouse_id` on both sync rows | Pre-create fills Jubelio store/loc from first `jubeliosyncs` on that gudang |
| TikTok Shop | No `tiktok_syncs` warehouse map in v1 | Webhook `shop_ref`; fulfillment uses Jubelio after invoice exists |
| Tokopedia (TikTok Partner) | `omnichannel_settings.tokopedia_store_id` + highlighted `jubeliosyncs` | Settings tab notes; same Jubelio posting path |

Do not duplicate Jubelio’s store/location map inside Shopee-only tables — link shops to the **same** Aria warehouse already wired for Jubelio cron.

Jubelio remains the posting path. Shopee **Order Status push (3)**:

1. **Sync stub** — upsert `marketplace_order_shadows` with `ordersn`, `shop_id`, push status, `compare_status=pending` (no queue worker; list may show `belum dimuat` for lines until detail is opened).
2. **Lazy enrich** — `OmnichannelController@showShopee` (and `refreshShopee`) calls `ShopeeOrderShadowRecorder::recordFromPush` → Shopee `get_order_detail`, line summary, Jubelio compare (`SP-{ordersn}`). Cached detail reuses the row for `SHOPEE_PUSH_SHADOW_DETAIL_TTL_SECONDS` (default 300).
3. **Pre-create (flag)** — `ShopeeJubelioQueuePrecreator` after shadow stub; see table above.

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

## Webhook status mapping (UI + ingest)

Raw platform strings are stored on `marketplace_webhook_events.order_status` / `marketplace_channel_orders.order_status`. Display and coarse ops buckets use `App\Services\Marketplace\MarketplaceWebhookStatusPresenter` + `config/marketplace.php` → `webhook_status_buckets` (no extra DB column).

| Channel | Event type | Payload field | Stored `order_status` |
|---------|------------|---------------|------------------------|
| Shopee | **3** `order_status_push` | `data.status` (e.g. `UNPAID`, `READY_TO_SHIP`, `SHIPPED`) | Same string |
| Shopee | **4** `order_trackingno_push` | `data.tracking_no` (optional `package_number`) — **no** `data.status` | `tracking:{tracking_no}` (e.g. `tracking:SPX123456789`) |
| TikTok Shop | **1** order status | `data.order_status` (e.g. `UNPAID`, `AWAITING_SHIPMENT`) | Same string |

**Shopee code 4:** Events list shows **Tracking** as the event label and **TRACKING · {no}** in the status column (bucket `tracking_update`). Unified ingest still writes the event row, but **`marketplace_channel_orders.order_status` is not overwritten** by tracking-only pushes — the last real order-status push (code 3 or TikTok type 1) stays on the order summary.

Bucket examples: Shopee `READY_TO_SHIP` → `awaiting_shipment`; TikTok `AWAITING_SHIPMENT` → `awaiting_shipment`; Tokopedia rows (`commerce_platform=TOKOPEDIA`) use the TikTok vocabulary map.

## Push code 0 vs code 3

| Code | Shopee Console action | `shopee_push_inbox` | `marketplace_order_shadows` |
|------|----------------------|---------------------|-----------------------------|
| **0** | URL **Verify** / connectivity sample | Yes, when `SHOPEE_PUSH_STORE_INBOX=true` and code listed in `SHOPEE_PUSH_STORE_INBOX_CODES` (default `0,3,4`) | **No** — not an order event |
| **3** | **Order status push** / **Push Test Data → order_status_push** | Optional (same env) | **Yes** when `SHOPEE_PUSH_SHADOW_ENABLED=true` and `data.ordersn` present |
| **4** | **Order tracking number push** | Optional (same env) | **No** — tracking only; see table above |

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