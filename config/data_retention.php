<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Transaction retention (whole calendar years)
    |--------------------------------------------------------------------------
    |
    | Live DB keeps the current year and the previous (retention_years - 1) full
    | years. Older yearly partitions are eligible for archive + DROP PARTITION.
    | Example: retention_years=5 in 2026 → keep 2021–2026, archive/drop ≤2020.
    |
    */

    'retention_years' => (int) env('DATA_RETENTION_YEARS', 5),

    /*
    |--------------------------------------------------------------------------
    | Batch sizes (avoid long locks / memory spikes on production)
    |--------------------------------------------------------------------------
    */

    'copy_batch_size' => (int) env('DATA_RETENTION_COPY_BATCH', 500),

    'item_purge_batch_size' => (int) env('DATA_RETENTION_ITEM_PURGE_BATCH', 500),

    /*
    |--------------------------------------------------------------------------
    | Legacy bulk-touch timestamps (items / customers created_at)
    |--------------------------------------------------------------------------
    |
    | L10 production schema defines items.created_at (and customers.created_at)
    | with ON UPDATE CURRENT_TIMESTAMP, so any bulk UPDATE rewrote created_at to
    | the migration run time — not the real insert date. Orphan purge must not
    | treat those rows as "too new" when they have no transaction references.
    |
    | 2026-08-24 02:58:40 UTC ≈ 09:58 WIB — production migration window that ran
    | zero-date normalization, legacy_code backfill, and/or items.qty backfill.
    |
    */

    'legacy_bulk_touch_timestamps' => [
        '2026-08-24 02:58:40',
    ],

];
