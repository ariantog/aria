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