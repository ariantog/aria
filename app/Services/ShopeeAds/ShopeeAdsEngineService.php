<?php

namespace App\Services\ShopeeAds;

use App\Enums\ShopeeAdsType;
use App\Models\ShopeeAdsBudgetHistory;
use App\Models\ShopeeAdsItemAd;
use App\Models\ShopeeAdsItemPerformanceSnapshot;
use App\Models\ShopeeAdsSchedule;
use App\Models\ShopeeAdsSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Budget automation engine — aligned with bots/engine.py (GMV-Max + item ads only).
 */
class ShopeeAdsEngineService
{
    public function __construct(
        private readonly ShopeeAdsApiService $api,
        private readonly ShopeeAdsSpecialRulesService $specialRules,
        private readonly ShopeeAdsTelegramNotifier $telegram,
    ) {}

    public function jakartaNow(): Carbon
    {
        return Carbon::now($this->automationTimezone());
    }

    /**
     * Total daily starting pool for all item ad slots (scaled on special days).
     */
    public function itemAdsStartingPoolTotal(
        ShopeeAdsSetting $settings,
        ?ShopeeAdsBudgetMultipliers $multipliers = null,
        ?Carbon $now = null,
    ): int {
        $multipliers ??= $this->specialRules->resolveForToday($settings, $now);

        return max(0, $multipliers->scaledItemBudgetAmount((int) $settings->item_ad_starting_budget));
    }

    /**
     * Configured item ad slot count (max_item_ads, scaled on special days).
     */
    public function itemAdsSlotCount(
        ShopeeAdsSetting $settings,
        ?ShopeeAdsBudgetMultipliers $multipliers = null,
        ?Carbon $now = null,
    ): int {
        $multipliers ??= $this->specialRules->resolveForToday($settings, $now);

        return max(1, $multipliers->scaledMaxItemAds((int) $settings->max_item_ads));
    }

    /**
     * Per-slot item ad budget: item_ad_starting_budget ÷ max_item_ads (after scaling).
     */
    public function itemAdBudgetPerSlot(
        ShopeeAdsSetting $settings,
        ?ShopeeAdsBudgetMultipliers $multipliers = null,
        ?Carbon $now = null,
    ): int {
        $pool = $this->itemAdsStartingPoolTotal($settings, $multipliers, $now);
        $slots = $this->itemAdsSlotCount($settings, $multipliers, $now);

        if ($pool <= 0) {
            return ShopeeAdsApiService::ITEM_AD_MIN_BUDGET;
        }

        return max((int) floor($pool / $slots), ShopeeAdsApiService::ITEM_AD_MIN_BUDGET);
    }

    private function automationTimezone(): string
    {
        return (string) config('services.shopee_ads.timezone', 'Asia/Jakarta');
    }

    public function runDueSchedules(): int
    {
        $settings = ShopeeAdsSetting::current();

        if ($settings->isPaused()) {
            Log::info('Shopee Ads schedules skipped: automation not active', [
                'status' => $settings->status,
            ]);

            return 0;
        }

        if (! $this->api->hasShopAuthorization()) {
            Log::info('Shopee Ads schedules skipped: shop not authorized');

            return 0;
        }

        $now = $this->jakartaNow();
        $ran = 0;

        $schedules = ShopeeAdsSchedule::query()
            ->where('enabled', true)
            ->orderBy('run_time')
            ->get()
            ->filter(fn (ShopeeAdsSchedule $schedule) => $this->isScheduleDue($schedule, $now));

        foreach ($schedules as $schedule) {
            if (! in_array($schedule->ad_type, ShopeeAdsType::supportedScheduleTypes(), true)) {
                Log::warning('Shopee Ads schedule skipped (legacy / unsupported API)', ['ad_type' => $schedule->ad_type]);
                $schedule->update(['last_run_at' => $now]);

                continue;
            }

            if ($schedule->ad_type === ShopeeAdsType::GmvMax->value) {
                $multipliers = $this->specialRules->resolveForToday($settings, $now);
                $increment = $multipliers->scaledGmvAmount($schedule->increment_idr);
                $this->applyGmvMaxIncrement($settings, $increment, $schedule->run_time);
            } elseif ($schedule->ad_type === ShopeeAdsType::ProdukManual->value) {
                $multipliers = $this->specialRules->resolveForToday($settings, $now);
                $pool = $multipliers->scaledItemBudgetAmount($schedule->increment_idr);
                $deleted = $this->applyItemAdsIncrement($settings, $pool, $schedule->run_time);

                if ($settings->item_replenish_enabled) {
                    $this->replenishItemAds($settings->fresh(), fillToCap: false);
                } elseif ($deleted > 0 && $settings->item_auto_topup_enabled) {
                    $this->replenishItemAds($settings->fresh(), fillToCap: false);
                }
            }

            $schedule->update(['last_run_at' => $now]);
            $ran++;

            Log::info('Shopee Ads schedule ran', [
                'ad_type' => $schedule->ad_type,
                'run_time' => $schedule->run_time,
                'wib' => $now->format('Y-m-d H:i:s'),
            ]);
        }

        return $ran;
    }

