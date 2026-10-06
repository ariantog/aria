<?php

namespace App\Support;

use App\Models\TaxFakturImport;

class VarianceCashTaxAmounts
{
    /**
     * Resolve PPN masukan amounts for a faktur payment-variance adjust.
     *
     * @param  array{record_ppn?: bool, record_pph?: bool, ppn_dpp?: float|null, ppn?: float|null, pph?: float|null}  $input
     * @return array{ppn: float, ppn_dpp: float|null, pph: float|null}
     */
    public static function resolve(float $varianceAmount, array $input): array
    {
        $recordPpn = filter_var($input['record_ppn'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $recordPpn || $varianceAmount < 0.01) {
            return ['ppn' => 0.0, 'ppn_dpp' => null, 'pph' => null];
        }

        $recordPph = filter_var($input['record_pph'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $ppn = isset($input['ppn']) ? (float) $input['ppn'] : 0.0;
        $ppnDpp = isset($input['ppn_dpp']) ? (float) $input['ppn_dpp'] : null;
        $pph = isset($input['pph']) ? (float) $input['pph'] : 0.0;

        $hasManual = $ppn >= 0.01 && $ppnDpp !== null && $ppnDpp >= 0.01;
        if ($recordPph) {
            $hasManual = $hasManual && $pph >= 0.01;
        }

        if ($hasManual) {
            return [
                'ppn' => $ppn,
                'ppn_dpp' => $ppnDpp,
                'pph' => $recordPph ? $pph : null,
            ];
        }

        $amounts = PpnAmounts::fromPayment($varianceAmount, $recordPph);

        return [
            'ppn' => $amounts['ppn'],
            'ppn_dpp' => $amounts['dpp'],
            'pph' => $recordPph && $amounts['pph'] >= 0.01 ? $amounts['pph'] : null,
        ];
    }

    /**
     * @return array{record_ppn?: bool, record_pph?: bool, ppn_dpp?: float|null, ppn?: float|null, pph?: float|null}
     */
    public static function inputFromImport(TaxFakturImport $import): array
    {
        return [
            'record_ppn' => (bool) $import->variance_record_ppn,
            'record_pph' => (bool) $import->variance_record_pph,
            'ppn_dpp' => $import->variance_ppn_dpp !== null ? (float) $import->variance_ppn_dpp : null,
            'ppn' => $import->variance_ppn !== null ? (float) $import->variance_ppn : null,
            'pph' => $import->variance_pph !== null ? (float) $import->variance_pph : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function applyToImport(TaxFakturImport $import, array $data): void
    {
        if (array_key_exists('variance_record_ppn', $data)) {
            $import->variance_record_ppn = filter_var($data['variance_record_ppn'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('variance_record_pph', $data)) {
            $import->variance_record_pph = filter_var($data['variance_record_pph'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        if (! $import->variance_record_ppn) {
            $import->variance_record_pph = false;
            $import->variance_ppn_dpp = null;
            $import->variance_ppn = null;
            $import->variance_pph = null;

            return;
        }

        if (array_key_exists('variance_ppn_dpp', $data)) {
            $import->variance_ppn_dpp = self::nullableAmount($data['variance_ppn_dpp']);
        }
        if (array_key_exists('variance_ppn', $data)) {
            $import->variance_ppn = self::nullableAmount($data['variance_ppn']);
        }
        if (array_key_exists('variance_pph', $data)) {
            $import->variance_pph = $import->variance_record_pph
                ? self::nullableAmount($data['variance_pph'])
                : null;
        }
    }

    private static function nullableAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
