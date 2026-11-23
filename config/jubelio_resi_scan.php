<?php

/**
 * Mobile resi (tracking) barcode scan — marketplace invoice prefixes and Jubelio store matching.
 *
 * Invoice format in Jubelio/Aria is typically "{PREFIX}-{barcode}" (e.g. SP-250302JA65X2S0).
 * Adjust prefixes here if your Jubelio channel uses different codes.
 */
return [
    'source' => 3,

    /** Window for Jubelio `q` search on sales orders (targeted, not paginated list scan). */
    'tracking_search_days' => 21,

    'marketplaces' => [
        'shopee' => [
            'label' => 'Shopee',
            'invoice_prefix' => 'SP',
            'store_name_patterns' => ['shopee'],
            'resolve_invoice_from_tracking' => true,
        ],
        'tiktok' => [
            'label' => 'TikTok Shop',
            'invoice_prefix' => 'TT',
            'store_name_patterns' => ['tiktok'],
            'resolve_invoice_from_tracking' => true,
            /** No Shopee-style push placeholder — scan may queue when AWB resolves before webhook. */
            'insert_queue_row_on_scan' => true,
        ],
        'tokopedia' => [
            'label' => 'Tokopedia',
            'invoice_prefix' => 'TP',
            'store_name_patterns' => ['tokopedia'],
            'resolve_invoice_from_tracking' => true,
            'insert_queue_row_on_scan' => true,
        ],
        'lazada' => [
            'label' => 'Lazada',
            'invoice_prefix' => 'LZ',
            'store_name_patterns' => ['lazada'],
            /** Jubelio salesorder_no is `{PREFIX}-{channelOrderId}-{store_id}` — same suffix rule as TikTok. */
            'append_jubelio_store_id' => true,
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
