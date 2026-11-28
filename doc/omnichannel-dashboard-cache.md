# Omnichannel dashboard stat cache

Performance model follows **Jubelio index** (`JubelioController@index`): one bounded SQL aggregate for tab cards + paginated list — no per-card COUNT loop and no full-table KPI scan on page load.

## Current orders (`/omnichannel/orders?view=orders`)

| Piece | Behavior |
|-------|----------|
| **Ops tab cards** | `MarketplaceChannelOrderOpsBucket::aggregateBucketCounts()` — single `SELECT` with `COUNT(CASE WHEN …)` per bucket on `ordersQueryForStats()` (date/channel/warehouse filters). Tab links only change list `ops_bucket`. |
| **Native posted** | Same aggregate adds `COUNT(CASE WHEN posted_transaction_id > 0 …)` for a lightweight “posted in window” hint — not eligibility/errors. |
| **Status filter hints** | Static list from ops-bucket status maps (`knownOrderStatusHints`) — no `DISTINCT order_status` on each request. |
| **Shop / warehouse dropdowns** | Short TTL cache on `MarketplaceWebhookShopWarehouseResolver` (default **120s**). |

## Native post-sell KPIs (eligible / errors / gate)

| Piece | Behavior |
|-------|----------|
| **Storage** | Row `omnichannel_post_sell_stats` (id `1`), incremental via `MarketplaceChannelOrderObserver`. |
| **Web read** | `OmnichannelPostSellStatsStore::countsForDashboard()` returns snapshot only; **never** `rebuildFull()` / `computeCountsFromDatabase()` on HTTP. Missing or stale → last snapshot or zeros + `stats_stale=true`; optional `RefreshOmnichannelPostSellStatsJob` (debounced). |
| **Reconcile** | `php artisan omnichannel:refresh-post-sell-stats` — full cursor recount. Run once after deploy/migration before relying on snapshot; then hourly scheduled task (if enabled). |

Env: `OMNICHANNEL_DASHBOARD_POST_SELL_FRESH_SECONDS`, `OMNICHANNEL_DASHBOARD_OPS_BUCKET_TTL_SECONDS` (legacy cache class unused on orders index), `OMNICHANNEL_DASHBOARD_COMPARE_TTL_SECONDS`, `OMNICHANNEL_DASHBOARD_SHOP_WAREHOUSE_TTL_SECONDS`.

## Shadow compare index (`/omnichannel`)

| Piece | Behavior |
|-------|----------|
| **Compare counts** | One `selectRaw` CASE aggregate on `marketplace_order_shadows` (+ small Jubelio pending count), cached **120s** (`OmnichannelCompareDashboardStats`). |
| **Invalidation** | `OmnichannelDashboardCacheBuster::bustCompareStats()` after shadow upsert. |

## Related

- Eligibility and gates: `doc/omnichannel-native-sell-post.md`
- Ops bucket rules: `doc/omnichannel.md`