    /**
     * Persist today's GMV-Max spend from Shopee so the dashboard can read DB only.
     */
    public function syncGmsCurrentSpend(?ShopeeAdsSetting $settings = null): bool
    {
        $settings ??= ShopeeAdsSetting::current();

        if (! $this->api->hasShopAuthorization()) {
            return false;
        }

        $camp = $this->api->getGmsCampaign();

        if ($camp === null) {
            Log::warning('GMV-Max spend sync skipped: no campaign performance from Shopee');

            return false;
        }

        $updates = [
            'gms_current_spend' => max(0, (int) round($camp['expense'])),
            'gms_current_spend_at' => now(),
        ];

        if (! $settings->gms_campaign_id && filled($camp['campaign_id'] ?? null)) {
            $updates['gms_campaign_id'] = (string) $camp['campaign_id'];
        }

        $settings->update($updates);

        Log::info('GMV-Max spend synced from Shopee', [
            'spend' => $updates['gms_current_spend'],
            'campaign_id' => $camp['campaign_id'],
        ]);

        return true;
    }

    /**
     * Human-readable reasons when shopee-ads:process does nothing (for CLI / ops).
     *
     * @return array{
     *     now_wib: string,
     *     now_utc: string,
     *     current_slot: string,
     *     automation_timezone: string,
     *     app_timezone: string,
     *     php_timezone: string,
     *     paused: bool,
     *     authorized: bool,
     *     automation_active: bool,
     *     settings_status: string,
     *     schedules: list<string>,
     *     daily_reset: list<string>,
     *     replenish: list<string>,
     * }
     */
    public function getRunDiagnostics(): array
    {
        $settings = ShopeeAdsSetting::current();
        $now = $this->jakartaNow();
        $currentSlot = $now->format('H:i');

        $scheduleNotes = [];
        if ($settings->isPaused()) {
            $scheduleNotes[] = sprintf(
                'Automasi tidak aktif (status DB: «%s»). Resume di /shopee-ads — legacy Python memakai Running, Laravel active.',
                $settings->status,
            );
        }

        if (! $this->api->hasShopAuthorization()) {
            $scheduleNotes[] = 'Shop tidak terotorisasi — Authorize Shopee di /shopee-ads.';
        }

        $enabledSchedules = ShopeeAdsSchedule::query()
            ->where('enabled', true)
            ->orderBy('run_time')
            ->get();

        $dueNow = $enabledSchedules->filter(fn (ShopeeAdsSchedule $schedule) => $this->isScheduleDue($schedule, $now));

        if ($dueNow->isNotEmpty() && $scheduleNotes === []) {
            foreach ($dueNow as $schedule) {
                $scheduleNotes[] = "Due now: {$schedule->ad_type} @ {$schedule->run_time} (akan jalan pada tick cron ini).";
            }
        }

        if ($dueNow->isEmpty() && $scheduleNotes === []) {
            if ($enabledSchedules->isEmpty()) {
                $scheduleNotes[] = 'Tidak ada jadwal increment — tambahkan di /shopee-ads.';
            } else {
                $slots = $enabledSchedules->map(fn ($s) => $s->run_time.' ('.$s->ad_type.')')->unique()->values()->all();
                $scheduleNotes[] = "Belum ada jadwal due di {$currentSlot} WIB (increment jalan setelah HH:MM, catch-up sampai midnight).";
                $scheduleNotes[] = 'Jadwal aktif: '.implode(', ', $slots);
            }
        }

        $disabledSchedules = ShopeeAdsSchedule::query()->where('enabled', false)->count();
        if ($disabledSchedules > 0) {
            $scheduleNotes[] = "{$disabledSchedules} jadwal disabled (enabled=0) — tidak akan jalan.";
        }

        $dailyResetNotes = $this->timedJobNotes(
            $now,
            (int) $settings->daily_reset_hour,
            (int) $settings->daily_reset_minute,
            $settings->last_daily_reset_at,
            'daily reset',
        );

        if (! $this->api->hasShopAuthorization()) {
            $dailyResetNotes[] = 'Shop tidak terotorisasi.';
        }

        $replenishNotes = [];
        if (! $settings->item_replenish_enabled) {
            $replenishNotes[] = 'Item replenish disabled di pengaturan.';
        } elseif (! $settings->item_ads_enabled) {
            $replenishNotes[] = 'Item ads subsystem disabled.';
        } else {
            $replenishNotes[] = 'Create: saat daily reset (isi penuh sampai max '.$settings->max_item_ads.') + setelah jadwal increment produk_manual jika active < max (max '.$settings->item_replenish_max_per_run.' per jadwal).';
        }

        if (! $this->api->hasShopAuthorization()) {
            $replenishNotes[] = 'Shop tidak terotorisasi.';
        }

        return [
            'now_wib' => $now->format('Y-m-d H:i:s'),
            'now_utc' => Carbon::now('UTC')->format('Y-m-d H:i:s'),
            'current_slot' => $currentSlot,
            'automation_timezone' => $this->automationTimezone(),
            'app_timezone' => (string) config('app.timezone'),
            'php_timezone' => date_default_timezone_get(),
            'paused' => $settings->isPaused(),
            'authorized' => $this->api->hasShopAuthorization(),
            'automation_active' => $settings->automationStatus()->isActive(),
            'settings_status' => (string) $settings->status,
            'schedules' => $scheduleNotes,
            'daily_reset' => $dailyResetNotes,
            'replenish' => $replenishNotes,
        ];
    }

