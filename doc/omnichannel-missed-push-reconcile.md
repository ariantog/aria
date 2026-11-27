# Omnichannel missed-push reconcile (MVP)

## Goal

Close gaps where the marketplace platform has an order but Aria never received a push/webhook row in `marketplace_channel_orders`. **Does not** replay every status webhook for orders already in Aria (no broad status-update replay).

## Command

```bash
php artisan marketplace:reconcile-missed-orders
  [--channel=shopee] [--shop-id=] [--days=] [--detect-only]
```

- **Feature flag:** `MARKETPLACE_MISSED_PUSH_RECONCILE_ENABLED` (`config/marketplace.php` → `missed_push_reconcile.enabled`).
- **Window:** `MARKETPLACE_MISSED_PUSH_RECONCILE_DAYS` (default 14), capped by `MARKETPLACE_MISSED_PUSH_RECONCILE_MAX_DAYS` (default 30).
- **Shopee shops:** mapped rows on `shopee_syncs` (`shop_id` > 0, `warehouse_id` > 0) with Admin OAuth.
- **Ingest path:** `ShopeeOrderPollService::pullAndIngestOrder()` → `ShopeeOrderStatusIngest` → `MarketplaceWebhookRecorder::recordShopee()` (same as poll / manual pull).
- **Cron:** scheduled task `marketplace:reconcile-missed-orders` (inactive by default).

## Status filters (Shopee)

Configurable via `MARKETPLACE_MISSED_PUSH_RECONCILE_SHOPEE_STATUSES` (comma-separated). Default **ingest when listing**:

| Status | Action |
|--------|--------|
| `UNPAID` | List + ingest if missing from Aria |
| `READY_TO_SHIP` | List + ingest if missing |
| `PROCESSED` | List + ingest if missing |
| `SHIPPED` | List + ingest if missing |

**Ignored on list poll** (not in default filter — avoids noise / invalid API filters):

| Status | Reason |
|--------|--------|
| `COMPLETED` | Late churn; order should already exist if push worked |
| `CANCELLED` / `IN_CANCEL` | Terminal / ops bucket elsewhere |
| `RETRY_SHIP` | Valid on push, rejected by `get_order_list` `order_status` filter |
| Minor / logistics-only churn | Not in scope for gap ingest |

## Gap queue + UI

- Table: `marketplace_missed_push_gaps` (open rows: `ingested_at` null).
- Omnichannel → Orders → view **Missed push (reconcile)** — open gaps, manual **Ingest**, last reconcile poll per shop (`shopee_syncs.last_order_poll_at`).

## TikTok

`TikTokMissedPushReconcileStub` implements the channel interface; reconcile is **deferred** (PR2). Use `--channel=tiktok` only after a live reconciler ships.

## Related

- FLEXI direct poll: `shopee:poll-orders` / `ShopeeOrderPollService` (still for `direct_order_ingest` flexi shops; may ingest known orders).
- Jubelio catch-up pattern: `JubelioGetOrdersService::pollRecentDays()` (different domain — Jubelio queue, not marketplace unified storage).
- CDN relay: `doc/marketplace-webhook-cdn-relay.md`.