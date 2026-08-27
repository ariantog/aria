<?php

namespace App\Http\Controllers;

use App\Enums\ShopeeAdsAutomationStatus;
use App\Enums\ShopeeAdsType;
use App\Models\ShopeeAds;
use App\Models\ScheduledTask;
use App\Models\ShopeeAdsBudgetHistory;
use App\Models\ShopeeAdsItemAd;
use App\Models\ShopeeAdsSchedule;
use App\Models\ShopeeAdsSetting;
use App\Services\ShopeeAds\ShopeeAdsApiService;
use App\Services\ShopeeAds\ShopeeAdsEngineService;
use App\Services\ShopeeAds\ShopeeAdsSpecialRulesService;
use App\Support\SchedulerHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShopeeAdsController extends Controller
{
    public function index(ShopeeAdsApiService $api, ShopeeAdsSpecialRulesService $specialRules, ShopeeAdsEngineService $engine): View
    {
        Gate::authorize(ShopeeAds::getPermissions()['view']);

        $settings = ShopeeAdsSetting::current();
        $itemAdsSyncStats = session('item_ads_sync_stats');
        $itemAdsSyncError = session('item_ads_sync_error');

        $schedules = ShopeeAdsSchedule::query()->orderBy('ad_type')->orderBy('run_time')->get();
        $history = ShopeeAdsBudgetHistory::query()->orderByDesc('created_at')->limit(50)->get();
        $itemAds = ShopeeAdsItemAd::query()->orderByDesc('updated_at')->limit(30)->get();
        $ruleStatus = $specialRules->todayStatus($settings);
        $connection = $api->getConnectionStatus();
        $cronTask = ScheduledTask::query()->where('command', 'shopee-ads:process')->first();
        $schedulerHealth = SchedulerHealth::snapshot($cronTask);
        $automationBlockers = $this->automationBlockers($settings, $api, $connection, $cronTask);
        $automationTimezone = (string) config('services.shopee_ads.timezone', 'Asia/Jakarta');
        $nowWib = $engine->jakartaNow();

        return view('shopee-ads.index', [
            'settings' => $settings,
            'schedules' => $schedules,
            'history' => $history,
            'itemAds' => $itemAds,
            'adTypeLabels' => ShopeeAdsType::labels(),
            'supportedTypes' => ShopeeAdsType::supportedScheduleTypes(),
            'connection' => $connection,
            'cronTask' => $cronTask,
            'schedulerHealth' => $schedulerHealth,
            'automationBlockers' => $automationBlockers,
            'automationTimezone' => $automationTimezone,
            'nowWib' => $nowWib,
            'oauthErrorHint' => $api->formatOAuthErrorForUser($api->getLastOAuthError()),
            'planned' => $this->plannedEndOfDay($settings, $schedules, $specialRules, $engine),
            'ruleStatus' => $ruleStatus,
            'canEdit' => request()->user()?->can(ShopeeAds::getPermissions()['edit']) ?? false,
            'canBoost' => request()->user()?->can(ShopeeAds::getPermissions()['boost']) ?? false,
            'itemAdsSyncStats' => $itemAdsSyncStats,
            'itemAdsSyncError' => $itemAdsSyncError,
        ]);
    }

    public function syncItemAds(ShopeeAdsApiService $api, ShopeeAdsEngineService $engine): RedirectResponse
    {
        Gate::authorize(ShopeeAds::getPermissions()['edit']);

        if (! $api->hasShopAuthorization()) {
            return back()->with('error', 'Shopee belum diotorisasi.');
        }

        try {
            $stats = $engine->syncItemAds();

            return back()
                ->with('success', $this->formatItemAdsSyncMessage($stats))
                ->with('item_ads_sync_stats', $stats);
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->with('error', 'Sync item ads gagal: '.$e->getMessage())
                ->with('item_ads_sync_error', $e->getMessage());
        }
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        Gate::authorize(ShopeeAds::getPermissions()['edit']);

        $validated = $request->validate([
            'starting_budget_gmv_max' => ['required', 'integer', 'min:0'],
            'daily_max_budget' => ['required', 'integer', 'min:1'],
            'item_ad_starting_budget' => [
                'required',
                'integer',
                'min:'.ShopeeAdsApiService::ITEM_AD_MIN_BUDGET,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $slots = max(1, (int) request()->input('max_item_ads', 1));
                    $minTotal = $slots * ShopeeAdsApiService::ITEM_AD_MIN_BUDGET;
                    if ((int) $value < $minTotal) {
                        $fail("Item ads starting pool must be at least Rp {$minTotal} ({$slots} slots × min budget).");
                    }
                },
            ],
            'max_item_ads' => ['required', 'integer', 'min:1'],
            'item_split_high' => ['required', 'integer', 'min:0', 'max:100'],
            'item_split_mid' => ['required', 'integer', 'min:0', 'max:100'],
            'item_split_low' => ['required', 'integer', 'min:0', 'max:100'],
            'item_roas_off_threshold' => ['required', 'numeric', 'min:0'],
            'item_off_after_checks' => ['required', 'integer', 'min:1'],
            'item_new_roas_target' => ['required', 'numeric', 'min:0'],
            'item_replenish_max_per_run' => ['required', 'integer', 'min:1'],
            'daily_reset_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'daily_reset_minute' => ['required', 'integer', 'min:0', 'max:59'],
            'item_replenish_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'item_replenish_minute' => ['required', 'integer', 'min:0', 'max:59'],
            'double_date_gmv_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'double_date_item_ads_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'double_date_item_budget_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'payday_day' => ['required', 'integer', 'min:1', 'max:28'],
            'payday_gmv_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'payday_item_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'manual_boost_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
        ]);

        $settings = ShopeeAdsSetting::current();
        $validated['item_ads_enabled'] = $request->boolean('item_ads_enabled');
        $validated['item_replenish_enabled'] = $request->boolean('item_replenish_enabled');
        $validated['double_date_enabled'] = $request->boolean('double_date_enabled');
        $validated['payday_enabled'] = $request->boolean('payday_enabled');
        $settings->update($validated);

        return back()->with('success', 'Pengaturan Shopee Ads disimpan.');
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        Gate::authorize(ShopeeAds::getPermissions()['edit']);

        $validated = $request->validate([
            'ad_type' => ['required', 'string'],
            'run_time' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'increment_idr' => ['required', 'integer', 'min:1'],
        ]);

        $type = ShopeeAdsType::normalizeScheduleType($validated['ad_type']);
        if ($type === null) {
            return back()->with('error', 'Tipe iklan tidak dikenal.');
        }

        if (! $type->isSupported()) {
            return back()->with('error', 'Tipe iklan legacy — hanya gmv_max dan iklan_produk_manual yang didukung API Shopee.');
        }

        ShopeeAdsSchedule::query()->updateOrCreate(
            ['ad_type' => $type->value, 'run_time' => $validated['run_time']],
            ['increment_idr' => $validated['increment_idr'], 'enabled' => true],
        );

        return back()->with('success', 'Jadwal ditambahkan / diperbarui.');
    }

    public function destroySchedule(ShopeeAdsSchedule $shopeeAdsSchedule): RedirectResponse
    {
        Gate::authorize(ShopeeAds::getPermissions()['edit']);

        $shopeeAdsSchedule->delete();

        return back()->with('success', 'Jadwal dihapus.');
    }

    public function togglePause(): RedirectResponse
    {
        Gate::authorize(ShopeeAds::getPermissions()['edit']);

        $settings = ShopeeAdsSetting::current();
        $wasActive = $settings->automationStatus()->isActive();
        $settings->update(['status' => $wasActive ? ShopeeAdsAutomationStatus::Paused->value : ShopeeAdsAutomationStatus::Active->value]);

        return back()->with('success', $wasActive ? 'Automasi di-pause.' : 'Automasi diaktifkan.');
    }

    public function authorizeShop(ShopeeAdsApiService $api): RedirectResponse
    {
        Gate::authorize(ShopeeAds::getPermissions()['edit']);

        if (! $api->isConfigured()) {
            return back()->with('error', 'SHOPEE_PARTNER_ID / PARTNER_KEY belum dikonfigurasi di .env');
        }

        return redirect()->away($api->buildAuthorizeUrl());
    }

    public function oauthCallback(Request $request, ShopeeAdsApiService $api): RedirectResponse
    {
        $code = $request->query('code');
        $shopId = (int) $request->query('shop_id');

        if (! $code || $shopId <= 0) {
            return redirect()->route('shopee-ads.index')->with('error', 'OAuth callback tidak lengkap (code / shop_id).');
        }

        $result = $api->exchangeAuthCode($code, $shopId);

        if (! $result) {
            $detail = $api->formatOAuthErrorForUser($api->getLastOAuthError());
            $message = 'Gagal menukar kode OAuth Shopee.';
            if ($detail) {
                $message .= ' '.$detail;
            }

            return redirect()->route('shopee-ads.index')->with('error', $message);
        }

        return redirect()->route('shopee-ads.index')->with('success', 'Shopee shop berhasil diotorisasi.');
    }

    public function runSchedules(ShopeeAdsEngineService $engine): RedirectResponse
    {
        Gate::authorize(ShopeeAds::getPermissions()['edit']);

        $settings = ShopeeAdsSetting::current();
        if ($settings->isPaused()) {
            return back()->with('error', 'Automasi paused — Resume dulu sebelum menjalankan jadwal.');
        }

        $ran = $engine->runDueSchedules();

        return back()->with('success', $ran > 0
            ? "{$ran} jadwal increment dijalankan."
            : 'Tidak ada jadwal yang due (belum lewat HH:MM WIB atau sudah jalan hari ini).');
    }

    public function replenish(ShopeeAdsEngineService $engine): RedirectResponse
    {
        Gate::authorize(ShopeeAds::getPermissions()['edit']);

        $settings = ShopeeAdsSetting::current();
        $result = $engine->replenishItemAds($settings);

        return back()->with('success', $result['message']);
    }

    public function dailyReset(ShopeeAdsEngineService $engine): RedirectResponse
    {
        Gate::authorize(ShopeeAds::getPermissions()['edit']);

        $settings = ShopeeAdsSetting::current();
        $engine->dailyReset($settings);
        $settings->update(['last_daily_reset_at' => $engine->jakartaNow()]);

        $message = 'Daily reset dijalankan.';
        if ($settings->item_ads_enabled && $settings->item_replenish_enabled) {
            $replenish = $engine->replenishItemAds($settings->fresh(), fillToCap: true);
            $message .= ' '.$replenish['message'];
        }

        return back()->with('success', $message);
    }

    public function boostBudget(ShopeeAdsEngineService $engine): RedirectResponse
    {
        Gate::authorize(ShopeeAds::getPermissions()['boost']);

        $settings = ShopeeAdsSetting::current();
        $result = $engine->applyManualBudgetBoost($settings);

        return back()->with('success', $result['message']);
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return list<string>
     */
    private function automationBlockers(
        ShopeeAdsSetting $settings,
        ShopeeAdsApiService $api,
        array $connection,
        ?ScheduledTask $cronTask,
    ): array {
        $blockers = [];

        if (! $cronTask?->active) {
            $blockers[] = 'Cron «Shopee Ads Process» nonaktif — aktifkan di Cron Manager (/cron-manager).';
        } elseif ($cronTask->last_run_at === null) {
            $queueTask = ScheduledTask::query()->where('command', 'app:process-queue')->first();
            if ($queueTask?->active && $queueTask->last_run_at?->gt(now()->subMinutes(10))) {
                $blockers[] = 'Scheduler jalan (Process Queue OK) tetapi shopee-ads:process belum tercatat — toggle off/on di Cron Manager atau jalankan: php artisan schedule:list | grep shopee';
            } else {
                $blockers[] = 'Laravel scheduler belum jalan — OS cron harus memanggil php artisan schedule:run tiap menit (lihat Cron Manager Last Run).';
            }
        } elseif ($cronTask->last_run_at->lt(now()->subMinutes(10))) {
            $blockers[] = 'shopee-ads:process terakhir jalan '.$cronTask->last_run_at->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB — cek laravel.log untuk «Scheduled task» / «Shopee Ads process tick».';
        }

        if ($settings->isPaused()) {
            $blockers[] = sprintf(
                'Automasi tidak aktif (status DB: «%s»). Klik Resume.',
                $settings->status,
            );
        }

        if (! $api->isConfigured()) {
            $blockers[] = 'SHOPEE_PARTNER_ID / SHOPEE_PARTNER_KEY belum dikonfigurasi di .env.';
        }

        if (! ($connection['has_token'] ?? false)) {
            $blockers[] = 'Shopee belum diotorisasi — klik Authorize Shopee.';
        } elseif (($connection['is_expired'] ?? true)) {
            $blockers[] = 'Token Shopee expired — otorisasi ulang.';
        }

        return $blockers;
    }

    /**
     * @param  array{imported: int, updated: int, closed: int, active: int}  $stats
     */
    private function formatItemAdsSyncMessage(array $stats): string
    {
        return sprintf(
            'Sync item ads: %d aktif di Shopee (%d baru, %d diperbarui, %d ditutup di DB).',
            $stats['active'],
            $stats['imported'],
            $stats['updated'],
            $stats['closed'],
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ShopeeAdsSchedule>  $schedules
     * @return array<string, array<string, mixed>>
     */
    private function plannedEndOfDay(
        ShopeeAdsSetting $settings,
        $schedules,
        ShopeeAdsSpecialRulesService $specialRules,
        ShopeeAdsEngineService $engine,
    ): array
    {
        $multipliers = $specialRules->resolveForToday($settings);

        $gmvStart = (int) ($settings->starting_budget_gmv_max ?: $settings->starting_budget);
        $gmvStart = $multipliers->scaledGmvAmount($gmvStart);
        $gmvInc = (int) $schedules
            ->where('ad_type', ShopeeAdsType::GmvMax->value)
            ->where('enabled', true)
            ->sum('increment_idr');
        $gmvInc = $multipliers->scaledGmvAmount($gmvInc);

        $itemStartPool = $engine->itemAdsStartingPoolTotal($settings, $multipliers);
        $itemStartPerAd = $engine->itemAdBudgetPerSlot($settings, $multipliers);
        $slotCount = $engine->itemAdsSlotCount($settings, $multipliers);
        $activeItemAds = ShopeeAdsItemAd::query()
            ->where('turned_off', false)
            ->whereNotIn('status', ['ended', 'closed', 'berakhir'])
            ->count();
        $effectiveMaxAds = $slotCount;
        $itemStart = $itemStartPerAd * $activeItemAds;
        $itemInc = (int) $schedules
            ->where('ad_type', ShopeeAdsType::ProdukManual->value)
            ->where('enabled', true)
            ->sum('increment_idr');
        $itemInc = $multipliers->scaledItemBudgetAmount($itemInc);

        $gmvPlanned = $gmvStart + $gmvInc;
        $itemPlanned = $itemStart + $itemInc;
        $plannedTotal = $gmvPlanned + $itemPlanned;
        $baseCap = (int) $settings->daily_max_budget;
        $effectiveCap = $multipliers->scaledCombinedDailyCap($baseCap);
        $cappedTotal = min($plannedTotal, $effectiveCap);

        return [
            'combined' => [
                'base_cap' => $baseCap,
                'effective_cap' => $effectiveCap,
                'planned_total' => $plannedTotal,
                'capped_total' => $cappedTotal,
                'over_cap' => $plannedTotal > $effectiveCap,
            ],
            ShopeeAdsType::GmvMax->value => [
                'start' => $gmvStart,
                'tracked' => (int) $settings->gms_current_budget,
                'increments' => $gmvInc,
                'planned' => $gmvPlanned,
            ],
            ShopeeAdsType::ProdukManual->value => [
                'start_pool' => $itemStartPool,
                'start_per_ad' => $itemStartPerAd,
                'slot_count' => $slotCount,
                'start' => $itemStart,
                'increments' => $itemInc,
                'planned' => $itemPlanned,
                'active_ads' => $activeItemAds,
                'effective_max_ads' => $effectiveMaxAds,
                'note' => 'Starting pool ÷ max item ads per slot; increment schedules = total pool per run (split by ROAS)',
            ],
        ];
    }
}
