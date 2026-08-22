<?php

namespace App\Services\ShopeeAds;

/**
 * Pure budget math used by the increment engine.
 */
class ShopeeAdsBudgetAllocator
{
    /**
     * Add increment to the live budget (not starting budget) and clamp to cap.
     */
    public static function addToBudget(int $currentBudget, int $incrementIdr, int $dailyMaxBudget): int
    {
        $incrementIdr = max(0, $incrementIdr);

        return min($currentBudget + $incrementIdr, $dailyMaxBudget);
    }

    /**
     * Split a total pool across groups by ROAS tiers (high / mid / low).
     *
     * @param  list<array{campaign_id: string, roas: float}>  $groups
     * @return array<string, int> campaign_id => increment IDR
     */
    public static function splitPoolByRoas(
        array $groups,
        int $poolIdr,
        int $splitHigh,
        int $splitMid,
        int $splitLow,
        int $dailyMaxBudget,
        array $currentBudgets = [],
    ): array {
        if ($poolIdr <= 0 || $groups === []) {
            return [];
        }

        $sorted = collect($groups)
            ->sortByDesc('roas')
            ->values()
            ->all();

        $count = count($sorted);
        $tierSize = max(1, (int) ceil($count / 3));

        $high = array_slice($sorted, 0, $tierSize);
        $mid = array_slice($sorted, $tierSize, $tierSize);
        $low = array_slice($sorted, $tierSize * 2);

        if ($mid === [] && $low !== []) {
            $mid = array_slice($low, 0, (int) ceil(count($low) / 2));
            $low = array_slice($low, (int) ceil(count($low) / 2));
        }

        $weights = [
            'high' => $splitHigh,
            'mid' => $splitMid,
            'low' => $splitLow,
        ];

        $allocations = [];

        foreach (['high' => $high, 'mid' => $mid, 'low' => $low] as $tier => $tierGroups) {
            if ($tierGroups === []) {
                continue;
            }

            $tierPool = (int) floor($poolIdr * ($weights[$tier] / 100));
            $perGroup = (int) floor($tierPool / count($tierGroups));
            $remainder = $tierPool - ($perGroup * count($tierGroups));

            foreach ($tierGroups as $index => $group) {
                $increment = $perGroup + ($index === 0 ? $remainder : 0);
                $campaignId = $group['campaign_id'];
                $current = (int) ($currentBudgets[$campaignId] ?? 0);
                $allocations[$campaignId] = self::addToBudget($current, $increment, $dailyMaxBudget) - $current;
            }
        }

        return $allocations;
    }
}
