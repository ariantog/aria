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

];
