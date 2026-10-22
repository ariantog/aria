<?php

return [

    'enabled' => (bool) env('LARK_ENABLED', false),

    'dry_run' => (bool) env('LARK_DRY_RUN', true),

    'timezone' => env('LARK_TIMEZONE', 'Asia/Jakarta'),

    'bot_webhook_url' => env('LARK_BOT_WEBHOOK_URL'),

    'bot_secret' => env('LARK_BOT_SECRET'),

    'ops_chat_id' => env('LARK_OPS_CHAT_ID'),

    'verification_token' => env('LARK_VERIFICATION_TOKEN'),

    'encrypt_key' => env('LARK_ENCRYPT_KEY'),

    /** Fallback user when Lark submitter cannot be mapped (restock apply audit). */
    'system_user_id' => (int) env('LARK_SYSTEM_USER_ID', 1),

    /**
     * Optional deep link into Jubelio’s web UI. Placeholders: {salesorder_id}, {invoice}
     * Example: https://app.jubelio.com/your-tenant/sales/order/{salesorder_id}
     */
    'jubelio_order_url_template' => env('LARK_JUBELIO_ORDER_URL_TEMPLATE'),

    'low_cover' => [
        'default_cover_days' => (int) env('LARK_LOW_COVER_DAYS', 14),
        'best_seller_cover_days' => (int) env('LARK_BEST_SELLER_COVER_DAYS', 30),
        'rearm_buffer_days' => (float) env('LARK_LOW_COVER_REARM_BUFFER', 4),
        'mismatch_qty_delta' => (int) env('LARK_STOCK_MISMATCH_QTY', 3),
        /** ItemType ints eligible for stok rendah Lark cards (default: asset lancar only). Overridable in Lark Ops settings. */
        'alert_item_types' => array_map('intval', array_filter(explode(',', env('LARK_LOW_COVER_ITEM_TYPES', '2')))),
    ],

    'jubelio' => [
        'error_backlog_threshold' => (int) env('LARK_JUBELIO_ERROR_BACKLOG', 25),
    ],

    /**
     * event_key => mention mode: none | roster, and staff role slugs for @ roster (Package 2).
     */
    'events' => [
        'jubelio.oversell.webhook' => ['mention' => 'none', 'roles' => []],
        'stock.low_cover' => ['mention' => 'none', 'roles' => []],
        'stock.low_cover.both' => ['mention' => 'roster', 'roles' => ['produk', 'gudang', 'penjualan_online']],
        'stock.mismatch' => ['mention' => 'roster', 'roles' => ['penjualan_online', 'gudang']],
        'jubelio.oversell.blocked' => ['mention' => 'roster', 'roles' => ['penjualan_online', 'gudang']],
        'jubelio.error.backlog' => ['mention' => 'roster', 'roles' => ['penjualan_online']],
        'stock.restock.pending' => ['mention' => 'none', 'roles' => []],
    ],

];
