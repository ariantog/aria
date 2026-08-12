<?php

/**
 * Maps L12 model/table names to the live Laravel 10 production schema.
 *
 * Enable with PRODUCTION_SCHEMA=true in .env when Aria shares the production DB.
 * Leave false (default) for greenfield installs / SQLite tests (addrbooks, warehouse_items, …).
 */
return [

    'enabled' => (bool) env('PRODUCTION_SCHEMA', false),

    'tables' => [
        'addrbook' => 'customers',
        'addrbook_stat' => 'customerstat',
        'addrbook_daily' => 'customer_class',
        'item_group' => 'item_group',
        'warehouse_item' => 'warehouse_item',
        'produksi' => 'prod_produksi',
        'borongan' => 'prod_borongan',
        'borongan_detail' => 'prod_borongandetail',
        'worker' => 'prod_worker',
        'deleted_transaction' => 'deleted',
        'deleted_transaction_detail' => 'deleted_details',
    ],

    /*
    |--------------------------------------------------------------------------
    | Column aliases (L12 attribute => production column)
    |--------------------------------------------------------------------------
    */
    'columns' => [
        'transactions' => [
            'invoice_number' => 'invoice',
            'due_date' => 'due',
            'tax_amount' => 'ppn',
            'discount_percent' => 'discount',
        ],
        'users' => [
            'is_active' => 'active',
        ],
        'addrbooks' => [
            'member_id' => 'memberId',
        ],
        'addrbook_stat' => [
            'addrbook_id' => 'customer_id',
        ],
        'addrbook_daily' => [
            'addrbook_id' => 'customer_id',
            'type' => 'customer_type',
        ],
    ],

];
