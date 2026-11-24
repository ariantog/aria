<?php

namespace App\Services\Shopee\Support;

use setasign\Fpdi\Fpdi;

/**
 * FPDI helper for Shopee AWB portrait A6 output (rotation + page-edge clip).
 */
class ShopeeAwbPortraitPdf extends Fpdi
{
    protected float $angle = 0;

    public function rotateTransform(float $angle, float $x, float $y): void
    {
        if ($this->angle !== 0.0) {
            $this->_out('Q');
        }

        $this->angle = $angle;
        if ($angle === 0.0) {
            return;
        }

        $angleRad = $angle * M_PI / 180;
        $c = cos($angleRad);
        $s = sin($angleRad);
        $cx = $x * $this->k;
        $cy = ($this->h - $y) * $this->k;
        $this->_out(sprintf(
            'q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',
            $c,
            $s,
            -$s,
            $c,
            $cx,
            $cy,
            -$cx,
            -$cy,
        ));
    }

    public function endRotate(): void
    {
        if ($this->angle !== 0.0) {
            $this->_out('Q');
            $this->angle = 0.0;
        }
    }
}