    /**
     * @return list<string>
     */
    private function timedJobNotes(Carbon $now, int $hour, int $minute, CarbonInterface|null $lastRun, string $label): array
    {
        $slot = sprintf('%02d:%02d', $hour, $minute);
        $notes = [];
        $scheduledAt = $now->copy()->startOfDay()->setTime($hour, $minute, 0);

        if ($lastRun && $lastRun->timezone($this->automationTimezone())->isSameDay($now)) {
            $notes[] = "{$label} sudah jalan hari ini ({$lastRun->timezone($this->automationTimezone())->format('H:i')} WIB).";
        } elseif ($now->lt($scheduledAt)) {
            $notes[] = "{$label} di {$slot} WIB (sekarang {$now->format('H:i')}, belum waktunya).";
        } elseif ($now->gt($scheduledAt)) {
            $notes[] = "{$label} due (jadwal {$slot} WIB, catch-up sampai reset tercatat hari ini).";
        }

        return $notes;
    }

    public function runDailyResetIfDue(): bool
    {
        $settings = ShopeeAdsSetting::current();
        $now = $this->jakartaNow();

        if (! $this->isTimedJobDue($now, (int) $settings->daily_reset_hour, (int) $settings->daily_reset_minute, $settings->last_daily_reset_at)) {
            return false;
        }

        if (! $this->api->hasShopAuthorization()) {
            return false;
        }

        Log::info('Shopee Ads daily reset starting', [
            'wib' => $now->format('Y-m-d H:i:s'),
            'last_daily_reset_at' => $settings->last_daily_reset_at?->toIso8601String(),
        ]);

        $this->dailyReset($settings);
        $settings->update(['last_daily_reset_at' => $now]);

        if ($settings->item_ads_enabled && ($settings->item_replenish_enabled || $settings->item_auto_topup_enabled)) {
            $this->replenishItemAds($settings->fresh(), fillToCap: true);
        }

        Log::info('Shopee Ads daily reset finished');

        return true;
    }

    public function runItemReplenishIfDue(): bool
    {
        // Legacy standalone item-replenish clock — superseded by reset-time fill + post-increment top-up.
        return false;
    }

    public function applyGmvMaxIncrement(ShopeeAdsSetting $settings, int $incrementIdr, ?string $runTime = null): bool
    {
        $campaignId = $this->discoverGmsCampaignId($settings);

        if (! $campaignId) {
            Log::error('GMV-Max increment skipped: no active campaign');

            return false;
        }

        $liveBudget = $this->api->getGmsLiveBudget($campaignId);
        $performance = $this->api->getGmsCampaign();
        $spendToday = $performance !== null
            ? max(0, (int) round($performance['expense']))
            : max(0, (int) $settings->gms_current_spend);
        $tracked = (int) $settings->gms_current_budget;

        $base = max(
            ($liveBudget !== null && $liveBudget > 0) ? $liveBudget : 0,
            $spendToday,
            $tracked,
        );

        if ($base <= 0) {
            Log::error('GMV-Max increment skipped: could not resolve a budget base', [
                'campaign_id' => $campaignId,
                'live_budget' => $liveBudget,
                'spend_today' => $spendToday,
                'tracked' => $tracked,
            ]);

            return false;
        }

        Log::info('GMV-Max increment base resolved', [
            'campaign_id' => $campaignId,
            'base' => $base,
            'live_budget' => $liveBudget,
            'spend_today' => $spendToday,
            'tracked' => $tracked,
        ]);

        $updates = [];
        if ($base !== $tracked) {
            $updates['gms_current_budget'] = $base;
        }
        if ($performance !== null && $spendToday !== (int) $settings->gms_current_spend) {
            $updates['gms_current_spend'] = $spendToday;
            $updates['gms_current_spend_at'] = now();
        }
        if ($updates !== []) {
            $settings->update($updates);
            $settings->refresh();
        }

        $tracked = (int) $settings->gms_current_budget;
        $maxGmvBudget = $tracked + $this->combinedHeadroom($settings);

        $result = $this->api->addGmsBudget($campaignId, $tracked, $incrementIdr, $maxGmvBudget, $base);

        if ($result === null) {
            return false;
        }

        if ($result['applied_increment'] > 0) {
            $settings->update([
                'gms_campaign_id' => $campaignId,
                'gms_current_budget' => $result['after'],
            ]);
        }

        $this->recordHistory(
            ShopeeAdsType::GmvMax->value,
            $campaignId,
            'increment',
            $result['before'],
            $result['after'],
            $result['applied_increment'],
            'GMV-Max schedule increment (max live budget, spend today, tracked + increment)',
        );

        if ($runTime !== null && $result['applied_increment'] > 0) {
            $this->telegram->notifyGmvIncrement($runTime, $result['before'], $result['after']);
        }

        return true;
    }

