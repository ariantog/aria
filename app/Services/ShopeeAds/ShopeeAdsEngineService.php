<?php

namespace App\Services\ShopeeAds;

use App\Enums\ShopeeAdsType;
use App\Models\ShopeeAdsBudgetHistory;
use App\Models\ShopeeAdsGroupState;
use App\Models\ShopeeAdsSchedule;
use App\Models\ShopeeAdsSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ShopeeAdsEngineService
{
    public function __construct(
        private readonly ShopeeAdsApiService $api,
    ) {}

    public function jakartaNow(): Carbon
    {
        return Carbon::now('Asia/Jakarta');
    }

    public function runDueSchedules(): int
    {
        $settings = ShopeeAdsSetting::current();

        if ($settings->isPaused()) {
            return 0;
        }

        if (! $this->api->hasShopAuthorization()) {
            Log::warning('Shopee Ads schedules skipped: shop not authorized');

            return 0;
        }

        $now = $this->jakartaNow();
        $currentSlot = $now->format('H:i');
        $ran = 0;

        $schedules = ShopeeAdsSchedule::query()
            ->where('enabled', true)
            ->where('run_time', $currentSlot)
            ->get();

        foreach ($schedules as $schedule) {
            if ($this->scheduleAlreadyRanToday($schedule, $now)) {
                continue;
            }

            if ($schedule->ad_type === ShopeeAdsType::Group->value) {
                $this->incrementGroupPool($settings, $schedule->increment_idr);
            } else {
                $this->incrementSingleAd($settings, $schedule->ad_type, $schedule->increment_idr);
            }

            $schedule->update(['last_run_at' => $now]);
            $ran++;
        }

        return $ran;
    }

    public function runDailyResetIfDue(): bool
    {
        $settings = ShopeeAdsSetting::current();
        $now = $this->jakartaNow();

        if (! $this->isDueAt($settings, $now, $settings->daily_reset_hour, $settings->daily_reset_minute, $settings->last_daily_reset_at)) {
            return false;
        }

        if (! $this->api->hasShopAuthorization()) {
            return false;
        }

        $this->dailyReset($settings);
        $settings->update(['last_daily_reset_at' => $now]);

        return true;
    }

    public function runReplenishIfDue(): bool
    {
        $settings = ShopeeAdsSetting::current();

        if (! $settings->group_replenish_enabled) {
            return false;
        }

        $now = $this->jakartaNow();

        if (! $this->isDueAt($settings, $now, $settings->group_replenish_hour, $settings->group_replenish_minute, $settings->last_replenish_at)) {
            return false;
        }

        if (! $this->api->hasShopAuthorization()) {
            return false;
        }

        $this->replenishGroups($settings);
        $settings->update(['last_replenish_at' => $now]);

        return true;
    }

    public function dailyReset(ShopeeAdsSetting $settings): void
    {
        foreach (ShopeeAdsType::singleAdTypes() as $adType) {
            $campaignId = $settings->campaignIdForType($adType);
            if (! $campaignId) {
                continue;
            }

            $before = $this->api->getCurrentBudget($adType, $campaignId);
            if ($before === null) {
                continue;
            }

            if ($this->api->setBudget($adType, $campaignId, $settings->starting_budget)) {
                $this->recordHistory($adType, $campaignId, 'daily_reset', $before, $settings->starting_budget, null, 'Daily reset to starting budget');
            }
        }

        $groups = $this->api->listActiveAdGroups();

        foreach ($groups as $group) {
            $campaignId = $group['campaign_id'];
            $before = $group['budget'];
            if ($this->api->setBudget('group', $campaignId, $settings->starting_budget)) {
                $this->recordHistory('group', $campaignId, 'daily_reset', $before, $settings->starting_budget, null, 'Daily reset + re-open group');
            }
        }

        ShopeeAdsGroupState::query()->update([
            'increments_today' => 0,
            'low_roas_streak' => 0,
            'turned_off' => false,
        ]);
    }

    public function replenishGroups(ShopeeAdsSetting $settings): array
    {
        $activeGroups = collect($this->api->listActiveAdGroups())
            ->reject(fn ($g) => ShopeeAdsGroupState::query()->where('campaign_id', $g['campaign_id'])->where('turned_off', true)->exists())
            ->values();

        $activeCount = $activeGroups->count();
        $target = $settings->group_target_active_count;

        if ($activeCount >= $target) {
            return ['created' => 0, 'suggested' => 0, 'message' => 'Active group count meets target'];
        }

        $needed = min($target - $activeCount, $settings->group_replenish_max_per_run);
        $candidates = $this->buildReplenishCandidates($settings);
        $created = 0;
        $suggested = 0;

        foreach ($candidates as $candidate) {
            if ($created + $suggested >= $needed) {
                break;
            }

            $campaignId = $this->api->createManualProductAd(
                $candidate['item_id'],
                $settings->starting_budget,
                (float) $settings->group_roas_target,
            );

            if ($campaignId) {
                $created++;
                $this->recordHistory('group', $campaignId, 'replenish_create', null, $settings->starting_budget, null, 'Created group for item '.$candidate['item_id']);
                continue;
            }

            $suggested++;
            $this->recordHistory('group', null, 'replenish_suggest', null, $settings->starting_budget, null, 'Suggest manual add item '.$candidate['item_id'].' budget '.$settings->starting_budget);
        }

        return [
            'created' => $created,
            'suggested' => $suggested,
            'message' => "Replenish: {$created} created, {$suggested} suggested",
        ];
    }

    public function incrementSingleAd(ShopeeAdsSetting $settings, string $adType, int $incrementIdr): bool
    {
        $campaignId = $settings->campaignIdForType($adType);

        if (! $campaignId) {
            Log::warning('Shopee Ads increment skipped: missing campaign_id', ['ad_type' => $adType]);

            return false;
        }

        $result = $this->api->addBudget(
            $adType,
            $campaignId,
            $incrementIdr,
            $settings->daily_max_budget,
        );

        if ($result === null) {
            return false;
        }

        $this->recordHistory(
            $adType,
            $campaignId,
            'increment',
            $result['before'],
            $result['after'],
            $result['applied_increment'],
            'Schedule increment',
        );

        return true;
    }

    public function incrementGroupPool(ShopeeAdsSetting $settings, int $poolIdr): void
    {
        $groups = collect($this->api->listActiveAdGroups())
            ->reject(function ($group) {
                return ShopeeAdsGroupState::query()
                    ->where('campaign_id', $group['campaign_id'])
                    ->where('turned_off', true)
                    ->exists();
            })
            ->values()
            ->all();

        if ($groups === []) {
            return;
        }

        $currentBudgets = collect($groups)->mapWithKeys(fn ($g) => [$g['campaign_id'] => $g['budget']])->all();

        $roasGroups = collect($groups)->map(fn ($g) => [
            'campaign_id' => $g['campaign_id'],
            'roas' => (float) $g['roas'],
        ])->all();

        $allocations = ShopeeAdsBudgetAllocator::splitPoolByRoas(
            $roasGroups,
            $poolIdr,
            $settings->group_split_high,
            $settings->group_split_mid,
            $settings->group_split_low,
            $settings->daily_max_budget,
            $currentBudgets,
        );

        foreach ($allocations as $campaignId => $increment) {
            if ($increment <= 0) {
                continue;
            }

            $result = $this->api->addBudget('group', $campaignId, $increment, $settings->daily_max_budget);

            if ($result === null) {
                continue;
            }

            $state = ShopeeAdsGroupState::query()->firstOrCreate(['campaign_id' => $campaignId]);
            $state->increment('increments_today');
            $group = collect($groups)->firstWhere('campaign_id', $campaignId);
            $roas = (float) ($group['roas'] ?? 0);
            $state->last_roas = $roas;

            if ($roas < $settings->group_roas_off_threshold) {
                $state->increment('low_roas_streak');
            } else {
                $state->low_roas_streak = 0;
            }

            if ($state->low_roas_streak >= $settings->group_off_after_increments) {
                if ($this->api->turnOffGroupCampaign($campaignId)) {
                    $state->turned_off = true;
                    $this->recordHistory('group', $campaignId, 'turn_off', $result['after'], 0, null, 'ROAS below threshold '.$settings->group_roas_off_threshold);
                }
            }

            $this->recordHistory(
                'group',
                $campaignId,
                'increment',
                $result['before'],
                $result['after'],
                $result['applied_increment'],
                'Group pool increment',
            );
        }
    }

    /**
     * @return list<array{item_id: int, source: string}>
     */
    private function buildReplenishCandidates(ShopeeAdsSetting $settings): array
    {
        $candidates = [];

        foreach ($this->api->getRecommendedItems() as $item) {
            $candidates[] = ['item_id' => $item['item_id'], 'source' => 'recommended:'.$item['tag']];
        }

        $recycled = ShopeeAdsGroupState::query()
            ->where('turned_off', true)
            ->where('last_roas', '>=', $settings->group_replenish_min_roas)
            ->get();

        foreach ($recycled as $state) {
            $info = $this->api->getProductLevelCampaignInfo($state->campaign_id);
            $itemId = (int) ($info['item_id'] ?? 0);
            if ($itemId > 0) {
                $candidates[] = ['item_id' => $itemId, 'source' => 'recycled'];
            }
        }

        $seen = [];

        return collect($candidates)
            ->filter(function ($candidate) use (&$seen) {
                if (isset($seen[$candidate['item_id']])) {
                    return false;
                }
                $seen[$candidate['item_id']] = true;

                return true;
            })
            ->values()
            ->all();
    }

    private function scheduleAlreadyRanToday(ShopeeAdsSchedule $schedule, Carbon $now): bool
    {
        if (! $schedule->last_run_at) {
            return false;
        }

        return $schedule->last_run_at->timezone('Asia/Jakarta')->isSameDay($now);
    }

    private function isDueAt(
        ShopeeAdsSetting $settings,
        Carbon $now,
        int $hour,
        int $minute,
        ?Carbon $lastRun,
    ): bool {
        if ($now->hour !== $hour || $now->minute !== $minute) {
            return false;
        }

        if (! $lastRun) {
            return true;
        }

        return ! $lastRun->timezone('Asia/Jakarta')->isSameDay($now);
    }

    private function recordHistory(
        ?string $adType,
        ?string $campaignId,
        string $action,
        ?int $before,
        ?int $after,
        ?int $increment,
        ?string $message,
    ): void {
        ShopeeAdsBudgetHistory::query()->create([
            'ad_type' => $adType,
            'campaign_id' => $campaignId,
            'action' => $action,
            'before_budget' => $before,
            'after_budget' => $after,
            'increment_idr' => $increment,
            'message' => $message,
            'created_at' => now(),
        ]);
    }
}
