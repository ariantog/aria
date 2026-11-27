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

    /** Partner Center → App & Service service id (Seller authorization link). */
    'service_id' => env('TIKTOK_SHOP_SERVICE_ID'),

    /** OAuth callback registered in Partner Center (e.g. https://aria3…/tiktok-shop/oauth/callback). */
    'redirect_url' => env('TIKTOK_SHOP_REDIRECT_URL', 'https://aria3.corenationactive.com/tiktok-shop/oauth/callback'),

    /** Seller authorization entry (ROW). Override for US: services.us.tiktokshop.com */
    'authorize_url' => env('TIKTOK_SHOP_AUTHORIZE_URL', 'https://services.tiktokshop.com/open/authorize'),

    /** Token exchange + refresh host (global). */
    'token_base_url' => env('TIKTOK_SHOP_TOKEN_BASE_URL', 'https://auth.tiktok-shops.com/api/v2/token'),

    /** Production Open API base; mock routes are served under /tiktok-shop/mock/open_api/… */
    'api_base_url' => env('TIKTOK_SHOP_API_BASE_URL', 'https://open-api.tiktokglobalshop.com'),

    /** Shared secret for mock Open API routes (header x-tts-mock-token). */
    'mock_api_token' => env('TIKTOK_SHOP_MOCK_API_TOKEN', 'aria-mock-tts-token'),

    'api_version' => env('TIKTOK_SHOP_API_VERSION', '202309'),

    /** Emergency fallback when settings OAuth row is empty (prefer tiktok_shop_oauth + authorize UI). */
    'access_token' => env('TIKTOK_SHOP_ACCESS_TOKEN'),

    /** Order API path version for getOrderDetail (Partner Center → Order API). */
    'order_api_version' => env('TIKTOK_SHOP_ORDER_API_VERSION', '202507'),

    /** Product API path version (Search Products, etc.). */
    'product_api_version' => env('TIKTOK_SHOP_PRODUCT_API_VERSION', '202502'),

    /** Fulfillment API path version (Ship Package, Get Package Shipping Document). */
    'fulfillment_api_version' => env('TIKTOK_SHOP_FULFILLMENT_API_VERSION', '202309'),

    /**
     * Omnichannel bulk AWB — Get Package Shipping Document query params (Indonesia / SEA default A6 PDF).
     *
     * @see https://partner.tiktokshop.com/docv2/page/get-package-shipping-document-202309
     */
    'shipping_document_type' => env('TIKTOK_SHOP_SHIPPING_DOCUMENT_TYPE', 'SHIPPING_LABEL'),
    'shipping_document_size' => env('TIKTOK_SHOP_SHIPPING_DOCUMENT_SIZE', 'A6'),
    'shipping_document_format' => env('TIKTOK_SHOP_SHIPPING_DOCUMENT_FORMAT', 'PDF'),

    /** TikTok Shipping (SEA): handover_method for Ship Package when label not ready (PICKUP or DROP_OFF). */
    'ship_handover_method' => env('TIKTOK_SHOP_SHIP_HANDOVER_METHOD', 'PICKUP'),

    /** Poll Get Package Shipping Document after Ship Package (seconds). */
    'awb_document_poll_seconds' => (int) env('TIKTOK_SHOP_AWB_DOCUMENT_POLL_SECONDS', 60),

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
        /** CDN edge → POST /tiktok-shop/webhook/relay (see doc/marketplace-webhook-cdn-relay.md). */
        'relay' => [
            'enabled' => filter_var(env('TIKTOK_SHOP_WEBHOOK_RELAY_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'bearer' => env('TIKTOK_SHOP_WEBHOOK_RELAY_BEARER', ''),
        ],
    ],

];