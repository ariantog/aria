<?php

return [

    'webhook' => [
        /**
         * Dual-write Shopee/TikTok deliveries to marketplace_webhook_events / marketplace_channel_orders
         * while legacy inbox tables remain authoritative for existing UIs (PR1).
         */
        'unified_storage' => filter_var(
            env('MARKETPLACE_WEBHOOK_UNIFIED_STORAGE', false),
            FILTER_VALIDATE_BOOLEAN
        ),

        /** Default retention for marketplace:prune-webhook-events (days). */
        'events_retention_days' => max(1, (int) env('MARKETPLACE_WEBHOOK_EVENTS_RETENTION_DAYS', 90)),
    ],

];