<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        // sendMessage chat_id. Private 1:1 chat: same numeric id as ALLOWED_TELEGRAM_USER_ID.
        'chat_ids' => env('TELEGRAM_CHAT_IDS'),
        // Python bots/bot.py compat — merged with chat_ids (deduped).
        'allowed_user_ids' => env('ALLOWED_TELEGRAM_USER_ID'),
    ],

    'shopee_ads' => [
        // All schedule / daily-reset / replenish times are WIB (GMT+7, same as Asia/Jakarta).
        'timezone' => env('SHOPEE_ADS_TIMEZONE', env('APP_TIMEZONE', 'Asia/Jakarta')),
        // Deprecated on aria3 — ads API OAuth/signing uses shopee_admin. Kept for legacy hosts only.
        'partner_id' => env('SHOPEE_PARTNER_ID'),
        'partner_key' => env('SHOPEE_PARTNER_KEY'),
        'base_url' => env('SHOPEE_BASE_URL', 'https://partner.shopeemobile.com'),
        'redirect_url' => env('SHOPEE_REDIRECT_URL', 'https://aria3.corenationactive.com/shopee-ads/oauth/callback'),
    ],

    'shopee_stock' => [
        // Deprecated on aria3 — stock API OAuth/signing uses shopee_admin. Kept for legacy hosts only.
        'partner_id' => env('SHOPEE_STOCK_PARTNER_ID'),
        'partner_key' => env('SHOPEE_STOCK_PARTNER_KEY'),
        'base_url' => env('SHOPEE_STOCK_BASE_URL', env('SHOPEE_BASE_URL', 'https://partner.shopeemobile.com')),
        'redirect_url' => env('SHOPEE_STOCK_REDIRECT_URL', 'https://aria3.corenationactive.com/shopee-stock/oauth/callback'),
    ],

    'shopee_admin' => [
        // CORENATION ADMIN app (aria3 — push + omnichannel order detail; separate from STOCK CHECKER).
        'partner_id' => env('SHOPEE_ADMIN_PARTNER_ID'),
        'partner_key' => env('SHOPEE_ADMIN_PARTNER_KEY'),
        'base_url' => env('SHOPEE_ADMIN_BASE_URL', env('SHOPEE_BASE_URL', 'https://partner.shopeemobile.com')),
        'redirect_url' => env('SHOPEE_ADMIN_REDIRECT_URL', 'https://aria3.corenationactive.com/shopee-admin/oauth/callback'),
    ],

    'shopee_push' => [
        // Push Mechanism webhook — use Live Partner ID + Live **Push** Partner Key from Console (Push page / App detail).
        // Not the Live Open API Partner Key (stock/ads). Push key is only for inbound webhook Authorization HMAC.
        'partner_id' => env('SHOPEE_PUSH_PARTNER_ID', env('SHOPEE_ADMIN_PARTNER_ID', env('SHOPEE_STOCK_PARTNER_ID'))),
        'partner_key' => env('SHOPEE_PUSH_PARTNER_KEY'),
        'verify_signature' => filter_var(env('SHOPEE_PUSH_VERIFY_SIGNATURE', true), FILTER_VALIDATE_BOOLEAN),
        /** Must match Console callback URL exactly if proxies alter $request->url(). */
        'callback_url' => env('SHOPEE_PUSH_CALLBACK_URL'),
        /** Persist selected pushes to shopee_push_inbox (off in production after URL verify). */
        'store_inbox' => filter_var(env('SHOPEE_PUSH_STORE_INBOX', false), FILTER_VALIDATE_BOOLEAN),
        /** Push codes to store when store_inbox=true: 0=URL verify sample, 3=order status, 4=tracking. */
        'store_inbox_codes' => array_map('intval', array_filter(explode(',', env('SHOPEE_PUSH_STORE_INBOX_CODES', '0,3,4')))),
        /** Minimal shadow row on push; full get_order_detail loads on omnichannel detail (no Aria post). */
        'shadow_enabled' => filter_var(env('SHOPEE_PUSH_SHADOW_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        /** Reuse cached shadow detail on repeat detail visits when younger than this (seconds). 0 = until manual refresh. */
        'shadow_detail_ttl_seconds' => (int) env('SHOPEE_PUSH_SHADOW_DETAIL_TTL_SECONDS', 300),
        /** Known Console "Push Test Data" ordersn values (comma-separated). */
        'test_push_ordersn' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SHOPEE_PUSH_TEST_ORDERSN', '220810QSK8S7BX')),
        ))),
        /**
         * On Shopee push code 3 (paid / awaiting shipment), upsert jubelioorders placeholder
         * with invoice SP-{ordersn}. Does not post SELL — Jubelio webhook + cron unchanged.
         */
        'precreate_jubelio_queue' => filter_var(env('SHOPEE_PRECREATE_JUBELIO_QUEUE', false), FILTER_VALIDATE_BOOLEAN),
        /**
         * Shopee data.status values that trigger pre-create (uppercase). Empty = use
         * marketplace.webhook_status_buckets "awaiting_shipment" (READY_TO_SHIP, PROCESSED, RETRY_SHIP).
         */
        'precreate_trigger_statuses' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SHOPEE_PRECREATE_TRIGGER_STATUSES', '')),
        ))),
    ],

    'jubelio' => [
        'active' => env('JUBELIO_ACTIVE', false),
        'poll_days' => (int) env('JUBELIO_POLL_DAYS', 7),
        'token_ttl_hours' => (int) env('JUBELIO_TOKEN_TTL_HOURS', 10),
        'url' => env('JUBELIO_URL'),
        'email' => env('JUBELIO_EMAIL'),
        'password' => env('JUBELIO_PASSWORD'),
        'verify_ssl' => env('JUBELIO_VERIFY_SSL', true),
        'webhook_secret' => env('JUBELIO_WEBHOOK_SECRET', 'corenation2025'),
        /** Get-orders / poll: ignore list rows older than this many days (webhook miss catch-up only). */
        'order_queue_max_age_days' => (int) env('JUBELIO_ORDER_QUEUE_MAX_AGE_DAYS', 30),
        /** SHIPPED webhook: do not queue legacy orders before Aria cutover. */
        'webhook_order_cutoff_date' => env('JUBELIO_WEBHOOK_ORDER_CUTOFF_DATE', '2025-03-06'),
    ],

];
