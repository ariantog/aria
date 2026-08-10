<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$inputPath = __DIR__.'/CORENATION';
$outputPath = __DIR__.'/CORENATION-daftar-ketidakcocokan.xlsx';

$raw = file_get_contents($inputPath);
if ($raw === false) {
    fwrite(STDERR, "Could not read {$inputPath}\n");
    exit(1);
}

if (! preg_match('/<!DOCTYPE html>.*?<\/html>/is', $raw, $htmlMatch)) {
    fwrite(STDERR, "Could not extract HTML from MHTML file\n");
    exit(1);
}

$html = $htmlMatch[0];

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
libxml_clear_errors();

$xpath = new DOMXPath($dom);

$headingNodes = $xpath->query('//h2[contains(normalize-space(.), "Daftar Ketidakcocokan")]');
if ($headingNodes === false || $headingNodes->length === 0) {
    fwrite(STDERR, "Could not find Daftar Ketidakcocokan heading\n");
    exit(1);
}

$table = null;
$heading = $headingNodes->item(0);
if ($heading instanceof DOMElement) {
    $container = $heading;
    while ($container->parentNode instanceof DOMElement) {
        $container = $container->parentNode;
        $tables = $container->getElementsByTagName('table');
        if ($tables->length > 0) {
            $table = $tables->item(0);
            break;
        }
    }
}

if (! $table instanceof DOMElement) {
    fwrite(STDERR, "Could not find mismatch table\n");
    exit(1);
}

$headers = [
    'Item Name (Aria)',
    'Item Code (Aria)',
    'Jubelio Item ID',
    'Warehouse (Aria)',
    'Location (Jubelio)',
    'Location ID (Jubelio)',
    'Qty Aria',
    'Qty Jubelio',
    'Selisih',
];

$rows = [];
$bodyRows = $table->getElementsByTagName('tbody')->item(0)?->getElementsByTagName('tr') ?? new DOMNodeList();

foreach ($bodyRows as $tr) {
    if (! $tr instanceof DOMElement) {
        continue;
    }

    $cells = $tr->getElementsByTagName('td');
    if ($cells->length < 7) {
        continue;
    }

    $itemCell = $cells->item(0);
    $itemName = '';
    $itemCode = '';
    if ($itemCell instanceof DOMElement) {
        $bold = $itemCell->getElementsByTagName('span');
        if ($bold->length >= 2) {
            $itemName = trim($bold->item(0)?->textContent ?? '');
            $itemCode = trim($bold->item(1)?->textContent ?? '');
        } else {
            $itemName = trim($itemCell->textContent ?? '');
        }
    }

    $jubelioItemId = trim($cells->item(1)?->textContent ?? '');

    $warehouse = trim($cells->item(2)?->textContent ?? '');

    $locationCell = $cells->item(3);
    $locationName = '';
    $locationId = '';
    if ($locationCell instanceof DOMElement) {
        $spans = $locationCell->getElementsByTagName('span');
        if ($spans->length >= 2) {
            $locationName = trim($spans->item(0)?->textContent ?? '');
            $locationIdText = trim($spans->item(1)?->textContent ?? '');
            if (preg_match('/ID:\s*(-?\d+)/', $locationIdText, $m)) {
                $locationId = $m[1];
            }
        } else {
            $locationName = trim($locationCell->textContent ?? '');
        }
    }

    $qtyAria = (float) str_replace(',', '', trim($cells->item(4)?->textContent ?? '0'));
    $qtyJubelio = (float) str_replace(',', '', trim($cells->item(5)?->textContent ?? '0'));

    $selisihText = trim($cells->item(6)?->textContent ?? '');
    $selisih = (float) str_replace(',', '', $selisihText);

    $rows[] = [
        $itemName,
        $itemCode,
        $jubelioItemId,
        $warehouse,
        $locationName,
        $locationId,
        $qtyAria,
        $qtyJubelio,
        $selisih,
    ];
}

$spreadsheet = new Spreadsheet;
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Daftar Ketidakcocokan');
$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:I1')->getFont()->setBold(true);

if ($rows !== []) {
    $sheet->fromArray($rows, null, 'A2');
}

foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

(new Xlsx($spreadsheet))->save($outputPath);

echo "Wrote {$outputPath} with ".count($rows)." data rows\n";
