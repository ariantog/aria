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

    /*
    |--------------------------------------------------------------------------
    | Supplier Umum (neraca hutang usaha)
    |--------------------------------------------------------------------------
    |
    | Catch-all supplier contacts whose year-to-date Buy totals are the
    | hutang usaha line. Matches production names "Supplier Umum" and
    | "Supplier Umum - PT CORE". Addrbook running balances are not used.
    |
    */
    'supplier_umum_name_needle' => 'supplier umum',

    /*
    |--------------------------------------------------------------------------
    | PPh Final rate (non-PKP entity CashIn)
    |--------------------------------------------------------------------------
    */
    'pph_final_rate' => 0.005,

    /*
    |--------------------------------------------------------------------------
    | PPh withholding rate (Cash Out with PPN, e.g. rental PPh 10%)
    |--------------------------------------------------------------------------
    */
    'pph_withholding_rate' => 10,

    /*
    |--------------------------------------------------------------------------
    | PKP CashIn keluaran inference
    |--------------------------------------------------------------------------
    |
    | Customer/reseller CashIn to an entity bank does not infer PPN keluaran;
    | keluaran is tracked on Sell (ppn column). Other payer types may still infer.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Persediaan roll-forward
    |--------------------------------------------------------------------------
    |
    | Company-wide inventory ledger starts January 2026. Opening seed is the
    | `reporting.persediaan_awal` setting (this config is the fallback).
    |
    | Manufactured COGS uses pcs that entered gudang, borongan labour (Gaji
    | Mingguan if borongan is 0), and Material Produksi cash-out. Purchased
    | items still use qty × items.cost. Conversion costs are capitalized
    | only in months that have produksi / borongan / manufactured sales.
    |
    */
    'persediaan_start' => '2026-01-01',
    'persediaan_awal' => 0,

];
