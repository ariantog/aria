<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reporting data cutover date
    |--------------------------------------------------------------------------
    |
    | Transactions before this date are omitted from reporting aggregate tables
    | (reporting_entity_monthly_summaries, reporting_operation_monthly_summaries).
    | Legacy monthly_* summaries are unaffected.
    |
    */
    'cutover_date' => '2025-01-01',

];
