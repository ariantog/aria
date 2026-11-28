# Omnichannel marketplace returns — verify, post, listing sync

Native returns live in **`marketplace_channel_orders`** and the **Omnichannel → Returns** queue (`/omnichannel/returns`). Physical stock still changes only when a RETURN transaction is posted (`OmnichannelReturnOrderPoster`).

## Workflow (ops)

| Step | UI / trigger | Stock |
|------|----------------|-------|
| Return signal | Webhook updates order status (`TO_RETURN`, cancel family, TikTok cancel) | No `warehouse_item` change |
| Physical verify | **Verify** on Returns queue | No stock change |
| Post RETURN | Cron `app:omnichannel-post-orders --returns` or **Post** button (or auto on verify when enabled) | `warehouse_item.quantity` increases via `TransactionService` |
| Listing sync (pilot) | See below | Listing APIs only — not physical |

## Listing sync loop (pilot)

Maintainer flow when Shopee bumps listing sellable on buyer return:

1. Shopee return webhook → Aria (`marketplace_channel_orders` / returns queue) — existing.
2. **Shopee listing correct** (optional): Aria pushes Shopee listing qty down to Aria-computed sellable — **no** `warehouse_item` change on webhook.
3. Ops **Verify** physical receipt.
4. **Verify listing push** (optional): auto-post RETURN (when eligible), then push sellable to **mapped** Shopee + TikTok/Tokopedia warehouses for allowlisted SKUs.

Sellable formula (pilot): `max(0, physical − on_order − promo_lock − safety_buffer)` with safety buffer from **Omnichannel → Settings → Low stock (Shopee)** (`omnichannel_settings.shopee_low_stock_threshold`). Promo lock = `0` until Grok locks ship.

Audit rows: `marketplace_listing_stock_pushes` (idempotent per trigger × SKU × location × qty).

TikTok/Tokopedia writes are **stubbed** (log + skip) until Partner inventory update is enabled on aria3; architecture routes through `MarketplaceListingStockPusher` / `TikTokListingStockWriter`.

## Maintainer flags (`.env`)

| Variable | Default | Purpose |
|----------|---------|---------|
| `OMNICHANNEL_POST_RETURNS` | `false` | Master gate for native RETURN posting |
| `OMNICHANNEL_POST_RETURN_REQUIRE_PHYSICAL_VERIFY` | `true` | Cron/post button require verify |
| `OMNICHANNEL_RETURN_SHOPEE_LISTING_CORRECT` | `false` | Step 2: Shopee webhook → listing qty correct |
| `OMNICHANNEL_RETURN_VERIFY_LISTING_PUSH` | `false` | Step 4: after **Verify**, post (optional) + multi-channel listing push |
| `OMNICHANNEL_RETURN_VERIFY_AUTO_POST` | `true` | When verify push on, chain RETURN post before listing push |
| `MARKETPLACE_STOCK_PUSH_ITEM_IDS` | *(empty)* | Comma-separated Aria `items.id` pilot allowlist — others refused |
| `MARKETPLACE_LISTING_PUSH_IDEMPOTENCY_TTL_MINUTES` | `120` | Skip duplicate successful pushes with same idempotency key |

**Verify behaviour**

- Flags off: **Verify** only sets `return_verified_*`; user posts RETURN separately.
- `OMNICHANNEL_RETURN_VERIFY_LISTING_PUSH=true`: **Verify** runs `OmnichannelReturnVerifyListingPush::afterVerify` — optional auto-post, then listing push for line SKUs on `aria_warehouse_id` mapped shops.

## Behaviour table

| Signal | Shopee listing correct | Verify + listing push |
|--------|------------------------|------------------------|
| Shopee `TO_RETURN` / cancel webhook | Yes, if `OMNICHANNEL_RETURN_SHOPEE_LISTING_CORRECT` + SKU allowlist | — |
| Returns **Verify** | — | Yes, if `OMNICHANNEL_RETURN_VERIFY_LISTING_PUSH` + allowlist; auto-post if `OMNICHANNEL_RETURN_VERIFY_AUTO_POST` + `OMNICHANNEL_POST_RETURNS` |
| Physical stock | Never on webhook/listing push | Only when RETURN transaction posts |

## Code map

- `OmnichannelReturnShopeeListingCorrector` — hook from `PersistShopeePushInboxJob` (push code 3).
- `OmnichannelReturnVerifyListingPush` — chained from `OmnichannelReturnsController@verify`.
- `MarketplaceListingStockPusher`, `ShopeeListingStockWriter`, `TikTokListingStockWriter`.
- `doc/omnichannel-native-sell-post.md` — native SELL/RETURN posting flags (complementary).

See also: `/cursor/stores/bc-b6977350-8be3-4a36-a966-943b4d89cf77/docs/marketplace-listing-sync-pilot.md` (phase 0 pilot plan).