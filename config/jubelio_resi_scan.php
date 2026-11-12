<?php

/**
 * Mobile resi (tracking) barcode scan — marketplace invoice prefixes and Jubelio store matching.
 *
 * Invoice format in Jubelio/Aria is typically "{PREFIX}-{barcode}" (e.g. SP-250302JA65X2S0).
 * Adjust prefixes here if your Jubelio channel uses different codes.
 */
return [
    'source' => 3,

    'marketplaces' => [
        'shopee' => [
            'label' => 'Shopee',
            'invoice_prefix' => 'SP',
            'store_name_patterns' => ['shopee'],
        ],
        'tiktok' => [
            'label' => 'TikTok Shop',
            'invoice_prefix' => 'TT',
            'store_name_patterns' => ['tiktok'],
        ],
        'tokopedia' => [
            'label' => 'Tokopedia',
            'invoice_prefix' => 'TP',
            'store_name_patterns' => ['tokopedia'],
        ],
        'lazada' => [
            'label' => 'Lazada',
            'invoice_prefix' => 'LZ',
            'store_name_patterns' => ['lazada'],
        ],
        'blibli' => [
            'label' => 'Blibli',
            'invoice_prefix' => 'BL',
            'store_name_patterns' => ['blibli'],
        ],
        'zalora' => [
            'label' => 'Zalora',
            'invoice_prefix' => 'ZA',
            'store_name_patterns' => ['zalora'],
        ],
    ],
];