    /**
     * @return int Number of ads deleted from Shopee + DB during this increment run.
     */
    public function applyItemAdsIncrement(ShopeeAdsSetting $settings, int $poolIdr, ?string $runTime = null): int
    {
        if (! $settings->item_ads_enabled) {
            return 0;
        }

        $liveByCampaign = $this->syncItemAds()['live_by_campaign'];

        $ads = $this->activeItemAdModels($liveByCampaign);

        if ($ads->isEmpty()) {
            return 0;
        }

        foreach ($ads as $ad) {
            $live = $liveByCampaign->get($ad->campaign_id);
            if ($live && (float) $live['budget'] > 0) {
                $liveBudget = (int) round((float) $live['budget']);
                if (abs($liveBudget - (int) $ad->budget) > 0) {
                    $ad->update(['budget' => $liveBudget]);
                }
            }
        }

        $campaignIds = $ads->pluck('campaign_id')->map(fn ($id) => (int) $id)->all();
        $performance = $this->api->getItemAdsDailyPerformance($campaignIds);

        $roasGroups = $ads->map(fn ($ad) => [
            'campaign_id' => $ad->campaign_id,
            'roas' => (float) ($performance[$ad->campaign_id]['roas'] ?? $ad->last_roas ?? 0),
            'turned_off' => false,
        ])->all();

        $currentBudgets = $ads->mapWithKeys(fn ($ad) => [$ad->campaign_id => (int) $ad->budget])->all();
        $headroom = $this->combinedHeadroom($settings);

        $effectivePool = min($poolIdr, $headroom);
        $allocations = ShopeeAdsBudgetAllocator::splitPoolByRoas(
            $roasGroups,
            $effectivePool,
            (int) $settings->item_split_high,
            (int) $settings->item_split_mid,
            (int) $settings->item_split_low,
            $this->combinedDailyCap($settings),
            $currentBudgets,
        );

        $runningHeadroom = $headroom;
        $notifyLines = [];
        $deleted = 0;

        foreach ($ads as $ad) {
            $cid = $ad->campaign_id;
            $roas = (float) ($performance[$cid]['roas'] ?? $ad->last_roas ?? 0);
            $streak = (int) $ad->low_roas_streak;
            $streak = $roas < (float) $settings->item_roas_off_threshold ? $streak + 1 : 0;

            if ($streak >= (int) $settings->item_off_after_checks) {
                if ($this->removeItemAd($ad, $roas, $streak, 'Item ad deleted (low ROAS streak)')) {
                    $deleted++;
                }

                continue;
            }

            $increment = (int) ($allocations[$cid] ?? 0);
            if ($increment <= 0) {
                $ad->update(['low_roas_streak' => $streak, 'last_roas' => $roas]);

                continue;
            }

            $perAdCap = (int) $ad->budget + $runningHeadroom;
            $result = $this->api->addItemAdBudget($cid, $increment, $perAdCap);

            if ($result === null) {
                continue;
            }

            $runningHeadroom = max(0, $runningHeadroom - $result['applied_increment']);
            $ad->update([
                'budget' => $result['after'],
                'increments_today' => $ad->increments_today + 1,
                'low_roas_streak' => $streak,
                'last_roas' => $roas,
            ]);

            $this->recordHistory(
                ShopeeAdsType::ProdukManual->value,
                $cid,
                'increment',
                $result['before'],
                $result['after'],
                $result['applied_increment'],
                'Item pool increment',
            );

            if ($runTime !== null && $result['applied_increment'] > 0) {
                $notifyLines[] = sprintf(
                    '• item %d: Rp %s → Rp %s (ROAS %.2f)',
                    $ad->item_id,
                    number_format($result['before'], 0, ',', '.'),
                    number_format($result['after'], 0, ',', '.'),
                    $roas,
                );
            }
        }

        if ($runTime !== null && $notifyLines !== []) {
            $this->telegram->notifyItemIncrement($runTime, $notifyLines);
        }

        return $deleted;
    }

    /**
     * Reconcile tracked item ads with Shopee live manual product campaigns (bots/engine.py sync_item_ads).
     *
     * @return array{imported: int, updated: int, closed: int, active: int, live_by_campaign: Collection}
     */
    public function syncItemAds(): array
    {
        $stats = ['imported' => 0, 'updated' => 0, 'closed' => 0, 'active' => 0];

        $live = $this->api->listManualProductAds(true);
        $liveByCampaign = collect($live)->keyBy('campaign_id');
        $tracked = ShopeeAdsItemAd::query()->get()->keyBy('campaign_id');

        if ($live === [] && $tracked->isEmpty()) {
            Log::info('Shopee Ads item sync: no active manual product campaigns returned from API');
        }

        foreach ($liveByCampaign as $campaignId => $row) {
            $existing = $tracked->get($campaignId);

            if ($existing === null) {
                ShopeeAdsItemAd::query()->create([
                    'campaign_id' => $campaignId,
                    'item_id' => (int) $row['item_id'],
                    'origin' => 'manual',
                    'budget' => (int) round((float) $row['budget']),
                    'status' => $row['status'] ?: 'ongoing',
                    'turned_off' => false,
                ]);
                $stats['imported']++;
            } else {
                $existing->update([
                    'item_id' => (int) $row['item_id'],
                    'budget' => (int) round((float) $row['budget']),
                    'status' => $row['status'] ?: 'ongoing',
                    'turned_off' => false,
                ]);
                $stats['updated']++;
            }

            $stats['active']++;
        }

        foreach ($tracked as $campaignId => $ad) {
            if (! $liveByCampaign->has($campaignId)) {
                if (strtolower((string) $ad->status) !== 'closed') {
                    $ad->update(['status' => 'closed', 'turned_off' => false]);
                    $stats['closed']++;
                }
            }
        }

        $stats['live_by_campaign'] = $liveByCampaign;

        Log::info('Shopee Ads item sync done', collect($stats)->except('live_by_campaign')->all());

        return $stats;
    }

