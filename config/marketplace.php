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