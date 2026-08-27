<?php

namespace App\Support;

use App\Models\Setting;

class PpnAmounts
{
    /**
     * Split a gross (tax-inclusive) amount into DPP and PPN at the configured rate.
     *
     * @return array{dpp: float, ppn: float}
     */
    public static function fromGross(float $gross, ?float $ratePercent = null): array
    {
        $gross = abs($gross);
        $rate = ($ratePercent ?? (float) Setting::getValue('ppn_rate', 11)) / 100;
        $dpp = round($gross / (1 + $rate), 2);
        $ppn = round($gross - $dpp, 2);

        return ['dpp' => $dpp, 'ppn' => $ppn];
    }
}