    /**
     * @param  bool  $fillToCap  When true (daily reset), create up to max_item_ads ignoring max_per_run.
     * @return array{created: int, message: string}
     */
    public function replenishItemAds(ShopeeAdsSetting $settings, bool $fillToCap = false): array
    {
        $multipliers = $this->specialRules->resolveForToday($settings);
        $starting = $this->itemAdBudgetPerSlot($settings, $multipliers);

        if (! $settings->item_ads_enabled || ! $settings->item_replenish_enabled) {
            $message = 'Item ads or auto-replenish disabled';
            $this->telegram->notifyReplenish(0, $starting, $message);

            return ['created' => 0, 'message' => $message];
        }

        if (! $this->api->hasShopAuthorization()) {
            return ['created' => 0, 'message' => 'Shop not authorized'];
        }

        $sync = $this->syncItemAds();
        $liveByCampaign = $sync['live_by_campaign'];

        $active = $liveByCampaign->count();
        $cap = $multipliers->scaledMaxItemAds((int) $settings->max_item_ads);
        $need = $cap - $active;

        if (! $fillToCap) {
            $need = min($need, $multipliers->scaledReplenishPerRun((int) $settings->item_replenish_max_per_run));
        }

        if ($need <= 0) {
            $message = $active > $cap
                ? "Active item ads exceed cap ({$active} live > {$cap} max) — run daily reset to trim"
                : 'Active item ads already at cap';
            $this->telegram->notifyReplenish(0, $starting, $message);

            return ['created' => 0, 'message' => $message];
        }

        $headroom = $this->combinedHeadroom($settings);
        $affordable = $starting > 0 ? (int) floor($headroom / $starting) : 0;
        $need = min($need, $affordable);

        if ($need <= 0) {
            $message = 'No combined budget headroom for new item ads';
            $this->telegram->notifyReplenish(0, $starting, $message);

            return ['created' => 0, 'message' => $message];
        }

        $exclude = $liveByCampaign->pluck('item_id')->map(fn ($id) => (int) $id)->all();
        $candidates = collect($this->rankItemAdCandidates($settings, $exclude))
            ->take($need);

        $created = 0;

        foreach ($candidates as $candidate) {
            $cid = $this->api->createManualProductAd(
                $candidate['item_id'],
                $starting,
                (float) $settings->item_new_roas_target,
            );

            if (! $cid) {
                continue;
            }

            ShopeeAdsItemAd::query()->create([
                'campaign_id' => $cid,
                'item_id' => $candidate['item_id'],
                'origin' => 'bot',
                'budget' => $starting,
                'roas_target' => (float) $settings->item_new_roas_target,
                'status' => 'ongoing',
            ]);

            $created++;
            $label = $fillToCap ? 'Daily reset fill' : 'Increment schedule top-up';
            $this->recordHistory(
                ShopeeAdsType::ProdukManual->value,
                $cid,
                'replenish_create',
                null,
                $starting,
                null,
                $label.' — created item ad for '.$candidate['item_id'],
            );
        }

        $message = "Replenish: {$created} item ad(s) created";
        $this->telegram->notifyReplenish($created, $starting, $message);

        Log::info('Shopee Ads item replenish finished', [
            'created' => $created,
            'fill_to_cap' => $fillToCap,
            'active_before' => $active,
            'cap' => $cap,
        ]);

        return ['created' => $created, 'message' => $message];
    }

    /**
     * @return array{gmv: bool, items: int, message: string}
     */
    public function applyManualBudgetBoost(ShopeeAdsSetting $settings): array
    {
        $multiplier = max(1.0, (float) $settings->manual_boost_multiplier);
        $gmvApplied = false;
        $itemsApplied = 0;

        $campaignId = $this->discoverGmsCampaignId($settings);
        if ($campaignId) {
            $before = (int) $settings->gms_current_budget;
            $target = (int) round($before * $multiplier);
            $maxGmv = max($this->combinedDailyCap($settings) - $this->activeItemAdsBudgetTotal(), 0);
            $after = min($target, $maxGmv);

            if ($after > $before && $this->api->setGmsBudget($campaignId, $after)) {
                $settings->update(['gms_current_budget' => $after, 'gms_campaign_id' => $campaignId]);
                $this->recordHistory(
                    ShopeeAdsType::GmvMax->value,
                    $campaignId,
                    'manual_boost',
                    $before,
                    $after,
                    $after - $before,
                    'Manual budget boost ×'.$multiplier,
                );
                $gmvApplied = true;
            }
        }

        $this->syncItemAds();
        $ads = $this->activeItemAdModels();

        $liveByCampaign = collect($this->api->listManualProductAds(true))
            ->keyBy('campaign_id');

        $runningHeadroom = $this->combinedHeadroom($settings);

        foreach ($ads as $ad) {
            $live = $liveByCampaign->get($ad->campaign_id);
            $current = $live && (float) $live['budget'] > 0
                ? (int) round((float) $live['budget'])
                : (int) $ad->budget;
            $target = (int) round($current * $multiplier);
            $perAdCap = $current + $runningHeadroom;
            $after = min($target, $perAdCap);

            if ($after <= $current) {
                continue;
            }

            if (! $this->api->setItemAdBudget($ad->campaign_id, $after)) {
                continue;
            }

            $applied = $after - $current;
            $runningHeadroom = max(0, $runningHeadroom - $applied);
            $ad->update(['budget' => $after]);
            $itemsApplied++;
            $this->recordHistory(
                ShopeeAdsType::ProdukManual->value,
                $ad->campaign_id,
                'manual_boost',
                $current,
                $after,
                $applied,
                'Manual budget boost ×'.$multiplier,
            );
        }

        $message = sprintf(
            'Manual boost ×%s: GMV %s, %d item ad(s) updated',
            $multiplier,
            $gmvApplied ? 'updated' : 'skipped',
            $itemsApplied,
        );

        $this->telegram->notifyManualBoost($multiplier, $gmvApplied, $itemsApplied);

        return [
            'gmv' => $gmvApplied,
            'items' => $itemsApplied,
            'message' => $message,
        ];
    }

