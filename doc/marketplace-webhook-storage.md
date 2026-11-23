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
| `MARKETPLACE_WEBHOOK_EVENTS_RETENTION_DAYS` | `90` | Default retention for prune command |

Legacy inbox flags are unchanged (`SHOPEE_PUSH_STORE_INBOX`, `TIKTOK_SHOP_WEBHOOK_STORE_INBOX`).

## Prune

Deletes `marketplace_webhook_events` where `received_at` is older than the retention window, in batches of 5000.

```bash
php artisan marketplace:prune-webhook-events
php artisan marketplace:prune-webhook-events --days=60
php artisan marketplace:prune-webhook-events --dry-run
```

Not registered in Cron Manager until the maintainer enables it.

## Idempotency keys

| Channel | Key |
|---------|-----|
| TikTok | `tiktok:{tts_notification_id}` |
| Shopee | `shopee:{shop_id}:{push_code}:{ordersn}:{timestamp or hash prefix}` |

Code: `App\Services\Marketplace\MarketplaceWebhookRecorder`.

## PR2 — Unified inbox UI

**Route:** `GET /marketplace/webhooks` redirects to `GET /omnichannel/orders` (query preserved; `view` stripped). Legacy `GET /tiktok-shop/webhooks` redirects to the same Orders list.

**Permission:** same as Omnichannel Orders — `omnichannel-orders`, `omnichannel-view`, or `tiktok-shop-view` (superadmin as usual). Legacy `marketplace-webhook-view` was removed; use omnichannel permissions only.

**Data:** Primary list reads `marketplace_webhook_events`; optional **Current orders** tab reads `marketplace_channel_orders`. Enable dual-write (`MARKETPLACE_WEBHOOK_UNIFIED_STORAGE=true`) or backfill from legacy inbox tables.

### Filters (query params preserved in pagination)

| Param | Notes |
|-------|--------|
| `channel` | `all` (default), `shopee`, `tiktok` |
| `date_from`, `date_to` | On `received_at` (events) or `last_event_at` (orders); default last 7 days |
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