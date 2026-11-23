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
        'SPBR' => 'Brunei',
    ],

    'domestic_prefix' => 'SP',
];