<?php

use App\Services\Shopee\ShopeeAwbPdfPortraitNormalizer;
use App\Services\Shopee\Support\ShopeeAwbPortraitPdf;
use setasign\Fpdi\PdfParser\StreamReader;

it('passes through invalid pdf bytes unchanged', function () {
    $binary = '%PDF-1.4 fake-awb-binary';

    $out = app(ShopeeAwbPdfPortraitNormalizer::class)->normalize($binary);

    expect($out)->toBe($binary);
});

it('rewrites landscape a6 awb to portrait a6', function () {
    $landscape = new ShopeeAwbPortraitPdf('L', 'mm');
    $landscape->AddPage('L', [148, 105]);
    $landscape->SetFont('Helvetica', '', 10);
    $landscape->Cell(120, 8, 'SPX-LANDSCAPE-A6');
    $input = $landscape->Output('S');

    $out = app(ShopeeAwbPdfPortraitNormalizer::class)->normalize($input);

    expect($out)->not->toBe($input)->toStartWith('%PDF');

    $reader = new ShopeeAwbPortraitPdf;
    $reader->setSourceFile(StreamReader::createByString($out));
    $tpl = $reader->importPage(1);
    $size = $reader->getTemplateSize($tpl);

    expect($size['height'])->toBeGreaterThan($size['width'])
        ->and((float) $size['width'])->toBeGreaterThan(100.0)
        ->and((float) $size['width'])->toBeLessThan(110.0)
        ->and((float) $size['height'])->toBeGreaterThan(143.0)
        ->and((float) $size['height'])->toBeLessThan(153.0);
});

it('crops wide landscape awb canvas to portrait a6', function () {
    $wide = new ShopeeAwbPortraitPdf('L', 'mm');
    $wide->AddPage('L', [297, 210]);
    $wide->SetFont('Helvetica', '', 12);
    $wide->Cell(90, 8, 'LABEL-LEFT');
    $input = $wide->Output('S');

    $out = app(ShopeeAwbPdfPortraitNormalizer::class)->normalize($input);

    $reader = new ShopeeAwbPortraitPdf;
    $reader->setSourceFile(StreamReader::createByString($out));
    $tpl = $reader->importPage(1);
    $size = $reader->getTemplateSize($tpl);

    expect((float) $size['width'])->toBeGreaterThan(100.0)
        ->and((float) $size['width'])->toBeLessThan(110.0)
        ->and((float) $size['height'])->toBeGreaterThan(143.0)
        ->and((float) $size['height'])->toBeLessThan(153.0);
});

it('leaves portrait a6 pdf unchanged', function () {
    $portrait = new ShopeeAwbPortraitPdf('P', 'mm');
    $portrait->AddPage('P', [105, 148]);
    $portrait->SetFont('Helvetica', '', 10);
    $portrait->Cell(80, 8, 'ALREADY-PORTRAIT');
    $input = $portrait->Output('S');

    $out = app(ShopeeAwbPdfPortraitNormalizer::class)->normalize($input);

    expect($out)->toBe($input);
});
