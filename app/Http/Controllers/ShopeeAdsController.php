<?php

namespace App\Http\Controllers;

use App\Enums\ShopeeAdsType;
use App\Models\ShopeeAds;
use App\Models\ShopeeAdsBudgetHistory;
use App\Models\ShopeeAdsItemAd;
use App\Models\ShopeeAdsSchedule;
use App\Models\ShopeeAdsSetting;
use App\Services\ShopeeAds\ShopeeAdsApiService;
use App\Services\ShopeeAds\ShopeeAdsEngineService;
use App\Services\ShopeeAds\ShopeeAdsSpecialRulesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShopeeAdsController extends Controller
{
    public function index(ShopeeAdsApiService $api, ShopeeAdsSpecialRulesService $specialRules): View
    {
        Gate::authorize(ShopeeAds::getPermissions()['view']);

        $settings = ShopeeAdsSetting::current();
        $schedules = ShopeeAdsSchedule::query()->orderBy('ad_type')->orderBy('run_time')->get();
        $history = ShopeeAdsBudgetHistory::query()->orderByDesc('created_at')->limit(50)->get();
        $itemAds = ShopeeAdsItemAd::query()->orderByDesc('updated_at')->limit(30)->get();
        $ruleStatus = $specialRules->todayStatus($settings);

        return view('shopee-ads.index', [
            'settings' => $settings,
            'schedules' => $schedules,
            'history' => $history,
            'itemAds' => $itemAds,
            'adTypeLabels' => ShopeeAdsType::labels(),
            'supportedTypes' => ShopeeAdsType::supportedScheduleTypes(),
            'connection' => $api->getConnectionStatus(),
            'planned' => $this->plannedEndOfDay($settings, $schedules, $specialRules),
            'ruleStatus' => $ruleStatus,
            'canEdit' => request()->user()?->can(ShopeeAds::getPermissions()['edit']) ?? false,
            'canBoost' => request()->user()?->can(ShopeeAds::getPermissions()['boost']) ?? false,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        Gate::authorize(ShopeeAds::getPermissions()['edit']);

        $validated = $request->validate([
            'starting_budget_gmv_max' => ['required', 'integer', 'min:0'],
            'daily_max_budget' => ['required', 'integer', 'min:1'],
            'item_ad_starting_budget' => ['required', 'integer', 'min:25000'],
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
        $settings->update([
            'status' => $settings->isPaused() ? 'active' : 'paused',
        ]);

        return back()->with('success', $settings->isPaused() ? 'Automasi di-pause.' : 'Automasi diaktifkan.');
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
            return redirect()->route('shopee-ads.index')->with('error', 'Gagal menukar kode OAuth Shopee.');
        }

        return redirect()->route('shopee-ads.index')->with('success', 'Shopee shop berhasil diotorisasi.');
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

        return back()->with('success', 'Daily reset dijalankan.');
    }

    public function boostBudget(ShopeeAdsEngineService $engine): RedirectResponse
    {
        Gate::authorize(ShopeeAds::getPermissions()['boost']);

        $settings = ShopeeAdsSetting::current();
        $result = $engine->applyManualBudgetBoost($settings);

        return back()->with('success', $result['message']);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ShopeeAdsSchedule>  $schedules
     * @return array<string, array<string, mixed>>
     */
    private function plannedEndOfDay(ShopeeAdsSetting $settings, $schedules, ShopeeAdsSpecialRulesService $specialRules): array
    {
        $multipliers = $specialRules->resolveForToday($settings);

        $gmvStart = (int) ($settings->starting_budget_gmv_max ?: $settings->starting_budget);
        $gmvStart = $multipliers->scaledGmvAmount($gmvStart);
        $gmvInc = (int) $schedules
            ->where('ad_type', ShopeeAdsType::GmvMax->value)
            ->where('enabled', true)
            ->sum('increment_idr');
        $gmvInc = $multipliers->scaledGmvAmount($gmvInc);

        $itemStartPerAd = max((int) $settings->item_ad_starting_budget, ShopeeAdsApiService::ITEM_AD_MIN_BUDGET);
        $itemStartPerAd = $multipliers->scaledItemBudgetAmount($itemStartPerAd);
        $activeItemAds = ShopeeAdsItemAd::query()
            ->where('turned_off', false)
            ->whereNotIn('status', ['ended', 'closed', 'berakhir'])
            ->count();
        $effectiveMaxAds = $multipliers->scaledMaxItemAds((int) $settings->max_item_ads);
        $itemStart = $itemStartPerAd * $activeItemAds;
        $itemInc = (int) $schedules
            ->where('ad_type', ShopeeAdsType::ProdukManual->value)
            ->where('enabled', true)
            ->sum('increment_idr');
        $itemInc = $multipliers->scaledItemBudgetAmount($itemInc);

        return [
            ShopeeAdsType::GmvMax->value => [
                'start' => $gmvStart,
                'tracked' => (int) $settings->gms_current_budget,
                'increments' => $gmvInc,
                'planned' => min($gmvStart + $gmvInc, $settings->daily_max_budget),
                'cap' => (int) $settings->daily_max_budget,
            ],
            ShopeeAdsType::ProdukManual->value => [
                'start_per_ad' => $itemStartPerAd,
                'start' => $itemStart,
                'increments' => $itemInc,
                'planned' => $itemStart + $itemInc,
                'active_ads' => $activeItemAds,
                'effective_max_ads' => $effectiveMaxAds,
                'cap' => (int) $settings->daily_max_budget,
                'note' => 'Pool per run dibagi per item ad by ROAS tier',
            ],
        ];
    }
}
