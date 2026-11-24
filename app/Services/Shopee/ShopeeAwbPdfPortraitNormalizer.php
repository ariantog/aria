<?php

namespace App\Services\Shopee;

use App\Services\Shopee\Support\ShopeeAwbPortraitPdf;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Shopee SPX AWB PDFs often arrive landscape or on a wide canvas (label left, blank right).
 * Rewrites each page to portrait A6 (105×148 mm) for thermal printers without squashing barcodes.
 */
class ShopeeAwbPdfPortraitNormalizer
{
    public const A6_WIDTH_MM = 105.0;

    public const A6_HEIGHT_MM = 148.0;

    private const LANDSCAPE_A6_RATIO_MIN = 1.38;

    private const LANDSCAPE_A6_RATIO_MAX = 1.48;

    private const WIDE_CANVAS_RATIO_MIN = 1.55;

    private const SIZE_TOLERANCE_MM = 4.0;

    public function normalize(string $pdfBinary): string
    {
        if (! str_starts_with($pdfBinary, '%PDF')) {
            return $pdfBinary;
        }

        try {
            $pdf = new ShopeeAwbPortraitPdf('P', 'mm');
            $pdf->SetAutoPageBreak(false);

            $pageCount = $pdf->setSourceFile(StreamReader::createByString($pdfBinary));
            if ($pageCount < 1) {
                return $pdfBinary;
            }

            $needsRewrite = false;
            $plans = [];
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                $plan = $this->planTransform((float) $size['width'], (float) $size['height']);
                $plans[$pageNo] = ['templateId' => $templateId, 'size' => $size, 'plan' => $plan];
                if ($plan['mode'] !== 'portrait_already_a6') {
                    $needsRewrite = true;
                }
            }

            if (! $needsRewrite) {
                return $pdfBinary;
            }

            $pdf = new ShopeeAwbPortraitPdf('P', 'mm');
            $pdf->SetAutoPageBreak(false);
            $pdf->setSourceFile(StreamReader::createByString($pdfBinary));

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                $plan = $this->planTransform((float) $size['width'], (float) $size['height']);
                $this->renderPage($pdf, $templateId, $size, $plan);
            }

            return $pdf->Output('S');
        } catch (\Throwable $e) {
            Log::warning('Shopee AWB PDF portrait normalize skipped', [
                'message' => $e->getMessage(),
            ]);

            return $pdfBinary;
        }
    }

    /**
     * @return array{mode: string}
     */
    private function planTransform(float $widthMm, float $heightMm): array
    {
        if ($widthMm <= 0.0 || $heightMm <= 0.0) {
            return ['mode' => 'portrait_fit'];
        }

        if ($this->isPortraitA6($widthMm, $heightMm)) {
            return ['mode' => 'portrait_already_a6'];
        }

        if ($widthMm > $heightMm) {
            $ratio = $widthMm / $heightMm;
            if ($ratio >= self::LANDSCAPE_A6_RATIO_MIN && $ratio <= self::LANDSCAPE_A6_RATIO_MAX) {
                return ['mode' => 'rotate_landscape_a6'];
            }
            if ($ratio >= self::WIDE_CANVAS_RATIO_MIN) {
                return ['mode' => 'crop_left_to_portrait'];
            }

            return ['mode' => 'rotate_landscape_a6'];
        }

        return ['mode' => 'portrait_fit'];
    }

    /**
     * @param  array{width: float|int, height: float|int, orientation: string}  $size
     * @param  array{mode: string}  $plan
     */
    private function renderPage(ShopeeAwbPortraitPdf $pdf, string $templateId, array $size, array $plan): void
    {
        $pdf->AddPage('P', [self::A6_WIDTH_MM, self::A6_HEIGHT_MM]);

        $sourceW = (float) $size['width'];
        $sourceH = (float) $size['height'];

        switch ($plan['mode']) {
            case 'rotate_landscape_a6':
                $pdf->rotateTransform(90, self::A6_WIDTH_MM / 2, self::A6_HEIGHT_MM / 2);
                $pdf->useImportedPage(
                    $templateId,
                    (self::A6_WIDTH_MM - $sourceH) / 2,
                    (self::A6_HEIGHT_MM - $sourceW) / 2,
                    $sourceH,
                    $sourceW,
                );
                $pdf->endRotate();
                break;

            case 'crop_left_to_portrait':
                $targetHeight = self::A6_HEIGHT_MM;
                $scale = $targetHeight / $sourceH;
                $scaledWidth = $sourceW * $scale;
                $pdf->useImportedPage($templateId, 0, 0, $scaledWidth, $targetHeight);
                break;

            case 'portrait_already_a6':
            case 'portrait_fit':
            default:
                $pdf->useImportedPage(
                    $templateId,
                    0,
                    0,
                    self::A6_WIDTH_MM,
                    self::A6_HEIGHT_MM,
                );
                break;
        }
    }

    private function isPortraitA6(float $widthMm, float $heightMm): bool
    {
        if ($widthMm >= $heightMm) {
            return false;
        }

        return abs($widthMm - self::A6_WIDTH_MM) <= self::SIZE_TOLERANCE_MM
            && abs($heightMm - self::A6_HEIGHT_MM) <= self::SIZE_TOLERANCE_MM;
    }
}
