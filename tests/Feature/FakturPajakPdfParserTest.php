<?php

use App\Services\Tax\FakturPajakPdfParser;

it('parses MDS output tax invoice faktur pajak PDF', function () {
    $fixture = base_path('tests/Fixtures/faktur-pajak/mds-output-tax-invoice-sample.pdf');

    $parsed = app(FakturPajakPdfParser::class)->parseFile($fixture);

    expect($parsed->fakturNumber)->toBe('04002600298450234')
        ->and($parsed->fakturDate?->toDateString())->toBe('2026-07-31')
        ->and($parsed->fakturDatePlace)->toBe('KOTA ADM. JAKARTA UTARA')
        ->and($parsed->sellerName)->toBe('INDOSPORT ADIGUNA PERKASA')
        ->and($parsed->sellerNpwp)->toBe('0504330085044000')
        ->and($parsed->buyerName)->toBe('MDS RETAILING TBK')
        ->and($parsed->buyerNpwp)->toBe('0013179569054000')
        ->and($parsed->grossTotal)->toBe(21_221_157.0)
        ->and($parsed->discountTotal)->toBe(0.0)
        ->and($parsed->dpp)->toBe(19_452_728.0)
        ->and($parsed->ppn)->toBe(2_334_327.0)
        ->and($parsed->ppnbm)->toBe(0.0)
        ->and($parsed->signatoryName)->toBe('ARIANTO GUNAWAN')
        ->and($parsed->sourceFormat)->toBe(FakturPajakPdfParser::FORMAT_MDS_OUTPUT_TAX_INVOICE)
        ->and($parsed->lineItems)->toHaveCount(7)
        ->and($parsed->lineItems[0]['name'])->toBe('Celana Panjang')
        ->and($parsed->lineItems[0]['quantity'])->toBe(18.0)
        ->and($parsed->lineItems[0]['total'])->toBe(2_524_409.1);
});

it('rejects non-faktur PDF text', function () {
    app(FakturPajakPdfParser::class)->parseText('Random invoice document');
})->throws(\InvalidArgumentException::class);

it('parses faktur fields from extracted text without PDF binary', function () {
    $text = <<<'TEXT'
Faktur Pajak
Kode dan Nomor Seri Faktur Pajak: 01000123456789012
Pengusaha Kena Pajak:
Nama : CV TEST PKP
Alamat : Jl. Test 1
NPWP : 0123456789012345
Pembeli Barang Kena Pajak/Penerima Jasa Kena Pajak:
Nama : CUSTOMER TBK
Alamat : Jl. Buyer 2
NPWP : 9876543210987654
Harga Jual / Penggantian / Uang Muka / Termin 1.110.000,00
Dikurangi Potongan Harga 0,00
Dasar Pengenaan Pajak 1.000.000,00
Jumlah PPN (Pajak Pertambahan Nilai) 110.000,00
Jumlah PPnBM (Pajak Penjualan atas Barang Mewah) 0,00
KOTA ADM. JAKARTA UTARA, 15 Agustus 2025
Ditandatangani secara elektronik
TEST SIGNER
(Referensi: )
TEXT;

    $parsed = app(FakturPajakPdfParser::class)->parseText($text);

    expect($parsed->fakturNumber)->toBe('01000123456789012')
        ->and($parsed->dpp)->toBe(1_000_000.0)
        ->and($parsed->ppn)->toBe(110_000.0)
        ->and($parsed->fakturDate?->toDateString())->toBe('2025-08-15');
});
