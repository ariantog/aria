<?php

use App\Services\Tax\ParsedFakturPajak;
use Illuminate\Support\Carbon;

it('uses harga jual plus ppn as faktur total not dpp plus ppn', function () {
    $parsed = new ParsedFakturPajak(
        fakturNumber: '04002600298450234',
        fakturDate: Carbon::parse('2026-07-31'),
        fakturDatePlace: 'Jakarta',
        sellerName: 'INDOSPORT',
        sellerNpwp: '0504330085044000',
        buyerName: 'MDS',
        buyerNpwp: '0013179569054000',
        grossTotal: 21_221_157.0,
        discountTotal: 0,
        dpp: 19_452_728.0,
        ppn: 2_334_327.0,
        ppnbm: 0,
        signatoryName: 'TEST',
        sourceFormat: 'mds_output_tax_invoice',
    );

    expect($parsed->grossIncludingTax())->toBe(23_555_484.0)
        ->and($parsed->dpp + $parsed->ppn)->toBe(21_787_055.0);
});

it('subtracts potongan harga before adding ppn', function () {
    $parsed = new ParsedFakturPajak(
        fakturNumber: '01000123456789012',
        fakturDate: null,
        fakturDatePlace: null,
        sellerName: 'PKP',
        sellerNpwp: '0504330085044000',
        buyerName: 'Buyer',
        buyerNpwp: '0013179569054000',
        grossTotal: 1_200_000.0,
        discountTotal: 200_000.0,
        dpp: 916_667.0,
        ppn: 110_000.0,
        ppnbm: 0,
        signatoryName: null,
        sourceFormat: 'mds_output_tax_invoice',
    );

    expect($parsed->grossIncludingTax())->toBe(1_110_000.0);
});

it('subtracts uang muka and ignores ppnbm in the payable total', function () {
    $parsed = new ParsedFakturPajak(
        fakturNumber: '01000123456789012',
        fakturDate: null,
        fakturDatePlace: null,
        sellerName: 'PKP',
        sellerNpwp: '0504330085044000',
        buyerName: 'Buyer',
        buyerNpwp: '0013179569054000',
        grossTotal: 1_200_000.0,
        discountTotal: 50_000.0,
        dpp: 916_667.0,
        ppn: 110_000.0,
        ppnbm: 99_000.0,
        signatoryName: null,
        sourceFormat: 'mds_output_tax_invoice',
        downPaymentTotal: 150_000.0,
    );

    expect($parsed->grossIncludingTax())->toBe(1_110_000.0);
});
