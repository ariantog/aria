# Omnichannel dashboard (phase 1)

**Route:** `/omnichannel` (sidebar **Omnichannel**)

**Read-only guarantee (phase 1):** Shopee push may queue `RecordShopeePushShadowJob`, which only **GET**s Shopee order detail and **upserts** `marketplace_order_shadows`. It does **not** create `transactions`, change `warehouse_item`, insert/update `jubelioorders`, or call `ProcessJubelioOrder`. Jubelio webhooks, cron, and manual Process are unchanged.

Disable shadow entirely: `SHOPEE_PUSH_SHADOW_ENABLED=false` (push callback still returns 200).

Jubelio remains the posting path. Shopee **Order Status push (3)** triggers a queued shadow job that:

1. Calls Shopee `get_order_detail`
2. Upserts `marketplace_order_shadows`
3. Compares to `jubelioorders` by invoice `SP-{ordersn}`

## Requirements

- `SHOPEE_PUSH_*` + verified callback
- `SHOPEE_PUSH_SHADOW_ENABLED=true` (default)
- Shopee stock OAuth (`shopee_stock` settings) for API detail fetch
- Queue worker (`php artisan queue:listen` or cron queue) processing `RecordShopeePushShadowJob`

## Migrations

```bash
php artisan migrate --path=database/migrations/2026_11_19_150000_create_marketplace_order_shadows_table.php
```

## Permissions

`omnichannel-view` (optional; generate via PermissionGenerator). Until assigned, users with `jubelio-view` or `shopee-stock-view` can open the page.

## Line compare (phase 1b)

Shadow rows store **Shopee `item_list`** summary (`line_summary`: SKU, qty, amount) and, when a matching `jubelioorders` SELL exists, a read-only Jubelio API payload line summary (`jubelio_line_summary`). Line qty is compared via Aria `items.jubelio_item_id` (Jubelio) and `items.code` / `legacy_code` (Shopee SKU). **No** `ProcessJubelioOrder`, stock, or transaction writes.

- **Index:** `/omnichannel` — filters for overall compare and line compare; badges per row.
- **Detail:** `/omnichannel/shopee/{ordersn}` — Shopee JSON snippet, line tables, link to Jubelio order show (when permitted).

Migration (additive):

```bash
php artisan migrate --path=database/migrations/2026_11_20_100000_add_line_fields_to_marketplace_order_shadows.php
```

## Later phases

- TikTok / Tokopedia channels on same table
- Manual refresh from detail page
- Channel-specific post flag (cutover from Jubelio)
- Stock push status per channel on the dashboard