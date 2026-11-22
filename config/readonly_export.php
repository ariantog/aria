<?php

return [

    /*
    | Grok / read-only bulk export API bearer token (Authorization: Bearer …).
    | Set READONLY_EXPORT_BEARER_TOKEN in the server env / bot vault — never in chat.
    | When set, requests with a matching bearer token authenticate as the user below.
    */
    'bearer_token' => env('READONLY_EXPORT_BEARER_TOKEN'),

    /** Username for bearer auth (default service account). */
    'username' => env('READONLY_EXPORT_USERNAME', 'grok'),

    /**
     * When false (default), POST /api/readonly/restock/apply is blocked — bot should emit CSV/XLSX for humans.
     * Set READONLY_RESTOCK_GROK_APPLY_ENABLED=true only when ops approves automated sheet updates.
     */
    'restock_grok_apply_enabled' => filter_var(
        env('READONLY_RESTOCK_GROK_APPLY_ENABLED', false),
        FILTER_VALIDATE_BOOLEAN,
    ),

];
