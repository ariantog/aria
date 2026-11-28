# Omnichannel dashboard stat cache

Reduces per-request full-table scans on **Omnichannel index**, **Current orders**, and native post-sell KPI cards.

## Native post-sell KPIs (`posted` / `eligible_to_post` / `errors`)

| Layer | Behavior |
|-------|----------|
| **Storage** | Single row `omnichannel_post_sell_stats` (id `1`), keyed by `scope_key` (gate + max age + allowed channels). |
| **Incremental** | `MarketplaceChannelOrderObserver` on save/delete calls `OmnichannelPostSellStatsStore` using `OmnichannelPostSellEligibility::rejectReason()` (same rules as live posting). |
| **Read** | `OmnichannelPostSellDashboardStats::counts()` returns the snapshot. Missing row or `scope_key` mismatch → full rebuild. Older than **fresh TTL** (default **180s**) → bounded inline rebuild (default **8s** `max_execution_time`) else show snapshot marked **stale** in UI. |
| **Reconcile** | `php artisan omnichannel:refresh-post-sell-stats` — full cursor recount (maintainer / optional hourly **inactive** cron). Use after gate or channel config changes or if counters drift from rolling window without row updates. |

Env: `OMNICHANNEL_DASHBOARD_POST_SELL_FRESH_SECONDS`, `OMNICHANNEL_DASHBOARD_POST_SELL_REFRESH_SECONDS`.

## Ops bucket stat cards (`/omnichannel/orders?view=orders`)

| Layer | Behavior |
|-------|----------|
| **Storage** | Laravel cache entry per **filter fingerprint** (`channel`, `date_from`, `date_to`, `warehouse_id`, `shop_ref`, `order_ref`). |
| **TTL** | Default **90s** (`OMNICHANNEL_DASHBOARD_OPS_BUCKET_TTL_SECONDS`). |
| **Invalidation** | Global `omnichannel.dashboard.ops_bucket_version` incremented on every `marketplace_channel_orders` save/delete (matches any filter using the same base stats query). |

Still runs six `COUNT`s on a cache miss; hits avoid repeat work within TTL.

## Shadow compare index (`/omnichannel`)

| Layer | Behavior |
|-------|----------|
| **Storage** | One cache blob: five `marketplace_order_shadows` compare counts + Jubelio sell pending. |
| **TTL** | Default **120s** (`OMNICHANNEL_DASHBOARD_COMPARE_TTL_SECONDS`). |
| **Invalidation** | `OmnichannelDashboardCacheBuster::bustCompareStats()` after Shopee shadow upsert / compare refresh. |

## Related

- Eligibility and gates: `doc/omnichannel-native-sell-post.md`
- Ops bucket rules: `doc/omnichannel.md` (Current orders ops buckets)