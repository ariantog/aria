# Omnichannel dashboard (phase 1)

**Route:** `/omnichannel` (sidebar **Omnichannel**)

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

## Later phases

- TikTok / Tokopedia channels on same table
- Detail drawer + manual refresh
- Channel-specific post flag (cutover from Jubelio)
- Stock push status per channel on the dashboard