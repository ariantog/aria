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

    'jubelio' => [
        'active' => env('JUBELIO_ACTIVE', false),
        'url' => env('JUBELIO_URL'),
        'email' => env('JUBELIO_EMAIL'),
        'password' => env('JUBELIO_PASSWORD'),
        'verify_ssl' => env('JUBELIO_VERIFY_SSL', true),
        'webhook_secret' => env('JUBELIO_WEBHOOK_SECRET', 'corenation2025'),
        'webhook_auto_process' => env('JUBELIO_WEBHOOK_AUTO_PROCESS', false),
    ],

];
