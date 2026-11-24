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

## Warehouse mapping UI (unified `marketplace_warehouse_syncs`)

Shopee + TikTok/Tokopedia memakai **satu tabel** `marketplace_warehouse_syncs` (`channel` = `shopee` \| `tiktok`). UI: **`/marketplace-warehouse-sync?channel=tiktok`** (legacy `/tiktok-shop-sync` redirect).

Alur TikTok per **platform `warehouse_id`**:

1. **Tambah toko** — pilih OAuth → `shop_ref` / `shop_cipher`.
2. **Sinkron** — `GET /logistics/{version}/warehouses?shop_cipher=` via `MarketplaceWarehouseSyncService` → upsert (preserves mapped `warehouse_id`).
3. **Matrix** — `/marketplace-warehouse-sync/tiktok/{shop_ref}/matrix` — petakan `platform_warehouse_id` → gudang Aria; opsional `commerce_platform`.

| Partner API | Use |
|-------------|-----|
| `GET /authorization/202309/shops` | Dropdown toko pada create |
| `GET /logistics/202309/warehouses?shop_cipher=` | Sinkron + refresh matrix |

**Legacy:** `tiktok_shop_syncs` (satu gudang per shop) masih dipakai sebagai fallback resolver jika webhook/order tidak membawa `warehouse_id`. Halaman edit lama redirect ke matrix.

Tokopedia storefront pada seller yang sama memakai logistics TikTok — tidak ada endpoint terpisah. `driver=mock` mengembalikan fixture tanpa HTTP.

**Omnichannel gudang column:** `MarketplaceWebhookShopWarehouseResolver::resolveAriaWarehouseId()` memakai `(shop_ref, platform_warehouse_id dari payload, commerce_platform?)` bila tersedia.

## Troubleshooting sinkron gudang

Gejala umum di aria3: tombol **Sinkron gudang** kembali ke form dengan pesan generik, atau matrix tetap kosong setelah refresh — padahal dropdown **Toko (OAuth)** dari `GET /authorization/…/shops` berisi toko.

### Penyebab

1. **`GET /logistics/{version}/warehouses?shop_cipher=` gagal** — HTTP 4xx/5xx, atau JSON `code != 0` (scope tidak disetujui, token kadaluarsa, cipher salah).
2. **Scope OAuth hilang** — app belum disetujui untuk **`seller.logistics`** di [Partner Center](https://partner.tiktokshop.com) → App & Service → API scopes.
3. **Respons bentuk lain** — Aria juga mem-parsing daftar gudang langsung di `data` (array), bukan hanya `data.warehouses`.

Aria menyimpan detail error Partner terakhir di `TikTokShopOpenApiService::getLastOpenApiError()` (HTTP status, API code, message, path). Flash UI memuat ringkasan itu plus petunjuk re-OAuth.

### Langkah perbaikan

1. Partner Center → app Aria → pastikan scope **seller.logistics** (Logistics API) **Approved** untuk seller yang dipakai.
2. Aria → **Authorize ulang** TikTok Shop OAuth (`/tiktok-shop/authorize?return=tiktok-sync`) setelah scope baru disetujui.
3. Ulangi **Sinkron gudang** dari `/marketplace-warehouse-sync/tiktok/create`.
4. Jika API masih kosong tapi Anda punya `warehouse_id` dari webhook/order Partner: gunakan **Tambah gudang manual** di halaman create atau **Tambah baris manual** di matrix (isi `platform_warehouse_id`, mis. `7392489215199020821`, lalu petakan ke gudang Aria seperti CITOS).

### Verifikasi cepat (log)

Saat gagal, `storage/logs/laravel.log` memuat baris `TikTok Shop Open API GET` dengan `path` berisi `/logistics/…/warehouses`.

## Item SKU link (TikTok Shop + Tokopedia)

Aria stores Partner product identifiers on `items` (nullable, additive migration):

| Column | Meaning |
|--------|---------|
| `tiktok_shop_product_id` | TikTok Shop product ID |
| `tiktok_shop_sku_id` | SKU ID within that product (variant) |

Match at link time uses Partner **`seller_sku`** against `items.code` / `items.legacy_code` (same idea as Shopee `model_sku`).

**UI routes**

| Route name | Method | Notes |
|------------|--------|-------|
| `items.tiktok-shop` | `GET items/{item}/tiktok-shop` | Link status tab (with item tabs) |
| `items.tiktok-shop-search` | `GET items/{item}/tiktok-shop-search` | Product search (`?q=` seller SKU) |
| `items.tiktok-shop-link` | `POST items/{item}/tiktok-shop-link` | Save `tiktok_shop_product_id` + `tiktok_shop_sku_id` |

**Permissions:** `tiktok-shop-item-link` (new) or existing `tiktok-shop-view` to open tabs; `items-edit` to save links. After deploy: `php artisan tinker --execute="app(\App\Services\PermissionGenerator::class)->generateForModule('TikTokShop');"`.

**OAuth / API:** Product search calls `POST /product/{version}/products/search` with `shop_cipher` (from `tiktok_shop_syncs`, `marketplace_warehouse_syncs`, or `TIKTOK_SHOP_SHOP_CIPHER`). Required seller token scope: **`seller.product.basic`** (Product read/search). Tokopedia listings on the same Partner stack use the same columns and routes.

**Warehouse items page:** When a gudang has TikTok mapping, shows a banner count of page items missing `tiktok_shop_product_id` (mirror Shopee unlinked banner).

## What’s next (not in this PR)

- Shadow compare to `jubelioorders` (like Shopee omnichannel) — read-only `GET` order detail behind a separate flag, default off.
- Optional cron poll as backup (TikTok docs: do not rely on webhooks alone).
- Post path / stock push — separate features, not from this webhook handler.