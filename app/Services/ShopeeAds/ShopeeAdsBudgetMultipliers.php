<?php

namespace App\Services\ShopeeAds;

readonly class ShopeeAdsBudgetMultipliers
{
    public function __construct(
        public float $gmv = 1.0,
        public float $itemBudget = 1.0,
        public float $itemAdsCount = 1.0,
    ) {}

    public function scaleAmount(int $amount, float $multiplier): int
    {
        if ($multiplier <= 0) {
            return $amount;
        }

        return (int) round($amount * $multiplier);
    }

    public function scaledGmvAmount(int $amount): int
    {
        return $this->scaleAmount($amount, $this->gmv);
    }

    public function scaledItemBudgetAmount(int $amount): int
    {
        return $this->scaleAmount($amount, $this->itemBudget);
    }

    public function scaledMaxItemAds(int $baseMax): int
    {
        return max(1, (int) ceil($baseMax * $this->itemAdsCount));
    }

    public function scaledReplenishPerRun(int $basePerRun): int
    {
        return max(1, (int) ceil($basePerRun * $this->itemAdsCount));
    }

    /**
     * @return list<string>
     */
    public function activeRuleLabels(): array
    {
        $labels = [];

        if ($this->gmv !== 1.0) {
            $labels[] = 'GMV ×'.$this->gmv;
        }

        if ($this->itemBudget !== 1.0) {
            $labels[] = 'Item budget ×'.$this->itemBudget;
        }

        if ($this->itemAdsCount !== 1.0) {
            $labels[] = 'Item ads count ×'.$this->itemAdsCount;
        }

        return $labels;
    }

    public function hasActiveRules(): bool
    {
        return $this->gmv !== 1.0 || $this->itemBudget !== 1.0 || $this->itemAdsCount !== 1.0;
    }
}
