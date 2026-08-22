<?php

namespace App\Http\Controllers;

use App\Enums\ShopeeAdsType;
use App\Models\ShopeeAds;
use App\Models\ShopeeAdsBudgetHistory;
use App\Models\ShopeeAdsGroupState;
use App\Models\ShopeeAdsSchedule;
use App\Models\ShopeeAdsSetting;
use App\Services\ShopeeAds\ShopeeAdsApiService;
use App\Services\ShopeeAds\ShopeeAdsEngineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShopeeAdsController extends Controller
{
    public function index(ShopeeAdsApiService $api): View
    {
        Gate::authorize(ShopeeAds::getPermissions()['view']);

        $settings = ShopeeAdsSetting::current();
        $schedules = ShopeeAdsSchedule::query()->orderBy('ad_type')->orderBy('run_time')->get();
        $history = ShopeeAdsBudgetHistory::query()->orderByDesc('created_at')->limit(50)->get();
        $groupStates = ShopeeAdsGroupState::query()->orderByDesc('updated_at')->limit(20)->get();

        $planned = $this->plannedEndOfDay($settings, $schedules);

        return view('shopee-ads.index', [
            'settings' => $settings,
            'schedules' => $schedules,
            'history' => $history,
            'groupStates' => $groupStates,
            'adTypeLabels' => ShopeeAdsType::labels(),
            'connection' => $api->getConnectionStatus(),
            'planned' => $planned,
            'canEdit' => request()->user()?->can(ShopeeAds::getPermissions()['edit']) ?? false,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        Gate::authorize(ShopeeAds::getPermissions()['edit']);

        $validated = $request->validate([
            'starting_budget' => ['required', 'integer', 'min:0'],
            'daily_max_budget' => ['required', 'integer', 'min:1'],
            'group_split_high' => ['required', 'integer', 'min:0', 'max:100'],
            'group_split_mid' => ['required', 'integer', 'min:0', 'max:100'],
            'group_split_low' => ['required', 'integer', 'min:0', 'max:100'],
            'group_roas_off_threshold' => ['required', 'numeric', 'min:0'],
            'group_off_after_increments' => ['required', 'integer', 'min:1'],
            'group_replenish_enabled' => ['sometimes', 'boolean'],
            'group_target_active_count' => ['required', 'integer', 'min:1'],
            'group_replenish_max_per_run' => ['required', 'integer', 'min:1'],
            'group_replenish_min_roas' => ['required', 'numeric', 'min:0'],
            'group_roas_target' => ['required', 'numeric', 'min:0'],
            'daily_reset_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'daily_reset_minute' => ['required', 'integer', 'min:0', 'max:59'],
            'group_replenish_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'group_replenish_minute' => ['required', 'integer', 'min:0', 'max:59'],
            'toko_auto_campaign_id' => ['nullable', 'string', 'max:64'],
            'toko_manual_campaign_id' => ['nullable', 'string', 'max:64'],
            'produk_auto_campaign_id' => ['nullable', 'string', 'max:64'],
        ]);

        $settings = ShopeeAdsSetting::current();
        $validated['group_replenish_enabled'] = $request->boolean('group_replenish_enabled');
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

        $type = ShopeeAdsType::normalize($validated['ad_type']);
        if ($type === null) {
            return back()->with('error', 'Tipe iklan tidak dikenal.');
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
        $result = $engine->replenishGroups($settings);

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

    /**
     * @param  \Illuminate\Support\Collection<int, ShopeeAdsSchedule>  $schedules
     * @return array<string, array{start: int, planned: int, cap: int}>
     */
    private function plannedEndOfDay(ShopeeAdsSetting $settings, $schedules): array
    {
        $planned = [];

        foreach (ShopeeAdsType::singleAdTypes() as $adType) {
            $sum = (int) $schedules->where('ad_type', $adType)->where('enabled', true)->sum('increment_idr');
            $planned[$adType] = [
                'start' => $settings->starting_budget,
                'planned' => min($settings->starting_budget + $sum, $settings->daily_max_budget),
                'cap' => $settings->daily_max_budget,
            ];
        }

        $groupSum = (int) $schedules->where('ad_type', ShopeeAdsType::Group->value)->where('enabled', true)->sum('increment_idr');
        $planned[ShopeeAdsType::Group->value] = [
            'start' => $settings->starting_budget,
            'planned' => $groupSum,
            'cap' => $settings->daily_max_budget,
            'note' => 'Pool total per run (split by ROAS)',
        ];

        return $planned;
    }
}
