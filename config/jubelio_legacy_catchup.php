<?php

/**
 * One-off repair: Jubelio get-orders posted sells with transaction date before 2026
 * but row created after the bad deploy. Remove this config with the repair UI when done.
 */
return [
    /** Sells with header date strictly before this day (Y-m-d). */
    'transaction_date_before' => env('JUBELIO_LEGACY_CATCHUP_DATE_BEFORE', '2025-12-31'),

    /** Only rows inserted after this timestamp (Y-m-d H:i:s). */
    'created_after' => env('JUBELIO_LEGACY_CATCHUP_CREATED_AFTER', '2026-06-30 20:39:03'),

    /** Prefix on move.notes linking back to the bad sell transaction id. */
    'restore_note_prefix' => 'jubelio-legacy-catchup-restore:',

    /** Jubelio cron user on transactions.user_id (L10: -100). */
    'cron_user_id' => (int) env('JUBELIO_LEGACY_CATCHUP_CRON_USER_ID', -100),
];
