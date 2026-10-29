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

];
