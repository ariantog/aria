<?php

namespace App\Services\Tax;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Smalot\PdfParser\Parser;
use Throwable;

class FakturPajakPdfParser
{
    public const FORMAT_MDS_OUTPUT_TAX_INVOICE = 'mds_output_tax_invoice';

    /** @var array<string, int> */
    private const INDONESIAN_MONTHS = [
        'januari' => 1,
        'februari' => 2,
        'maret' => 3,
        'april' => 4,
        'mei' => 5,
        'juni' => 6,
        'juli' => 7,
        'agustus' => 8,
        'september' => 9,
        'oktober' => 10,
        'november' => 11,
        'desember' => 12,
    ];

    public function __construct(
        private readonly Parser $pdfParser = new Parser(),
    ) {}

    public function parseFile(string $path): ParsedFakturPajak
    {
        if (! is_readable($path)) {
            throw new InvalidArgumentException("Faktur PDF is not readable: {$path}");
        }

        try {
            $pdf = $this->pdfParser->parseFile($path);
            $text = $pdf->getText();
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Could not read PDF: '.$e->getMessage(), previous: $e);
        }

        return $this->parseText($text);
    }

    public function parseText(string $text): ParsedFakturPajak
    {
        $normalized = $this->normalizeText($text);

        if (! Str::contains($normalized, 'Faktur Pajak', ignoreCase: true)) {
            throw new InvalidArgumentException('PDF does not appear to be a Faktur Pajak document.');
        }

        $fakturNumber = $this->requireMatch(
            $normalized,
            '/Kode dan Nomor Seri Faktur Pajak:\s*(\d{17})/i',
            'faktur number',
        );

        $sellerNpwp = $this->requireMatch(
            $normalized,
            '/Pengusaha Kena Pajak:.*?NPWP\s*:\s*(\d{15,16})/is',
            'seller NPWP',
        );

        $buyerNpwp = $this->requireMatch(
            $normalized,
            '/Pembeli Barang Kena Pajak\/Penerima Jasa Kena Pajak:.*?NPWP\s*:\s*(\d{15,16})/is',
            'buyer NPWP',
        );

        $sellerName = $this->cleanupPartyName($this->requireMatch(
            $normalized,
            '/Pengusaha Kena Pajak:\s*Nama\s*:\s*(.+?)\s+Alamat\s*:/is',
            'seller name',
        ));

        $buyerName = $this->cleanupPartyName($this->requireMatch(
            $normalized,
            '/Pembeli Barang Kena Pajak\/Penerima Jasa Kena Pajak:\s*Nama\s*:\s*(.+?)\s+Alamat\s*:/is',
            'buyer name',
        ));

        $grossTotal = $this->parseAmount($this->requireMatch(
            $normalized,
            '/Harga Jual \/ Penggantian \/ Uang Muka \/ Termin\s+([\d.,]+)/i',
            'gross total',
        ));

        $discountTotal = $this->parseAmount($this->matchOr(
            $normalized,
            '/Dikurangi Potongan Harga\s+([\d.,]+)/i',
            '0',
        ));

        $downPaymentTotal = $this->parseAmount($this->matchOr(
            $normalized,
            '/Dikurangi Uang Muka yang telah diterima\s+([\d.,]+)/i',
            '0',
        ));

        $dpp = $this->parseAmount($this->requireMatch(
            $normalized,
            '/Dasar Pengenaan Pajak\s+([\d.,]+)/i',
            'DPP',
        ));

        $ppn = $this->parseAmount($this->requireMatch(
            $normalized,
            '/Jumlah PPN \(Pajak Pertambahan Nilai\)\s+([\d.,]+)/i',
            'PPN',
        ));

        $ppnbm = $this->parseAmount($this->matchOr(
            $normalized,
            '/Jumlah PPnBM \(Pajak Penjualan atas Barang Mewah\)\s+([\d.,]+)/i',
            '0',
        ));

        [$fakturDate, $fakturDatePlace] = $this->parseSigningDate($normalized);
        $signatoryName = $this->parseSignatoryName($normalized);
        $lineItems = $this->parseLineItems($normalized);

        return new ParsedFakturPajak(
            fakturNumber: $fakturNumber,
            fakturDate: $fakturDate,
            fakturDatePlace: $fakturDatePlace,
            sellerName: $sellerName,
            sellerNpwp: $sellerNpwp,
            buyerName: $buyerName,
            buyerNpwp: $buyerNpwp,
            grossTotal: $grossTotal,
            discountTotal: $discountTotal,
            downPaymentTotal: $downPaymentTotal,
            dpp: $dpp,
            ppn: $ppn,
            ppnbm: $ppnbm,
            signatoryName: $signatoryName,
            sourceFormat: self::FORMAT_MDS_OUTPUT_TAX_INVOICE,
            lineItems: $lineItems,
        );
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;

        return trim($text);
    }

    private function requireMatch(string $text, string $pattern, string $label): string
    {
        $value = $this->matchOr($text, $pattern, null);
        if ($value === null) {
            throw new InvalidArgumentException("Could not parse {$label} from Faktur Pajak PDF.");
        }

        return trim($value);
    }

    private function matchOr(string $text, string $pattern, ?string $default): ?string
    {
        if (! preg_match($pattern, $text, $matches)) {
            return $default;
        }

        return $matches[1];
    }

    private function cleanupPartyName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }

    private function parseAmount(string $raw): float
    {
        $normalized = str_replace('.', '', trim($raw));
        $normalized = str_replace(',', '.', $normalized);

        return (float) $normalized;
    }

    /**
     * @return array{0: ?Carbon, 1: ?string}
     */
    private function parseSigningDate(string $text): array
    {
        if (! preg_match(
            '/([^\n]+),\s*(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})\s+Ditandatangani secara elektronik/is',
            $text,
            $matches,
        )) {
            return [null, null];
        }

        $place = trim($matches[1]);
        $day = (int) $matches[2];
        $monthName = strtolower($matches[3]);
        $year = (int) $matches[4];
        $month = self::INDONESIAN_MONTHS[$monthName] ?? null;

        if (! $month) {
            return [null, $place];
        }

        return [Carbon::createFromDate($year, $month, $day), $place];
    }

    private function parseSignatoryName(string $text): ?string
    {
        if (! preg_match(
            '/Ditandatangani secara elektronik\s+(.+?)\s+\(Referensi:/is',
            $text,
            $matches,
        )) {
            return null;
        }

        $name = trim($matches[1]);

        return $name !== '' ? $name : null;
    }

    /**
     * @return list<array{
     *     line_no: int,
     *     name: string,
     *     unit_price: float,
     *     quantity: float,
     *     total: float,
     * }>
     */
    private function parseLineItems(string $text): array
    {
        $pattern = '/(\d+)\s+000000\s+(.+?)\s+Rp\s+([\d.,]+)\s+x\s+([\d.,]+)\s+Piece\s+Potongan Harga = Rp [\d.,]+\s+PPnBM \([\d.,]+%\) = Rp [\d.,]+\s+([\d.,]+)/is';

        if (! preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $items = [];
        foreach ($matches as $match) {
            $items[] = [
                'line_no' => (int) $match[1],
                'name' => $this->cleanupPartyName($match[2]),
                'unit_price' => $this->parseAmount($match[3]),
                'quantity' => $this->parseAmount($match[4]),
                'total' => $this->parseAmount($match[5]),
            ];
        }

        return $items;
    }
}
