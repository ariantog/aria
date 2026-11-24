# Omnichannel dashboard (phase 1)

## Sidebar navigation (v1 unified group)

Collapsible **Omnichannel** menu (like Transactions):

| Label | Route | Permission |
|-------|-------|------------|
| **Orders** | `GET /omnichannel/orders` | `omnichannel-orders`, `omnichannel-view`, or `tiktok-shop-view` |
| **Compare** | `GET /omnichannel` | `omnichannel-view` (shadow Shopee vs Jubelio — kept separate from Orders) |
| **Settings** | `GET /omnichannel/settings?tab=…` | `omnichannel-settings` or `omnichannel-view` |

**Legacy redirect:** `GET /marketplace/webhooks` and `GET /tiktok-shop/webhooks` redirect to **Omnichannel** (`/omnichannel/orders`) with the full query string preserved (including `view=events|orders`).

**Orders / Webhooks UI:** `GET /omnichannel/orders` defaults to **`view=events`** so every stored webhook (including non-order pushes) is visible first. Switch to **Current orders** for the `marketplace_channel_orders` summary; expand an order row for lazy detail. Channel filter **Tokopedia (TikTok Partner)** matches rows with `commerce_platform=TOKOPEDIA` (case-insensitive) on unified tables; **TikTok Shop** excludes Tokopedia-tagged rows.

**Unified storage flag:** `MARKETPLACE_WEBHOOK_UNIFIED_STORAGE` (`config/marketplace.php`) — default stays **off** in production until maintainer enables dual-write; the UI reads unified tables whenever rows exist (backfill or flag on). Do not change the production default in code.

**Compare vs Orders:** **Orders** reads `marketplace_channel_orders` (all channels, webhook-derived state). **Compare** remains the Shopee shadow/Jubelio line-compare dashboard on `marketplace_order_shadows` — not folded into Orders so lazy API enrich on shadow detail stays isolated.

### Orders lazy detail

- **Events view:** list from `marketplace_webhook_events` only — no marketplace API on first paint; inline payload expand is read-only JSON.
- **Orders view:** summary from `marketplace_channel_orders` (no Shopee/TikTok/Jubelio API on first paint).
- Expand row or `GET /omnichannel/orders/{channel}/{order_ref}` (HTML fragment or `?format=json`) loads webhook events, stored shadow snippet (Shopee), ingress timeline, and Jubelio invoice links from DB. **TikTok:** on expand, a read-only **Get Order Detail** (`TikTokShopOpenApiService`) resolves `shop_cipher` from `tiktok_shop_syncs` for the latest webhook `shop_ref` (fallback `TIKTOK_SHOP_SHOP_CIPHER`), returns `line_summary` (SKU × qty), a compact `channel_order` snippet (status, platform, payment/total when present), `api_detail_loaded` / `api_error` on credential or HTTP failure (webhooks still shown). Persist `commerce_platform` when the API returns it — no `warehouse_item` or transaction writes. **Events view** keeps raw payload only; TikTok events with `order_ref` show a hint to open **Current orders** for line detail.

### AWB print → Jubelio queue (Shopee, OMN-66)

After a successful **Cetak AWB (Shopee)** (`POST /omnichannel/orders/shipping-documents`), Aria upserts a **`jubelioorders` SELL** row per printed order (`SP-{ordersn}`, `source = 4` shopee push placeholder) when the row does not already exist — same invoice key as resi scan / optional push pre-create. **No SELL transaction** is posted.

When Jubelio cron posts that sell (`submit_type = 2`), `TransactionService` deducts physical stock and **releases matching `warehouse_item.on_order`** for **Shopee invoices only** (`SP-…` / flexi prefixes, or queue `source = shopee push`). Safe to enable `SHOPEE_PUSH_ON_ORDER_ENABLED=true` once this ships on production.

### Bulk AWB print (Shopee, v1)

**Current orders** only (`view=orders`): checkbox selection + **Cetak AWB (Shopee)** → `POST /omnichannel/orders/shipping-documents` (`omnichannel.orders.shipping-documents`). Permission: `omnichannel-orders` / `Omnichannel::userCanOrders`.

