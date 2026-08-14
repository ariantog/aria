<?php

namespace App\Services\Jubelio;

/**
 * Resolves seller receivable (Estimasi Total Penghasilan) from Jubelio order payloads.
 *
 * Marketplace orders often expose customer payment (grand_total) separately from
 * seller income (real_total / sub_total minus platform fees).
 */
class JubelioSellerIncomeResolver
{
    /** @var list<string> */
    private const PROMO_FEE_KEYS = [
        'promotion_fee',
        'promo_fee',
        'biaya_promosi',
        'promosi_fee',
        'promotion_service_fee',
    ];

    /** @var list<string> */
    private const INCOME_KEYS = [
        'seller_income',
        'net_income',
        'estimated_income',
        'total_penghasilan',
        'income_total',
        'seller_total',
        'net_total',
    ];

    /** @var list<string> */
    private const DEDUCTION_KEYS = [
        'voucher_subsidi',
        'voucher_and_subsidi',
        'voucher_subsidy',
        'subsidi',
        'subsidy',
        'subsidi_amount',
        'subsidy_amount',
        'platform_fee',
        'platform_service_fee',
        'biaya_platform',
        'free_shipping_fee',
        'gratis_ongkir_fee',
        'gratis_ongkir_xtra_fee',
        'shipping_fee_subsidy',
        'shipping_subsidy',
        'ongkir_subsidi',
        'xtra_shipping_fee',
        'service_fee',
        'biaya_layanan',
    ];

  /**
     * Amount Aria should book on the channel customer (signed positive).
     */
    public function resolve(array $dataApi, float $itemsTotal): float
    {
        $subTotal = $this->numericValue($dataApi['sub_total'] ?? null);
        $grandTotal = $this->numericValue($dataApi['grand_total'] ?? null);
        $realTotal = $this->numericValue($dataApi['real_total'] ?? null);

        foreach (self::INCOME_KEYS as $key) {
            $income = $this->numericValue($dataApi[$key] ?? null);
            if ($income !== null && $income > 0) {
                return $income;
            }
        }

        $fromFeesWithPromo = $this->incomeFromSubTotalAndFees($dataApi, $subTotal, includePromotionFee: true);
        if ($fromFeesWithPromo !== null) {
            return $fromFeesWithPromo;
        }

        if ($realTotal !== null && $this->looksLikeSellerIncome($realTotal, $subTotal, $grandTotal, $itemsTotal)) {
            return $realTotal;
        }

        $fromFeesWithoutPromo = $this->incomeFromSubTotalAndFees($dataApi, $subTotal, includePromotionFee: false);
        if ($fromFeesWithoutPromo !== null) {
            return $fromFeesWithoutPromo;
        }

        if ($subTotal !== null && $grandTotal !== null && $subTotal > $grandTotal) {
            if (($realTotal === null || $realTotal >= $grandTotal) && $itemsTotal > $grandTotal) {
                return $subTotal - $grandTotal;
            }
        }

        if ($grandTotal !== null) {
            return $grandTotal;
        }

        if ($realTotal !== null) {
            return $realTotal;
        }

        return $itemsTotal;
    }

    private function looksLikeSellerIncome(
        float $realTotal,
        ?float $subTotal,
        ?float $grandTotal,
        float $itemsTotal,
    ): bool {
        if ($subTotal !== null && $grandTotal !== null && $subTotal > $grandTotal && $realTotal >= $grandTotal) {
            return false;
        }

        if ($grandTotal !== null && $realTotal < $grandTotal) {
            return true;
        }

        if ($subTotal !== null && $realTotal < $subTotal) {
            return true;
        }

        if ($itemsTotal > 0 && $realTotal < $itemsTotal) {
            return true;
        }

        return $grandTotal === null && $subTotal === null;
    }

    private function incomeFromSubTotalAndFees(
        array $dataApi,
        ?float $subTotal,
        bool $includePromotionFee,
    ): ?float {
        if ($subTotal === null || $subTotal <= 0) {
            return null;
        }

        $fees = $this->collectDeductionFees($dataApi, $includePromotionFee);
        if ($fees === []) {
            return null;
        }

        return $subTotal - array_sum($fees);
    }

    /**
     * @return list<float>
     */
    private function collectDeductionFees(array $dataApi, bool $includePromotionFee): array
    {
        $fees = [];
        $keys = array_merge(
            array_diff(self::DEDUCTION_KEYS, self::PROMO_FEE_KEYS),
            $includePromotionFee ? self::PROMO_FEE_KEYS : [],
        );

        foreach ($keys as $key) {
            $value = $this->numericValue($dataApi[$key] ?? null);
            if ($value !== null && $value > 0) {
                $fees[] = $value;
            }
        }

        foreach (['fees', 'marketplace_fees', 'seller_fees', 'additional_fees', 'fee_details'] as $nestedKey) {
            $nested = $dataApi[$nestedKey] ?? null;
            if (! is_array($nested)) {
                continue;
            }

            foreach ($nested as $feeKey => $feeValue) {
                if (! $this->isDeductionKey((string) $feeKey, $includePromotionFee)) {
                    continue;
                }

                $value = $this->numericValue($feeValue);
                if ($value !== null && $value > 0) {
                    $fees[] = $value;
                }
            }
        }

        return $fees;
    }

    private function isDeductionKey(string $key, bool $includePromotionFee): bool
    {
        $normalized = strtolower($key);

        if (! $includePromotionFee && $this->matchesAny($normalized, self::PROMO_FEE_KEYS)) {
            return false;
        }

        if ($this->matchesAny($normalized, array_merge(self::DEDUCTION_KEYS, self::PROMO_FEE_KEYS))) {
            return true;
        }

        return str_contains($normalized, 'subsidi')
            || str_contains($normalized, 'subsidy')
            || str_contains($normalized, 'voucher')
            || str_contains($normalized, 'fee');
    }

    /**
     * @param  list<string>  $candidates
     */
    private function matchesAny(string $key, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if ($key === strtolower($candidate)) {
                return true;
            }
        }

        return false;
    }

    private function numericValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
