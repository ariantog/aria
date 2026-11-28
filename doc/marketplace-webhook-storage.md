# Marketplace webhook unified storage (PR1)

Shopee and TikTok inbound webhooks can **dual-write** into two shared tables on production MySQL while legacy `shopee_push_inbox` and `tiktok_shop_webhook_inbox` remain the source for existing UIs.

## Tables

| Table | Role |
|-------|------|
| `marketplace_webhook_events` | Append-only history; **unique** `idempotency_key` |
| `marketplace_channel_orders` | One row per `(channel, order_ref)` — latest order state |

Channel values: `shopee`, `tiktok`.

Design detail: see the Project doc *marketplace-webhook-inbox-design* (rollout PR2 = backfill + unified inbox UI).

## Migrations

```bash
php artisan migrate --path=database/migrations/2026_11_21_150000_create_marketplace_webhook_events_table.php
php artisan migrate --path=database/migrations/2026_11_21_151000_create_marketplace_channel_orders_table.php
```

## Environment

| Variable | Default | Purpose |
|----------|---------|---------|
| `MARKETPLACE_WEBHOOK_UNIFIED_STORAGE` | `false` | When `true`, dual-write on Shopee push (inbox path) and `PersistTikTokShopWebhookInboxJob` |
| `MARKETPLACE_WEBHOOK_EVENTS_RETENTION_DAYS` | `90` | Retention for prune command (unified events + legacy inbox tables) |

Legacy inbox flags are unchanged (`SHOPEE_PUSH_STORE_INBOX`, `TIKTOK_SHOP_WEBHOOK_STORE_INBOX`).

## Prune

Deletes rows older than the retention window (default `MARKETPLACE_WEBHOOK_EVENTS_RETENTION_DAYS`, 90), in batches of 5000:

| Table | Timestamp column |
|-------|------------------|
| `marketplace_webhook_events` | `received_at` |
| `shopee_push_inbox` | `created_at` |
| `tiktok_shop_webhook_inbox` | `created_at` |

Does **not** trim `marketplace_channel_orders` (latest order summary).

```bash
php artisan marketplace:prune-webhook-events
php artisan marketplace:prune-webhook-events --days=60
php artisan marketplace:prune-webhook-events --dry-run
```

Schedule quarterly or monthly in Cron Manager when inbox/unified volume warrants it (not enabled by default).

## Idempotency keys

| Channel | Key |
|---------|-----|
| TikTok | `tiktok:{tts_notification_id}` |
| Shopee | `shopee:{shop_id}:{push_code}:{ordersn}:{timestamp}:{ingest status or payload hash}` |

Code: `App\Services\Marketplace\MarketplaceWebhookRecorder`.

## PR2 — Unified inbox UI

**Route:** `GET /marketplace/webhooks` redirects to `GET /omnichannel/orders` (query preserved; `view` stripped). Legacy `GET /tiktok-shop/webhooks` redirects to the same Orders list.

**Permission:** same as Omnichannel Orders — `omnichannel-orders`, `omnichannel-view`, or `tiktok-shop-view` (superadmin as usual). Legacy `marketplace-webhook-view` was removed; use omnichannel permissions only.

**Data:** Primary list reads `marketplace_webhook_events`; optional **Current orders** tab reads `marketplace_channel_orders`. Enable dual-write (`MARKETPLACE_WEBHOOK_UNIFIED_STORAGE=true`) or backfill from legacy inbox tables.

### Filters (query params preserved in pagination)

| Param | Notes |
|-------|--------|
| `channel` | `all` (default), `shopee`, `tiktok` |
| `date_from`, `date_to` | On `received_at` (events) or `marketplace_ordered_at` (orders — marketplace order date, not webhook receipt); default last 7 days |
| `order_status` | Partial `LIKE` match; datalist from distinct values in current scope |
| `shop_ref` | Exact match on stored shop id |
| `warehouse_id` | Aria gudang — resolves `shop_ref` via **`shopee_syncs.shop_id`** and **`tiktok_shop_syncs.shop_ref`** |
| `view` | `events` (default) or `orders` |

**Warehouse filter SQL scope:** when `warehouse_id` > 0, load shop refs from `shopee_syncs` + `tiktok_shop_syncs` for that warehouse, then `WHERE shop_ref IN (…)`; if the map is empty, the query returns no rows.

### Backfill

Idempotent import from `shopee_push_inbox` and `tiktok_shop_webhook_inbox` using the same idempotency keys as live ingest:

```bash
php artisan marketplace:backfill-webhook-events
php artisan marketplace:backfill-webhook-events --dry-run
php artisan marketplace:backfill-webhook-events --limit=1000
```

Upserts `marketplace_channel_orders` when `order_ref` is present (same rules as live recorder).

**Key files:** `MarketplaceWebhookInboxController`, `MarketplaceWebhookInboxQuery`, `MarketplaceWebhookShopWarehouseResolver`, `resources/views/marketplace/webhooks.blade.php`, `BackfillMarketplaceWebhookEventsCommand`.

## Maintainer — pull order by ref (API)

When a webhook was missed or history must be seeded for one order:

| Channel | API | Shop auth |
|---------|-----|-----------|
| Shopee | `ShopeeAdminOpenApiService::getOrderDetail` | `shop_id` from `shopee_syncs` + Shopee Admin OAuth |
| TikTok Shop / Tokopedia | `TikTokShopOpenApiService::getOrderDetail` | `shop_ref` + `shop_cipher` from `tiktok_shop_syncs` |

**UI:** `GET/POST /omnichannel/fetch-order` (sidebar **Fetch order**). Permission: `omnichannel-orders` (same as Omnichannel orders).

**CLI:** `php artisan marketplace:pull-order ORDER_REF --channel=shopee --shop-ref=501` (Shopee `--shop-id` alias). Tokopedia: `--channel=tokopedia` (uses TikTok API).

Ingest path:

- Shopee → `ShopeeOrderPollService::pullAndIngestOrder` → `ShopeeOrderStatusIngest` → `MarketplaceWebhookRecorder::recordShopee()`
- TikTok → `TikTokOrderPullService::pullAndIngestOrder` → `MarketplaceWebhookRecorder::recordTikTok()` (+ optional legacy `tiktok_shop_webhook_inbox`, commerce_platform enrich)

Requires `MARKETPLACE_WEBHOOK_UNIFIED_STORAGE=true` for rows on **Current orders** / **Webhooks**. Return-related statuses follow the same gates as live webhooks (#1191 verify before post).

**Key files:** `OmnichannelFetchOrderController`, `MarketplaceOrderRefPullService`, `TikTokOrderPullService`, `PullMarketplaceOrderCommand`, `resources/views/omnichannel/fetch-order.blade.php`.