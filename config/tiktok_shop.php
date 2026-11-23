<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TikTok Shop API driver
    |--------------------------------------------------------------------------
    |
    | mock — in-app cache-backed demo + mock Open API routes (no extra DB tables).
    | live — real TikTok Shop Open Platform (requires approved partner credentials).
    |
    */
    'driver' => env('TIKTOK_SHOP_DRIVER', 'mock'),

    'app_key' => env('TIKTOK_SHOP_APP_KEY'),
    'app_secret' => env('TIKTOK_SHOP_APP_SECRET'),
    'shop_cipher' => env('TIKTOK_SHOP_SHOP_CIPHER'),

    /** Production Open API base; mock routes are served under /tiktok-shop/mock/open_api/… */
    'api_base_url' => env('TIKTOK_SHOP_API_BASE_URL', 'https://open-api.tiktokglobalshop.com'),

    /** Shared secret for mock Open API routes (header x-tts-mock-token). */
    'mock_api_token' => env('TIKTOK_SHOP_MOCK_API_TOKEN', 'aria-mock-tts-token'),

    'api_version' => env('TIKTOK_SHOP_API_VERSION', '202309'),

    /** Shop access token fallback when Grok fetch is off or fails (prefer Grok cache in settings). */
    'access_token' => env('TIKTOK_SHOP_ACCESS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Grok bot — outbound TikTok token (Aria → Grok, inverse of Shopee readonly export)
    |--------------------------------------------------------------------------
    |
    | When enabled, Aria GETs access_token from the bot before live Open API calls.
    | Cached in settings slug tiktok_shop_oauth (see TikTokShopAccessTokenResolver).
    |
    */
    'grok_token' => [
        'enabled' => filter_var(env('TIKTOK_SHOP_GROK_TOKEN_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'url' => env('TIKTOK_SHOP_GROK_TOKEN_URL'),
        /** Shared secret with Grok; defaults to READONLY_EXPORT_BEARER_TOKEN when unset. */
        'bearer_token' => env('TIKTOK_SHOP_GROK_TOKEN_BEARER', env('READONLY_EXPORT_BEARER_TOKEN')),
        'timeout_seconds' => (int) env('TIKTOK_SHOP_GROK_TOKEN_TIMEOUT', 15),
    ],

    /** Order API path version for getOrderDetail (Partner Center → Order API). */
    'order_api_version' => env('TIKTOK_SHOP_ORDER_API_VERSION', '202507'),

    /**
     * After TikTok Shop webhook ingest, queue read-only Get Order Detail to set commerce_platform
     * (e.g. TOKOPEDIA) on marketplace_channel_orders + marketplace_webhook_events.
     */
    'enrich_commerce_platform' => filter_var(env('TIKTOK_SHOP_ENRICH_COMMERCE_PLATFORM', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Inbound webhooks (independent of driver=mock|live)
    |--------------------------------------------------------------------------
    |
    | Receives ORDER_STATUS_CHANGE etc. from Partner Center — no Open API writes.
    | TIKTOK_SHOP_DRIVER may stay mock while webhooks are enabled on aria3.
    |
    */
    'webhook' => [
        'enabled' => filter_var(env('TIKTOK_SHOP_WEBHOOK_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'verify_signature' => filter_var(env('TIKTOK_SHOP_WEBHOOK_VERIFY_SIGNATURE', true), FILTER_VALIDATE_BOOLEAN),
        /** Persist payloads to tiktok_shop_webhook_inbox (read-only observability). */
        'store_inbox' => filter_var(env('TIKTOK_SHOP_WEBHOOK_STORE_INBOX', true), FILTER_VALIDATE_BOOLEAN),
    ],

];
