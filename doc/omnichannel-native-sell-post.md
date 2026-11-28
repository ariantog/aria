# Native marketplace SELL posting

Phase 1 fulfillment cutover: post SELL transactions from `marketplace_channel_orders` instead of `jubelio:order-jubelio-to-aria`.

## Command

`php artisan omnichannel:post-shipped-orders`

Options: `--channel=shopee`, `--dry-run`, `--limit=N`. `--returns` is reserved (not implemented).

Registered in Cron Manager as **Omnichannel post shipped marketplace sells** (`everyFiveMinutes`, **inactive** by default).

## Environment

| Variable | Default | Meaning |
|----------|---------|---------|
| `OMNICHANNEL_POST_SELLS` | `false` | Master enable for real posts |
| `OMNICHANNEL_POST_SELL_DRY_RUN` | `false` | Log would-post without transactions |
| `OMNICHANNEL_POST_SELL_GATE` | `shipped` | `shipped` or `awb_printed` (config default) |

### Gate precedence (env vs UI)

1. **Effective gate** at runtime: `OmnichannelSettingStore::resolvePostSellGate()` → value in `omnichannel_settings` key `post_sell_gate` when set to `shipped` or `awb_printed`.
2. **Otherwise** fall back to `config('marketplace.post_sell.gate')` from `OMNICHANNEL_POST_SELL_GATE`.
3. **Omnichannel → Settings → Fulfillment** saves the DB override (permission `omnichannel-settings-edit`). Env remains the bootstrap default until the maintainer saves from UI.
| `OMNICHANNEL_POST_SELL_MAX_AGE_DAYS` | `30` | Rolling window |
| `OMNICHANNEL_POST_SELL_BATCH_SIZE` | `25` | Per run |
| `OMNICHANNEL_POST_SELL_CHANNELS` | `shopee` | Comma list (`shopee`, `tiktok`) |

## Gate: shipped (default)

Uses ops buckets from `MarketplaceChannelOrderOpsBucket` / webhook status map:

- **Shopee:** `SHIPPED`, `TO_CONFIRM_RECEIVE`, `COMPLETED` (in transit + completed).
- **TikTok / Tokopedia:** `PARTIALLY_SHIPPING`, `IN_TRANSIT`, `DELIVERED`, `COMPLETED`.

## Gate: awb_printed

Requires `marketplace_channel_orders.awb_printed_at` and excludes cancelled / unprocessed buckets (Shopee-led AWB workflow).

## Lines & mapping

- Lines from `marketplace_order_shadows` (`line_summary` or stored detail) or `marketplace_on_order_reservations`.
- Warehouse: `aria_warehouse_id` on the channel order row.
- Customer: `jubeliosyncs.customer_id` for that warehouse (same pattern as FLEXI prefill).
- Invoice: `ShopeeSyncInvoicePrefix` (`SP-{ordersn}`, flexi prefixes); TikTok `TT-{order_ref}`.
- `submit_type` = `Transaction::SUBMIT_TYPE_MARKETPLACE` (3).

## Cutover runbook

1. Enable shadow: `OMNICHANNEL_POST_SELL_DRY_RUN=true`, run cron or manual command; compare logs to Jubelio cron output.
2. Flip `OMNICHANNEL_POST_SELLS=true`, activate scheduled task in Cron Manager.
3. Disable Jubelio order posting for that channel (maintainer: pause `jubelio:order-jubelio-to-aria` or channel-specific flag).
4. Do not remove Jubelio stock/listing sync in this phase.

## Dashboard (ops)

**Omnichannel → Current orders** and **Omnichannel index** (when the user has `omnichannel-orders`) show native fulfillment KPIs from `marketplace_channel_orders` + `config('marketplace.post_sell')` / Settings **Fulfillment** gate:

| Card | Meaning |
|------|---------|
| Native SELL posted | Rows with `posted_transaction_id > 0` in the rolling window and post-sell gate |
| Eligible to post | In-window, gate bucket, not posted — `OmnichannelPostSellEligibility::rejectReason()` is null |
| Post errors | Same scope, not posted, reject reason other than “Transaction sudah ada.” (e.g. unmapped warehouse) |
| Jubelio sell pending | Secondary when `JUBELIO_ACTIVE` — legacy queue `status = 0` |

List/detail show **submit source** via `Transaction::submitTypeLabel()` on the linked SELL (`SUBMIT_TYPE_JUBELIO` = Jubelio cron, `SUBMIT_TYPE_MARKETPLACE` = omnichannel cron).

## Duplicate invoice → finished (not ERROR_DUPLICATE)

When either cron would create a duplicate SELL for the same marketplace invoice (`SP-…` / `TT-…`):

| Path | Behaviour |
|------|-----------|
| `ProcessJubelioOrder` (SELL) | `MarketplaceSellPostGuard::finishJubelioSellOnExistingInvoice()` — `jubelioorders.status = 2`, `error_type = SUCCESS`, message links existing txn id; backfill `posted_transaction_id` on matching `marketplace_channel_orders` when found |
| `OmnichannelShippedOrderPoster` skip | `reconcileNativeSkipBecauseInvoiceExists()` — sets `posted_transaction_id` from existing txn; marks pending/error-duplicate Jubelio SELL rows SUCCESS for same invoice |

Operators should still pause Jubelio order posting per channel once native post is trusted; reconciliation is a cutover safety net.

## Mutual exclusion

During Jubelio → native cutover, **both** `jubelio:order-jubelio-to-aria` and `omnichannel:post-shipped-orders` may run temporarily. `MarketplaceSellPostGuard` keeps a single SELL per marketplace invoice:

| Check | Native cron skips when… | Jubelio cron skips when… |
|-------|-------------------------|---------------------------|
| Invoice | SELL already in `transactions` | Same |
| Channel order | `posted_transaction_id` on `marketplace_channel_orders` | Matching row has `posted_transaction_id > 0` |
| Jubelio queue | `jubelioorders` SELL **pending** (`status=0`) or **success** for same `invoice` | Another successful queue row (via `invoiceTaken`) |
| Race | `lockForUpdate` on channel order row + re-check inside DB transaction before insert | Re-check in `ProcessJubelioOrder::createTransaction` before insert |

**Maintainer:** still disable Jubelio order posting per channel once native post is trusted (`OMNICHANNEL_POST_SELLS=true`). Mutual exclusion is a safety net, not a substitute for turning off duplicate pipelines.