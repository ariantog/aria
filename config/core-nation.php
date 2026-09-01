<?php

return [
    'item_image_path' => env('ITEM_IMAGE_PATH', public_path('asset/')),
    'item_image_url' => env('ITEM_IMAGE_URL', '/asset/'),
    'cdn_url' => env('CDN_URL', '/asset/'),
    'cdn_path' => env('CDN_PATH', public_path('asset/')),
    'invoice_path' => env('INVOICE_PATH') ?: public_path('asset/inv/'),
    'invoice_url' => env('INVOICE_URL', 'https://invoice.corenationactive.com/'),

    /*
    |--------------------------------------------------------------------------
    | New-subdomain install
    |--------------------------------------------------------------------------
    |
    | The current live host already has the L10/L12 production database.
    | `php artisan app:install-new-domain` and the new-domain seeders refuse
    | to run there. Set ARIA_LEGACY_PRODUCTION=true on that host as a belt-
    | and-suspenders flag. Set ARIA_NEW_DOMAIN=true only on an empty
    | subdomain database whose APP_URL still happens to match a legacy host.
    | A production data fingerprint (Crystal ledgers / cv-crystal) always wins.
    |
    */
    'legacy_production' => (bool) env('ARIA_LEGACY_PRODUCTION', false),
    'new_domain' => (bool) env('ARIA_NEW_DOMAIN', false),
    'legacy_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('ARIA_LEGACY_HOSTS', 'aria.corenationactive.com')),
    ))),
];
