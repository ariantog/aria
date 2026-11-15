<?php

/**
 * Allowed Jubelio API names for POST/GET /api/jubelio-proxy.
 *
 * Bots may only call mapped names — never arbitrary URLs.
 */
return [

    'max_page_size' => (int) env('JUBELIO_PROXY_MAX_PAGE_SIZE', 200),

    'default_rate_limit_per_minute' => (int) env('JUBELIO_PROXY_RATE_LIMIT', 60),

    /** Global kill-switch for write (POST/PUT/PATCH) proxy entries. */
    'allow_write' => (bool) env('JUBELIO_PROXY_ALLOW_WRITE', false),

  /**
     * Phase 1 read APIs (GET). Keys are the `api` name bots send in the request body.
     *
     * @var array<string, array{
     *   method: string,
     *   access: 'read'|'write',
     *   url: string,
     *   path_params?: list<string>,
     *   query_params?: list<string>,
     *   body_params?: list<string>,
     *   default_query?: array<string, scalar>,
     *   contains_pii?: bool,
     *   contains_finance?: bool,
     *   enabled?: bool,
     * }>
     */
    'apis' => [
        'orders.list' => [
            'method' => 'GET',
            'access' => 'read',
            'url' => 'https://api2.jubelio.com/sales/orders/',
            'query_params' => [
                'page', 'pageSize', 'transactionDateFrom', 'transactionDateTo',
                'q', 'sortBy', 'sortDirection', 'store_id', 'location_id', 'status',
            ],
            'default_query' => ['page' => 1, 'pageSize' => 50],
            'contains_pii' => true,
        ],
        'orders.detail' => [
            'method' => 'GET',
            'access' => 'read',
            'url' => 'https://api2.jubelio.com/sales/orders/{id}',
            'path_params' => ['id'],
            'contains_pii' => true,
        ],
        'orders.ready_to_pick' => [
            'method' => 'GET',
            'access' => 'read',
            'url' => 'https://api2.jubelio.com/sales/orders/ready-to-pick/',
            'query_params' => ['page', 'pageSize', 'q', 'sortBy', 'sortDirection'],
            'default_query' => ['page' => 1, 'pageSize' => 50],
            'contains_pii' => true,
        ],
        'returns.list' => [
            'method' => 'GET',
            'access' => 'read',
            'url' => 'https://api2.jubelio.com/sales/sales-returns/',
            'query_params' => [
                'page', 'pageSize', 'transactionDateFrom', 'transactionDateTo',
                'q', 'sortBy', 'sortDirection',
            ],
            'default_query' => ['page' => 1, 'pageSize' => 50],
            'contains_pii' => true,
        ],
        'returns.detail' => [
            'method' => 'GET',
            'access' => 'read',
            'url' => 'https://api2.jubelio.com/sales/sales-returns/{id}',
            'path_params' => ['id'],
            'contains_pii' => true,
        ],
        'inventory.list' => [
            'method' => 'GET',
            'access' => 'read',
            'url' => 'https://api2.jubelio.com/inventory/',
            'query_params' => ['page', 'pageSize', 'sortDirection', 'sortBy', 'csv', 'q'],
            'default_query' => ['page' => 1, 'pageSize' => 50, 'sortDirection' => 'ASC', 'sortBy' => 'name'],
            'contains_finance' => true,
        ],
        'inventory.search' => [
            'method' => 'GET',
            'access' => 'read',
            'url' => 'https://api2.jubelio.com/inventory/items/to-stock/',
            'query_params' => ['q', 'page', 'pageSize'],
            'default_query' => ['page' => 1, 'pageSize' => 50],
            'contains_finance' => true,
        ],
        'locations.list' => [
            'method' => 'GET',
            'access' => 'read',
            'url' => 'https://api2.jubelio.com/locations/',
            'query_params' => ['page', 'pageSize', 'q'],
            'default_query' => ['page' => 1, 'pageSize' => 50],
        ],
        'locations.detail' => [
            'method' => 'GET',
            'access' => 'read',
            'url' => 'https://api2.jubelio.com/locations/{id}',
            'path_params' => ['id'],
        ],

        /** Write entries — disabled in phase 1; require client write_enabled + allow_write. */
        'stock.adjust_warehouse' => [
            'method' => 'POST',
            'access' => 'write',
            'enabled' => false,
            'url' => 'https://api2.jubelio.com/inventory/adjustments/warehouse',
            'body_params' => ['location_id', 'items'],
        ],
    ],

    /** Substring match on array keys (case-insensitive) when stripping responses. */
    'finance_field_patterns' => [
        'cost', 'hpp', 'buy_price', 'average_cost', 'cogs', 'landed_cost', 'unit_cost',
    ],

    'pii_field_patterns' => [
        'phone', 'mobile', 'email', 'address', 'shipping_address', 'billing_address',
        'recipient_address', 'customer_phone', 'customer_email',
    ],

    'secret_field_patterns' => [
        'token', 'password', 'access_token', 'refresh_token', 'authorization', 'secret',
    ],
];