- **v1 channel support:** Shopee rows only (TikTok/Tokopedia skipped with flash message). Uses **Shopee Admin Open API** logistics: `create_shipping_document` → poll `get_shipping_document_result` (≤30s) → `download_shipping_document`. Batches ≤50 orders; grouped per `shop_id` from `shop_ref` + OAuth/`shopee_syncs`. Multi-package orders resolve `package_number` via `get_order_detail` (`package_list`); empty `package_number` is never sent.
- **Response:** single-shop / single batch → one PDF download; multiple shops or >50 orders → ZIP of PDFs.
- **Config:** `SHOPEE_SHIPPING_DOCUMENT_TYPE` (default `NORMAL_AIR_WAYBILL`) on `config('services.shopee_admin.shipping_document_type')`.
- **Code:** `App\Services\Shopee\ShopeeShippingDocumentService`, `OmnichannelOrdersController@downloadShippingDocuments`.

**TikTok shop access token (live driver):** Same pattern as Shopee — Aria stores seller OAuth in `settings` slug **`tiktok_shop_oauth`** (`TikTokShopOAuthService`), refreshes via TikTok `token/refresh`, and serves read-only tokens to Grok at **`GET /api/readonly/tiktok/oauth`** (`doc/grok-readonly-api.md`). Authorize in UI: **`GET /tiktok-shop/authorize`** (Omnichannel → Settings → Connections). Callback: **`/tiktok-shop/oauth/callback`** (`TIKTOK_SHOP_REDIRECT_URL` must match Partner Center). **`shop_cipher`** disimpan per toko di `marketplace_warehouse_syncs` (`channel=tiktok`) dan legacy `tiktok_shop_syncs`, dengan env fallback `TIKTOK_SHOP_SHOP_CIPHER`. `TIKTOK_SHOP_ACCESS_TOKEN` is emergency-only fallback. Grok needs `export-readonly` + `api.readonly.tiktok.oauth`.

**Marketplace gudang matrix (`/marketplace-warehouse-sync`):** Tab **Shopee** \| **TikTok / Tokopedia** — satu baris per gudang platform di `marketplace_warehouse_syncs`. Install migration **backfill** dari `shopee_syncs` (legacy table tetap). Resolver: `MarketplaceWarehouseSyncResolver` + omnichannel **gudang** column; fallback `shopee_syncs` / `tiktok_shop_syncs`. Jubelio `jubeliosyncs` tidak berubah. Lihat `doc/tiktok-shop-webhook.md`.

| Aria env | Purpose |
|----------|---------|
| `TIKTOK_SHOP_APP_KEY` / `TIKTOK_SHOP_APP_SECRET` | Partner app (signing + token exchange) |
| `TIKTOK_SHOP_SERVICE_ID` | Seller authorization link |
| `TIKTOK_SHOP_REDIRECT_URL` | OAuth callback on aria3 |
| `TIKTOK_SHOP_ACCESS_TOKEN` | Static fallback only |

**Manual seed (no browser):** maintainer may `updateOrCreate` `settings` slug `tiktok_shop_oauth` with `access_token`, `refresh_token`, and `expires_at` (or `access_token_expire_in` unix); order detail and Grok readonly API pick it up. Re-authorize in UI when refresh expires.

**TikTok ingest-time enrich (Tokopedia label):** When `TIKTOK_SHOP_ENRICH_COMMERCE_PLATFORM=true` (default), each TikTok Shop webhook with `order_id` dispatches `EnrichMarketplaceTikTokCommercePlatformJob` after unified storage writes — so Orders / Events lists can show **Tokopedia** without opening detail first. On `TIKTOK_SHOP_DRIVER=live`, requires resolvable `access_token` (OAuth settings or env); mock driver works without a token. Backfill existing rows: `php artisan marketplace:enrich-tiktok-commerce-platform` (`--order=`, `--limit=`, `--since=`).

**TikTok ingest without `tts_notification_id`:** unified recorder and legacy inbox use idempotency `tiktok:body:` + SHA-256 prefix of the raw JSON body (warning logged).