    private function activeItemAdsBudgetTotal(): int
    {
        return (int) ShopeeAdsItemAd::query()
            ->where('turned_off', false)
            ->whereNotIn('status', ['ended', 'closed', 'berakhir'])
            ->sum('budget');
    }

    public function dailyReset(ShopeeAdsSetting $settings): void
    {
        $settings = $settings->fresh() ?? $settings;

        $liveByCampaign = collect();
        if ($this->api->hasShopAuthorization()) {
            $sync = $this->syncItemAds();
            $liveByCampaign = $sync['live_by_campaign'];
        }

        $multipliers = $this->specialRules->resolveForToday($settings);
        $gmvStart = (int) ($settings->starting_budget_gmv_max ?: $settings->starting_budget);
        $gmvStart = $multipliers->scaledGmvAmount($gmvStart);
        $itemStart = $this->itemAdBudgetPerSlot($settings, $multipliers);

        $gmvReset = false;
        $itemResetCount = 0;

        $campaignId = $this->discoverGmsCampaignId($settings);
        if ($campaignId) {
            $before = (int) $settings->gms_current_budget;
            if ($this->api->setGmsBudget($campaignId, $gmvStart)) {
                $settings->update(['gms_current_budget' => $gmvStart, 'gms_campaign_id' => $campaignId]);
                $this->recordHistory(ShopeeAdsType::GmvMax->value, $campaignId, 'daily_reset', $before, $gmvStart, null, 'Daily reset GMV-Max');
                $gmvReset = true;
            }
        }

        if ($settings->item_ads_enabled && $liveByCampaign->isNotEmpty()) {
            $ads = $this->activeItemAdModels($liveByCampaign);
            $campaignIds = $ads->pluck('campaign_id')->map(fn ($id) => (int) $id)->all();
            $performance = $this->api->getItemAdsDailyPerformance($campaignIds);

            $this->purgeUnderperformingItemAdsAtReset($settings, $ads, $performance);
            $liveByCampaign = $this->liveManualAdsByCampaign();
            $this->enforceItemAdCap($settings, $liveByCampaign, $performance);
            $liveByCampaign = $this->liveManualAdsByCampaign();
            $ads = $this->activeItemAdModels($liveByCampaign);
        } elseif ($settings->item_ads_enabled && ! $this->api->hasShopAuthorization()) {
            $ads = ShopeeAdsItemAd::query()
                ->where('turned_off', false)
                ->whereNotIn('status', ['ended', 'closed', 'berakhir'])
                ->get();
        } else {
            $ads = collect();
        }

        foreach ($ads as $ad) {
            $before = (int) $ad->budget;
            if ($this->api->setItemAdBudget($ad->campaign_id, $itemStart)) {
                $ad->update([
                    'budget' => $itemStart,
                    'status' => 'ongoing',
                    'increments_today' => 0,
                    'low_roas_streak' => 0,
                    'turned_off' => false,
                ]);
                $this->recordHistory(ShopeeAdsType::ProdukManual->value, $ad->campaign_id, 'daily_reset', $before, $itemStart, null, 'Daily reset item ad');
                $itemResetCount++;
            }
        }

        if ($settings->item_ads_enabled && $this->api->hasShopAuthorization()) {
            $ads = $this->activeItemAdModels($this->liveManualAdsByCampaign());
            if ($ads->isNotEmpty()) {
                $performance = $this->api->getItemAdsDailyPerformance(
                    $ads->pluck('campaign_id')->map(fn ($id) => (int) $id)->all(),
                );
                $this->snapshotItemAdPerformance($ads, $performance);
            }
        }

        $this->purgeStaleItemAdRows($liveByCampaign);

        $this->telegram->notifyDailyReset(
            $gmvReset ? $gmvStart : 0,
            $itemResetCount,
            $itemStart,
        );
    }

    private function discoverGmsCampaignId(ShopeeAdsSetting $settings): ?string
    {
        if ($settings->gms_campaign_id) {
            return (string) $settings->gms_campaign_id;
        }

        $camp = $this->api->getGmsCampaign();

        if (! $camp) {
            return null;
        }

        $settings->update(['gms_campaign_id' => $camp['campaign_id']]);

        return $camp['campaign_id'];
    }

    public function combinedDailyCap(ShopeeAdsSetting $settings): int
    {
        $multipliers = $this->specialRules->resolveForToday($settings);

        return $multipliers->scaledCombinedDailyCap((int) $settings->daily_max_budget);
    }

    public function combinedHeadroom(ShopeeAdsSetting $settings): int
    {
        $total = (int) $settings->gms_current_budget;

        $itemTotal = $this->activeItemAdsBudgetTotal();

        $total += (int) $itemTotal;

        return max($this->combinedDailyCap($settings) - $total, 0);
    }

    private function scheduleAlreadyRanToday(ShopeeAdsSchedule $schedule, Carbon $now): bool
    {
        if (! $schedule->last_run_at) {
            return false;
        }

        return $schedule->last_run_at->timezone($this->automationTimezone())->isSameDay($now);
    }

    private function isScheduleDue(ShopeeAdsSchedule $schedule, Carbon $now): bool
    {
        if ($this->scheduleAlreadyRanToday($schedule, $now)) {
            return false;
        }

        $runTime = $this->normalizeRunTime($schedule->run_time);
        if ($runTime === null) {
            return false;
        }

        $scheduledAt = $this->scheduledAtToday($runTime, $now);

        // Catch up any time after HH:MM WIB same day (cron runs every minute).
        return $now->greaterThanOrEqualTo($scheduledAt);
    }

