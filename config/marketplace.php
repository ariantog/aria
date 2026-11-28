<?php

return [

    /** Increment warehouse_item.on_order on TikTok awaiting-shipment webhooks (no transactions). */
    'tiktok_on_order' => [
        'enabled' => filter_var(
            env('TIKTOK_SHOP_ON_ORDER_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],

    /**
     * Increment warehouse_item.on_order on Shopee push code 3 (awaiting shipment) without jubelioorders placeholder.
     * Uses item_list + ShopeeSyncWarehouseResolver; idempotent via marketplace_on_order_reservations.
     */
    'shopee_on_order' => [
        'enabled' => filter_var(
            env('SHOPEE_PUSH_ON_ORDER_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],

    /**
     * Detect platform orders missing from marketplace_channel_orders (missed push / relay gap).
     * Does not replay status churn for orders already in Aria.
     */
    'missed_push_reconcile' => [
        'enabled' => filter_var(
            env('MARKETPLACE_MISSED_PUSH_RECONCILE_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'days' => max(1, (int) env('MARKETPLACE_MISSED_PUSH_RECONCILE_DAYS', 14)),
        'max_days' => max(1, (int) env('MARKETPLACE_MISSED_PUSH_RECONCILE_MAX_DAYS', 30)),
        'shopee' => [
            // UNPAID..SHIPPED only — no COMPLETED/CANCEL churn; RETRY_SHIP invalid for get_order_list filter.
            'order_statuses' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env(
                    'MARKETPLACE_MISSED_PUSH_RECONCILE_SHOPEE_STATUSES',
                    'UNPAID,READY_TO_SHIP,PROCESSED,SHIPPED'
                )),
            ))),
        ],
    ],

    'webhook' => [
        /**
         * Dual-write Shopee/TikTok deliveries to marketplace_webhook_events / marketplace_channel_orders
         * while legacy inbox tables remain authoritative for existing UIs (PR1).
         */
        'unified_storage' => filter_var(
            env('MARKETPLACE_WEBHOOK_UNIFIED_STORAGE', false),
            FILTER_VALIDATE_BOOLEAN
        ),

        /**
         * Default retention for marketplace:prune-webhook-events (days).
         * Also applies to legacy shopee_push_inbox and tiktok_shop_webhook_inbox (created_at).
         */
        'events_retention_days' => max(1, (int) env('MARKETPLACE_WEBHOOK_EVENTS_RETENTION_DAYS', 90)),
    ],

    /**
     * Map platform-native order_status strings to coarse ops buckets (UI chips).
     * Tokopedia rows use TikTok vocabulary via commerce_platform=TOKOPEDIA.
     */
    /**
     * Native marketplace SELL posting (replaces jubelio:order-jubelio-to-aria for enabled channels).
     *
     * Gate shipped: Shopee SHIPPED, TO_CONFIRM_RECEIVE, COMPLETED; TikTok/Tokopedia IN_TRANSIT family + COMPLETED.
     * Gate awb_printed: awb_printed_at set, not cancelled (Shopee-led; TikTok may stay on shipped gate).
     */
    'post_sell' => [
        'enabled' => filter_var(
            env('OMNICHANNEL_POST_SELLS', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'dry_run' => filter_var(
            env('OMNICHANNEL_POST_SELL_DRY_RUN', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'gate' => in_array(
            strtolower((string) env('OMNICHANNEL_POST_SELL_GATE', 'shipped')),
            ['shipped', 'awb_printed'],
            true
        ) ? strtolower((string) env('OMNICHANNEL_POST_SELL_GATE', 'shipped')) : 'shipped',
        'max_age_days' => max(1, (int) env('OMNICHANNEL_POST_SELL_MAX_AGE_DAYS', 30)),
        'batch_size' => max(1, (int) env('OMNICHANNEL_POST_SELL_BATCH_SIZE', 25)),
        'channels' => array_values(array_filter(array_map(
            static fn (string $c) => strtolower(trim($c)),
            explode(',', (string) env('OMNICHANNEL_POST_SELL_CHANNELS', 'shopee'))
        ))),
    ],

    /**
     * Native marketplace RETURN posting (cancelled / buyer-return statuses; requires linked SELL).
     */
    'post_return' => [
        'enabled' => filter_var(
            env('OMNICHANNEL_POST_RETURNS', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'dry_run' => filter_var(
            env('OMNICHANNEL_POST_RETURN_DRY_RUN', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'max_age_days' => max(1, (int) env('OMNICHANNEL_POST_RETURN_MAX_AGE_DAYS', 30)),
        'batch_size' => max(1, (int) env('OMNICHANNEL_POST_RETURN_BATCH_SIZE', 25)),
        'channels' => array_values(array_filter(array_map(
            static fn (string $c) => strtolower(trim($c)),
            explode(',', (string) env('OMNICHANNEL_POST_RETURN_CHANNELS', 'shopee'))
        ))),
        'shopee_statuses' => ['TO_RETURN', 'IN_CANCEL', 'CANCELLED', 'CANCELED'],
        'tiktok_statuses' => ['CANCEL', 'CANCELLED', 'CANCELED'],
    ],

    /**
     * Omnichannel hub / orders dashboard stat cards (post-sell snapshot, ops buckets, shadow compare).
     */
    'dashboard_cache' => [
        'post_sell_fresh_seconds' => max(60, (int) env('OMNICHANNEL_DASHBOARD_POST_SELL_FRESH_SECONDS', 180)),
        'post_sell_inline_refresh_seconds' => max(3, (int) env('OMNICHANNEL_DASHBOARD_POST_SELL_REFRESH_SECONDS', 8)),
        'ops_bucket_ttl_seconds' => max(30, (int) env('OMNICHANNEL_DASHBOARD_OPS_BUCKET_TTL_SECONDS', 90)),
        'compare_stats_ttl_seconds' => max(30, (int) env('OMNICHANNEL_DASHBOARD_COMPARE_TTL_SECONDS', 120)),
    ],

    'webhook_status_buckets' => [
        'shopee' => [
            'UNPAID' => 'unpaid',
            'READY_TO_SHIP' => 'awaiting_shipment',
            'PROCESSED' => 'awaiting_shipment',
            'RETRY_SHIP' => 'awaiting_shipment',
            'SHIPPED' => 'in_transit',
            'TO_CONFIRM_RECEIVE' => 'in_transit',
            'IN_CANCEL' => 'cancelled',
            'CANCELLED' => 'cancelled',
            'CANCELED' => 'cancelled',
            'TO_RETURN' => 'other',
            'COMPLETED' => 'completed',
        ],
        'tiktok' => [
            'UNPAID' => 'unpaid',
            'ON_HOLD' => 'unpaid',
            'AWAITING_SHIPMENT' => 'awaiting_shipment',
            'AWAITING_COLLECTION' => 'awaiting_shipment',
            'PARTIALLY_SHIPPING' => 'in_transit',
            'IN_TRANSIT' => 'in_transit',
            'DELIVERED' => 'in_transit',
            'COMPLETED' => 'completed',
            'CANCEL' => 'cancelled',
            'CANCELLED' => 'cancelled',
            'CANCELED' => 'cancelled',
        ],
        'tokopedia' => [
            'UNPAID' => 'unpaid',
            'ON_HOLD' => 'unpaid',
            'AWAITING_SHIPMENT' => 'awaiting_shipment',
            'AWAITING_COLLECTION' => 'awaiting_shipment',
            'PARTIALLY_SHIPPING' => 'in_transit',
            'IN_TRANSIT' => 'in_transit',
            'DELIVERED' => 'in_transit',
            'COMPLETED' => 'completed',
            'CANCEL' => 'cancelled',
            'CANCELLED' => 'cancelled',
            'CANCELED' => 'cancelled',
        ],
    ],

];