### Settings tabs

- **Warehouse mapping** — read-only preview of `jubeliosyncs` / `shopeesyncs` with links to full Jubelio/Shopee map pages (`jubelio-sync`, `shopee-stock-sync`). **Tokopedia (via Jubelio)** highlights matching `jubeliosyncs` rows and optional maintainer notes (`tokopedia_store_id`, `tokopedia_notes` in `omnichannel_settings`).
- **Low stock (Shopee)** — persisted `omnichannel_settings.shopee_low_stock_threshold` (v1: threshold + explanation only; no outbound stock push).
- **Connections** — read-only OAuth/status cards + reconnect links to existing authorize routes.

**aria3 maintenance (`omnichannel_settings` missing but `2026_11_22` already migrated):** `php artisan migrate --path=database/migrations/2026_11_23_120000_install_omnichannel_settings_table.php --force` — guarded CREATE only; FLEXI staging / low-stock / Tokopedia values are saved from Settings **Simpan**, not separate migrations or env.

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
| AWB scan without queue row | **Shopee:** no insert (pre-create/webhook first). **TikTok/Tokopedia:** may insert `READY TO SHIP` after Jubelio AWB resolves (`insert_queue_row_on_scan`). |
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
| TikTok Shop / Tokopedia (Partner) | `tiktok_shop_syncs.shop_ref` → gudang Aria | Webhook `shop_id` as `shop_ref`; optional `shop_cipher` for Open API; Kelola di **Omnichannel → Settings → Warehouse** or `/tiktok-shop-sync` |
| Tokopedia (TikTok Partner) | `omnichannel_settings.tokopedia_store_id` + highlighted `jubeliosyncs` | Settings tab notes; same Jubelio posting path |

Do not duplicate Jubelio’s store/location map inside Shopee-only tables — link shops to the **same** Aria warehouse already wired for Jubelio cron.

### Shopee FLEXI / Malaysia (no Jubelio webhook)

Jubelio may not enqueue **export / FLEXI Malaysia** orders. Aria can ingest them **directly from Shopee Open Platform**:

1. **Authorize** via **Main account** (not Direct Shop login — Shopee rejects Direct-only OAuth). Omnichannel → Settings → Connections → Shopee Admin; pilih Main + Direct (MY). Callback menyimpan `shop_id_list` di settings dan menampilkan semua Shop ID di tab Connections (tidak perlu menyalin URL redirect).
2. **FLEXI shops** (`/shopee-flexi`): konfigurasi **`shop_id` + invoice prefix** (SPMY / SPSG / …) dan flag poll shadow. Satu **gudang staging** shared untuk semua shop FLEXI: **hanya** di **Omnichannel → Settings → Warehouse** (`OmnichannelSettingStore::KEY_FLEXI_STAGING_WAREHOUSE_ID` pada tabel generik `omnichannel_settings` — tidak ada migrasi khusus FLEXI; key ditulis lewat UI save). UI FLEXI menampilkan nama gudang + peringatan bila belum diset / bukan tipe warehouse.
3. **FLEXI orders** (`/shopee-flexi/orders`): alur operasi bertahap:
   1. Cari order + **muat detail** Shopee API (`get_order_detail`) — dari **shop affiliate** (MY).
   2. **Pindah ke gudang FLEXI** — POST membangun draft **MOVE** (pickup gudang dari warehouse map → staging) via `PrepareMoveTransactionDraft` → form `transactions/move/create`. Staging harus **`arrangement_enabled=true`** kecuali ID gudang sama dengan staging FLEXI di omnichannel settings (exception di `PrepareMoveTransactionDraft` via `ShopeeFlexiStagingWarehouse`).
   3. Tunggu order **`COMPLETED`** + **`get_escrow_detail`** usable (`amountsUsableForSellPrefill`: `exchange_rate`, **`escrow_amount_pri` (IDR tanpa kurs)**, atau nominal IDR domestic — **bukan** catalog fallback saja).
   4. **Buat Transaksi Manual** → form SELL terprefill **IDR**; bila staging dikonfigurasi, **sender = gudang staging** (bukan pickup), receiver = customer channel Jubelio map. **Tidak ada antrean Jubelio** untuk shop di `shopee_flexi_shops`.
   - Order Malaysia membayar **MYR**; Aria transaksi pakai **IDR**. **Escrow** sering dari **shop primary Indonesia** (`ShopeeAdminOAuthStore::primaryShopId`) dengan ref wallet **`MY-{ordersn}`** (bukan prefix invoice `SPMY-`).
   - `get_escrow_detail` dicoba berurutan: affiliate shop + ordersn → affiliate + `MY-{sn}` → primary shop + variasi SN yang sama; berhenti pada `order_income` usable.
   - Penyelesaian IDR: `exchange_rate` > 0 (SIP/CB, MYR×rate), atau **`escrow_amount_pri` / IDR escrow** tanpa kurs (FLEXI export / dana di wallet seller ID) — alokasi proporsional ke baris (atau breakdown `items` jika API mengembalikan).
   - Hanya jika escrow/kurs benar-benar kosong: **jangan** memprefill MYR sebagai IDR — qty + SKU dari Shopee, harga dari **`items.price`** katalog Aria (tidak memenuhi gate SELL).
