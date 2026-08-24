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

    'shopee_ads' => [
        'active' => env('SHOPEE_ADS_ACTIVE', false),
        'partner_id' => env('SHOPEE_PARTNER_ID'),
        'partner_key' => env('SHOPEE_PARTNER_KEY'),
        'base_url' => env('SHOPEE_BASE_URL', 'https://partner.shopeemobile.com'),
        'redirect_url' => env('SHOPEE_REDIRECT_URL'),
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
    ],

];
