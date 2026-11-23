<?php

/**
 * Shopee FLEXI export invoice prefixes (Jubelio queue / resi scan / pre-create).
 * Domestic Indonesia Shopee remains SP unless overridden on shopee_syncs.
 */
return [
    'flexi_prefixes' => [
        'SPMY' => 'Malaysia',
        'SPSG' => 'Singapore',
        'SPTH' => 'Thailand',
        'SPPH' => 'Philippines',
    ],

    'domestic_prefix' => 'SP',

    /**
     * Legacy env fallback when omnichannel_settings.flexi_staging_warehouse_id is empty (ShopeeFlexiStagingWarehouse).
     */
    'staging_warehouse_id' => (int) env('SHOPEE_FLEXI_STAGING_WAREHOUSE_ID', 0),
];