4. **Warehouse Map** (`/shopee-sync`): Shopee pickup → Aria gudang (stok).
5. **Poll opsional** (`SHOPEE_ORDER_POLL_ENABLED`, cron `shopee:poll-orders`): untuk shop FLEXI hanya **shadow + unified webhook events** — **tidak** membuat baris `jubelioorders`. Push webhook precreate Jubelio juga dilewati untuk `shop_id` FLEXI. CLI `shopee:pull-order` sama (shadow/events saja). Default poll status **tidak** menyertakan `RETRY_SHIP` (valid di push/pre-create, invalid di `get_order_list`).
6. Optional: register **Push** for the export `shop_id` in Shopee Console (same `/shopee/push` path as Indonesia).

**SELL posting** untuk export = form manual di FLEXI orders; Jubelio cron tidak pernah menerima payload MY/export.

## Shopee push path

Jubelio remains the default posting path for Indonesia Shopee. Shopee **Order Status push (3)**:

1. **Sync stub** — upsert `marketplace_order_shadows` with `ordersn`, `shop_id`, push status, `compare_status=pending` (no queue worker; list may show `belum dimuat` for lines until detail is opened).
2. **Lazy enrich** — `OmnichannelController@showShopee` (and `refreshShopee`) calls `ShopeeOrderShadowRecorder::recordFromPush` → Shopee `get_order_detail`, line summary, Jubelio compare (`{prefix}-{ordersn}` from `shopee_syncs`). Cached detail reuses the row for `SHOPEE_PUSH_SHADOW_DETAIL_TTL_SECONDS` (default 300).
3. **Pre-create (flag)** — `ShopeeJubelioQueuePrecreator` after shadow stub; see table above.

Detail page: `/omnichannel/shopee/{ordersn}` — **Refresh** forces a new API fetch.

## Requirements

- `SHOPEE_PUSH_*` + verified callback
- `SHOPEE_PUSH_SHADOW_ENABLED=true` (default)
- Shopee **Admin** OAuth (`shopee_admin_oauth` setting / `SHOPEE_ADMIN_*`) for `get_order_detail`, **Shopee Ads** API, and **stock / product** shop APIs on aria3 — one authorize covers all three UIs.
- **CORENATION ADMIN (aria3):** Live Redirect URL = `https://aria3.corenationactive.com/shopee-admin/oauth/callback` (Shopee rejects `bot` and `.php` in redirect URLs — no CDN relay); env `SHOPEE_ADMIN_PARTNER_ID=2053195`, `SHOPEE_ADMIN_REDIRECT_URL` must match Console exactly (see `doc/shopee-push-test.md`).
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
| Shopee | **30** `package_fulfillment_status_push` | `data.logistics_status` (e.g. `LOGISTICS_READY`) | `FULFILLMENT:{status}` |
| TikTok Shop | **1** order status | `data.order_status` (e.g. `UNPAID`, `AWAITING_SHIPMENT`) | Same string |

