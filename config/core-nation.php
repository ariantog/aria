<?php

return [
    'item_image_path' => env('ITEM_IMAGE_PATH', public_path('asset/')),
    'item_image_url' => env('ITEM_IMAGE_URL', '/asset/'),
    'cdn_url' => env('CDN_URL', '/asset/'),
    'cdn_path' => env('CDN_PATH', public_path('asset/')),
];
