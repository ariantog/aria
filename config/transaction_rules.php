<?php

return [
    'buy' => [
        'id' => \App\Models\Transaction::TYPE_BUY,
        'price_source' => 'cost',
        'sender_type' => [\App\Models\Addrbook::TYPE_SUPPLIER],
        'receiver_type' => [\App\Models\Addrbook::TYPE_WAREHOUSE],
    ],
    'sell' => [
        'id' => \App\Models\Transaction::TYPE_SELL,
        'price_source' => 'price',
        'sender_type' => [\App\Models\Addrbook::TYPE_WAREHOUSE],
        'receiver_type' => [\App\Models\Addrbook::TYPE_CUSTOMER, \App\Models\Addrbook::TYPE_RESELLER],
    ],
    'move' => [
        'id' => \App\Models\Transaction::TYPE_MOVE,
        'price_source' => 'price',
        'sender_type' => [\App\Models\Addrbook::TYPE_WAREHOUSE, \App\Models\Addrbook::TYPE_V_WAREHOUSE],
        'receiver_type' => [\App\Models\Addrbook::TYPE_WAREHOUSE, \App\Models\Addrbook::TYPE_V_WAREHOUSE],
    ],
    'transfer' => [
        'id' => \App\Models\Transaction::TYPE_TRANSFER,
        'price_source' => 'price',
        'sender_type' => [\App\Models\Addrbook::TYPE_BANK, \App\Models\Addrbook::TYPE_V_ACCOUNT],
        'receiver_type' => [\App\Models\Addrbook::TYPE_BANK, \App\Models\Addrbook::TYPE_V_ACCOUNT],
    ],
    'adjust' => [
        'id' => \App\Models\Transaction::TYPE_ADJUST,
        'price_source' => 'price',
        'sender_type' => [\App\Models\Addrbook::TYPE_ACCOUNT, \App\Models\Addrbook::TYPE_CUSTOMER, \App\Models\Addrbook::TYPE_SUPPLIER, \App\Models\Addrbook::TYPE_RESELLER],
        'receiver_type' => [\App\Models\Addrbook::TYPE_ACCOUNT, \App\Models\Addrbook::TYPE_CUSTOMER, \App\Models\Addrbook::TYPE_SUPPLIER, \App\Models\Addrbook::TYPE_RESELLER],
    ],
    'return' => [
        'id' => \App\Models\Transaction::TYPE_RETURN,
        'price_source' => 'price',
        'sender_type' => [\App\Models\Addrbook::TYPE_CUSTOMER, \App\Models\Addrbook::TYPE_RESELLER],
        'receiver_type' => [\App\Models\Addrbook::TYPE_WAREHOUSE],
    ],
    'production' => [
        'id' => \App\Models\Transaction::TYPE_PRODUCTION,
        'price_source' => 'cost',
    ],
    'return-supplier' => [
        'id' => \App\Models\Transaction::TYPE_RETURN_SUPPLIER,
        'price_source' => 'cost',
        'sender_type' => [\App\Models\Addrbook::TYPE_WAREHOUSE],
        'receiver_type' => [\App\Models\Addrbook::TYPE_SUPPLIER, \App\Models\Addrbook::TYPE_RESELLER],
    ],
    'cash-in' => [
        'id' => \App\Models\Transaction::TYPE_CASH_IN,
        'receiver_type' => [\App\Models\Addrbook::TYPE_BANK],
        'sender_type' => \App\Models\Addrbook::cashPartyTypes(),
    ],
    'cash-out' => [
        'id' => \App\Models\Transaction::TYPE_CASH_OUT,
        'sender_type' => [\App\Models\Addrbook::TYPE_BANK],
        'receiver_type' => \App\Models\Addrbook::cashPartyTypes(),
    ],
    'depreciation' => [
        'id' => \App\Models\Transaction::TYPE_DEPRECIATION,
        'price_source' => 'cost',
        'sender_type' => [\App\Models\Addrbook::TYPE_ACCOUNT],
        'receiver_type' => [\App\Models\Addrbook::TYPE_ACCOUNT],
    ],
];