**Shopee code 4:** Events list shows **Tracking** as the event label and **TRACKING · {no}** in the status column (bucket `tracking_update`). Unified ingest still writes the event row, but **`marketplace_channel_orders.order_status` is not overwritten** by tracking-only pushes — the last real order-status push (code 3 or TikTok type 1) stays on the order summary.

Bucket examples: Shopee `READY_TO_SHIP` → `awaiting_shipment`; TikTok `AWAITING_SHIPMENT` → `awaiting_shipment`; Tokopedia rows (`commerce_platform=TOKOPEDIA`) use the TikTok vocabulary map.

### Channel order summary (`marketplace_channel_orders.order_status`)

Ops list **Current orders** shows one summary row per `(channel, order_ref)`. Webhook **events** keep every delivery; the summary applies the rules below so fulfillment/tracking pushes and out-of-order replays do not regress the ops status.

| Source | Updates `order_status`? | Notes |
|--------|-------------------------|--------|
| Shopee push **3** `data.status` | Yes | Lifecycle rank; newer/advanced status wins (e.g. `READY_TO_SHIP` not clobbered by later `UNPAID` replay). |
| TikTok type **1** `data.order_status` | Yes | Same rank rules (e.g. `AWAITING_SHIPMENT` over `UNPAID`). |
| Shopee push **4** tracking | **No** | Stored on `marketplace_webhook_events` only (`tracking:{no}`). |
| Shopee push **30** fulfillment | **No** | Stored on events as `FULFILLMENT:{logistics_status}`; does not replace order status. |
| Push **0** verify / non-order | No `order_ref` | No channel order row. |
| **Cancelled** (`CANCELLED`, `IN_CANCEL`, TikTok `CANCEL`, …) | Yes, terminal | Cancellation **wins** over non-cancelled statuses and **sticks** (later non-cancelled pushes ignored). |

**Ignored for ops queue (bucket `unpaid` / `cancelled` — no AWB, no pre-create Jubelio):** pure unpaid non-COD orders; any cancelled bucket. Pre-create and AWB still require Shopee Admin OAuth separately.

**COD / cash-on-delivery:** When webhook `data` indicates COD (`payment_method` / `cod` / `is_cod`, etc.), summary storage treats unpaid as processable: Shopee `UNPAID` → stored **`PROCESSED`**; TikTok `UNPAID` / `ON_HOLD` → **`AWAITING_SHIPMENT`**. Ops bucket becomes **Awaiting shipment** even before paid settlement.

**After Cetak AWB (Shopee):** Successful `POST /omnichannel/orders/shipping-documents` promotes each printed Shopee row to **`READY_TO_SHIP`** when current status is not shipped, completed, or cancelled (e.g. `PROCESSED` after AWB). No transactions or stock changes.

Code: `MarketplaceWebhookStatusPresenter` (ranks, COD, skip tracking/fulfillment), `MarketplaceWebhookRecorder::upsertChannelOrder`, `MarketplaceChannelOrderAwbPromoter`, `OmnichannelOrdersController@downloadShippingDocuments`.

## Push code 0 vs code 3

| Code | Shopee Console action | `shopee_push_inbox` | `marketplace_order_shadows` |
|------|----------------------|---------------------|-----------------------------|
| **0** | URL **Verify** / connectivity sample | Yes by default (`SHOPEE_PUSH_STORE_INBOX` true; set false to disable) | **No** — not an order event |
| **3** | **Order status push** / **Push Test Data → order_status_push** | Optional (same env) | **Yes** when `SHOPEE_PUSH_SHADOW_ENABLED=true` and `data.ordersn` present |
| **4** | **Order tracking number push** | Optional (same env) | **No** — tracking only; see table above |
| **30** | **Package fulfillment** (`logistics_status`) | Same inbox flag | **No** — observability only; unified storage when `MARKETPLACE_WEBHOOK_UNIFIED_STORAGE=true` |

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
SELECT COUNT(*) FROM shopee_push_inbox;   -- default on unless SHOPEE_PUSH_STORE_INBOX=false
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