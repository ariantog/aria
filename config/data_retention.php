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

];
