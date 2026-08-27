<?php

namespace App\Support;

use App\Models\Setting;

class PpnAmounts
{
    /**
     * Split a gross (tax-inclusive) payment into DPP and PPN with no withholding.
     *
     * @return array{dpp: float, ppn: float, pph: float}
     */
    public static function fromGross(float $gross, ?float $ratePercent = null): array
    {
        return self::fromPayment($gross, withPph: false, ppnRatePercent: $ratePercent);
    }

    /**
     * Derive DPP, PPN, and optional PPh from the bank payment (Total).
     *
     * Without PPh: payment = DPP × (1 + PPN rate)
     * With PPh:    payment = DPP × (1 + PPN rate − PPh rate)
     *
     * @return array{dpp: float, ppn: float, pph: float}
     */
    public static function fromPayment(
        float $payment,
        bool $withPph = false,
        ?float $ppnRatePercent = null,
        ?float $pphRatePercent = null,
    ): array {
        $payment = abs($payment);
        $ppnRate = ($ppnRatePercent ?? (float) Setting::getValue('ppn_rate', 11)) / 100;
        $pphRate = $withPph
            ? (($pphRatePercent ?? (float) config('reporting.pph_withholding_rate', 10)) / 100)
            : 0.0;

        $divisor = 1 + $ppnRate - $pphRate;
        if ($divisor <= 0) {
            $divisor = 1 + $ppnRate;
            $pphRate = 0.0;
        }

        $dpp = round($payment / $divisor, 2);
        $ppn = round($dpp * $ppnRate, 2);
        $pph = $withPph ? round($dpp * $pphRate, 2) : 0.0;

        return ['dpp' => $dpp, 'ppn' => $ppn, 'pph' => $pph];
    }
}
