<?php

namespace App\Services\ShopeeAds;

use App\Models\ShopeeAdsSetting;
use Carbon\Carbon;

class ShopeeAdsSpecialRulesService
{
    public function jakartaNow(): Carbon
    {
        return Carbon::now('Asia/Jakarta');
    }

    public function isDoubleDate(Carbon $now): bool
    {
        return $now->day === $now->month;
    }

    public function isPayday(ShopeeAdsSetting $settings, Carbon $now): bool
    {
        return $now->day === (int) $settings->payday_day;
    }

    public function resolveForToday(ShopeeAdsSetting $settings, ?Carbon $now = null): ShopeeAdsBudgetMultipliers
    {
        $now = $now ?? $this->jakartaNow();
        $gmv = 1.0;
        $itemBudget = 1.0;
        $itemAdsCount = 1.0;

        if ($settings->double_date_enabled && $this->isDoubleDate($now)) {
            $gmv *= (float) $settings->double_date_gmv_multiplier;
            $itemBudget *= (float) $settings->double_date_item_budget_multiplier;
            $itemAdsCount *= (float) $settings->double_date_item_ads_multiplier;
        }

        if ($settings->payday_enabled && $this->isPayday($settings, $now)) {
            $gmv *= (float) $settings->payday_gmv_multiplier;
            $itemBudget *= (float) $settings->payday_item_multiplier;
        }

        return new ShopeeAdsBudgetMultipliers($gmv, $itemBudget, $itemAdsCount);
    }

    /**
     * @return array{double_date: bool, payday: bool, multipliers: ShopeeAdsBudgetMultipliers, labels: list<string>}
     */
    public function todayStatus(ShopeeAdsSetting $settings, ?Carbon $now = null): array
    {
        $now = $now ?? $this->jakartaNow();
        $multipliers = $this->resolveForToday($settings, $now);

        return [
            'double_date' => $settings->double_date_enabled && $this->isDoubleDate($now),
            'payday' => $settings->payday_enabled && $this->isPayday($settings, $now),
            'multipliers' => $multipliers,
            'labels' => $multipliers->activeRuleLabels(),
        ];
    }
}