    private function normalizeRunTime(string $runTime): ?string
    {
        $raw = trim($runTime);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $raw, $matches) !== 1) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function scheduledAtToday(string $runTime, Carbon $now): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $runTime));

        return $now->copy()->startOfDay()->setTime($hour, $minute, 0);
    }

    /**
     * Daily reset / replenish: run once per WIB day, any time after HH:MM (catch-up if cron missed the slot).
     */
    private function isTimedJobDue(Carbon $now, int $hour, int $minute, CarbonInterface|null $lastRun): bool
    {
        if ($lastRun && $lastRun->timezone($this->automationTimezone())->isSameDay($now)) {
            return false;
        }

        $scheduledAt = $now->copy()->startOfDay()->setTime($hour, $minute, 0);

        return $now->greaterThanOrEqualTo($scheduledAt);
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

    /**
     * Suggest group ad candidates from performance history + Shopee recommendations.
     * Does not call Shopee create API (group ads may be gated per shop).
     *
     * @return list<array{item_id: int, source: string, avg_roas: float, reason: string}>
     */
    public function suggestGroupAds(ShopeeAdsSetting $settings, int $limit = 10): array
    {
        $liveItemIds = $this->liveManualAdsByCampaign()->pluck('item_id')->map(fn ($id) => (int) $id)->all();

        return collect($this->rankItemAdCandidates($settings, $liveItemIds))
            ->take($limit)
            ->map(fn (array $row) => [
                'item_id' => (int) $row['item_id'],
                'source' => (string) ($row['source'] ?? 'recommended'),
                'avg_roas' => (float) ($row['avg_roas'] ?? 0),
                'reason' => (string) ($row['reason'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * Live manual product ads keyed by campaign_id (Shopee is source of truth for "active").
     */
    private function liveManualAdsByCampaign(): Collection
    {
        return collect($this->api->listManualProductAds(true))->keyBy('campaign_id');
    }

    /**
     * Tracked DB rows that are live on Shopee right now.
     */
    private function activeItemAdModels(?Collection $liveByCampaign = null): Collection
    {
        $liveByCampaign ??= $this->liveManualAdsByCampaign();
        $liveIds = $liveByCampaign->keys()->all();

        if ($liveIds === []) {
            return collect();
        }

        return ShopeeAdsItemAd::query()
            ->whereIn('campaign_id', $liveIds)
            ->get();
    }

    /**
     * Delete an item ad from Shopee and remove the DB row (not pause/end-only).
     */
    private function removeItemAd(ShopeeAdsItemAd $ad, float $roas, int $streak, string $reason): bool
    {
        if (! $this->api->deleteItemAd($ad->campaign_id)) {
            return false;
        }

        $this->recordHistory(
            ShopeeAdsType::ProdukManual->value,
            $ad->campaign_id,
            'delete',
            (int) $ad->budget,
            null,
            null,
            $reason.sprintf(' (ROAS %.2f, streak %d)', $roas, $streak),
        );

        $ad->delete();

        return true;
    }

    /**
     * Persist today's ROAS/spend snapshot for rolling-window replenishment ranking.
     *
     * @param  Collection<int, ShopeeAdsItemAd>  $ads
     * @param  array<string, array{roas: float, spend: float}>  $performance
     */
    private function snapshotItemAdPerformance(Collection $ads, array $performance): void
    {
        $today = $this->jakartaNow()->toDateString();

        foreach ($ads as $ad) {
            $row = $performance[$ad->campaign_id] ?? null;
            $roas = (float) ($row['roas'] ?? $ad->last_roas ?? 0);
            $spend = (int) round((float) ($row['spend'] ?? 0));

            ShopeeAdsItemPerformanceSnapshot::query()->updateOrCreate(
                ['item_id' => $ad->item_id, 'snapshot_date' => $today],
                [
                    'campaign_id' => $ad->campaign_id,
                    'roas' => $roas,
                    'spend' => $spend,
                    'budget' => (int) $ad->budget,
                ],
            );
        }
    }

    /**
     * Daily-reset delete rule for volatile ROAS:
     * - Increment checks use streak (item_off_after_checks) — see applyItemAdsIncrement().
     * - Daily reset uses BOTH today's ROAS and a 7-day rolling average from snapshots.
     *   Delete only when today AND rolling avg are below item_roas_off_threshold, so one
     *   bad day after a good week does not immediately drop the ad.
     *
     * @param  Collection<int, ShopeeAdsItemAd>  $ads
     * @param  array<string, array{roas: float, spend: float}>  $performance
     */
    private function purgeUnderperformingItemAdsAtReset(
        ShopeeAdsSetting $settings,
        Collection $ads,
        array $performance,
    ): void {
        $threshold = (float) $settings->item_roas_off_threshold;
        $rolling = $this->rollingAverageRoasByItem(
            $ads->pluck('item_id')->map(fn ($id) => (int) $id)->all(),
        );

        foreach ($ads as $ad) {
            $todayRoas = (float) ($performance[$ad->campaign_id]['roas'] ?? $ad->last_roas ?? 0);
            $hasRollingHistory = array_key_exists($ad->item_id, $rolling);
            $avgRoas = (float) ($rolling[$ad->item_id] ?? $todayRoas);
            $streak = $todayRoas < $threshold ? (int) $ad->low_roas_streak + 1 : 0;

            $deleteByStreak = $streak >= (int) $settings->item_off_after_checks;
            $deleteByRolling = $hasRollingHistory && $avgRoas < $threshold && $todayRoas < $threshold;

            if ($deleteByStreak || $deleteByRolling) {
                $this->removeItemAd(
                    $ad,
                    $todayRoas,
                    $streak,
                    $deleteByStreak
                        ? 'Daily reset delete (low ROAS streak)'
                        : 'Daily reset delete (7-day avg + today below threshold)',
                );
            } else {
                $ad->update(['low_roas_streak' => $streak, 'last_roas' => $todayRoas]);
            }
        }
    }

    /**
     * Trim excess live ads down to max_item_ads by deleting lowest rolling ROAS first.
     *
     * @param  array<string, array{roas: float, spend: float}>  $performance
     */
    private function enforceItemAdCap(
        ShopeeAdsSetting $settings,
        Collection $liveByCampaign,
        array $performance = [],
    ): int {
        $multipliers = $this->specialRules->resolveForToday($settings);
        $cap = $multipliers->scaledMaxItemAds((int) $settings->max_item_ads);
        $excess = $liveByCampaign->count() - $cap;

        if ($excess <= 0) {
            return 0;
        }

        $ads = $this->activeItemAdModels($liveByCampaign);
        $rolling = $this->rollingAverageRoasByItem(
            $ads->pluck('item_id')->map(fn ($id) => (int) $id)->all(),
        );

        $ranked = $ads->sortBy(function (ShopeeAdsItemAd $ad) use ($performance, $rolling) {
            $today = (float) ($performance[$ad->campaign_id]['roas'] ?? $ad->last_roas ?? 0);

            return (float) ($rolling[$ad->item_id] ?? $today);
        });

        $deleted = 0;
        foreach ($ranked->take($excess) as $ad) {
            $todayRoas = (float) ($performance[$ad->campaign_id]['roas'] ?? $ad->last_roas ?? 0);
            if ($this->removeItemAd($ad, $todayRoas, (int) $ad->low_roas_streak, 'Cap enforcement delete (lowest ROAS)')) {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            Log::info('Shopee Ads item cap enforced', [
                'cap' => $cap,
                'deleted' => $deleted,
                'live_before' => $liveByCampaign->count(),
            ]);
        }

        return $deleted;
    }

    /**
     * Remove DB rows for campaigns no longer live on Shopee.
     */
    private function purgeStaleItemAdRows(Collection $liveByCampaign): void
    {
        if (! $this->api->hasShopAuthorization()) {
            return;
        }

        $query = ShopeeAdsItemAd::query();
        if ($liveByCampaign->isNotEmpty()) {
            $query->whereNotIn('campaign_id', $liveByCampaign->keys()->all());
        }

        $query->delete();
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, float>
     */
    private function rollingAverageRoasByItem(array $itemIds, int $days = 7): array
    {
        if ($itemIds === []) {
            return [];
        }

        $since = $this->jakartaNow()->copy()->subDays(max(1, $days) - 1)->toDateString();

        return ShopeeAdsItemPerformanceSnapshot::query()
            ->whereIn('item_id', $itemIds)
            ->where('snapshot_date', '>=', $since)
            ->get()
            ->groupBy('item_id')
            ->map(fn (Collection $rows) => round((float) $rows->avg('roas'), 4))
            ->all();
    }

    /**
     * Rank replenish / group-suggest candidates. Uses 7-day snapshot avg when available,
     * otherwise Shopee recommended-item tags (best selling / best ROI / top search).
     *
     * @param  list<int>  $excludeItemIds
     * @return list<array{item_id: int, source: string, avg_roas: float, reason: string}>
     */
    private function rankItemAdCandidates(ShopeeAdsSetting $settings, array $excludeItemIds): array
    {
        $exclude = array_fill_keys(array_map('intval', $excludeItemIds), true);
        $candidates = [];

        $since = $this->jakartaNow()->copy()->subDays(6)->toDateString();
        $history = ShopeeAdsItemPerformanceSnapshot::query()
            ->where('snapshot_date', '>=', $since)
            ->get()
            ->groupBy('item_id');

        foreach ($history as $itemId => $rows) {
            $itemId = (int) $itemId;
            if (isset($exclude[$itemId])) {
                continue;
            }

            $avgRoas = round((float) $rows->avg('roas'), 4);
            $candidates[$itemId] = [
                'item_id' => $itemId,
                'source' => 'performance_history',
                'avg_roas' => $avgRoas,
                'reason' => sprintf('7-day avg ROAS %.2f (%d snapshots)', $avgRoas, $rows->count()),
            ];
        }

        foreach ($this->api->getRecommendedItems() as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            if ($itemId <= 0 || isset($exclude[$itemId]) || isset($candidates[$itemId])) {
                continue;
            }

            $tags = $row['sku_tags'] ?? [];
            $candidates[$itemId] = [
                'item_id' => $itemId,
                'source' => 'recommended',
                'avg_roas' => 0.0,
                'reason' => $tags !== [] ? 'Shopee tags: '.implode(', ', $tags) : 'Shopee recommended',
            ];
        }

        return collect($candidates)
            ->sortByDesc(fn (array $row) => [$row['avg_roas'], str_contains($row['source'], 'performance') ? 1 : 0])
            ->values()
            ->all();
    }
}
