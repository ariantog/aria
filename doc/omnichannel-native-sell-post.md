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

## Mutual exclusion

Duplicate guard uses transaction invoice + `posted_transaction_id` on `marketplace_channel_orders`. Do not run Jubelio and native post for the same channel without maintainer exclusion rules.