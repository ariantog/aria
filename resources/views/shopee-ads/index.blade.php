@extends('layouts.app')

@section('title', 'Shopee Ads')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Shopee Ads', 'href' => route('shopee-ads.index')],
];
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Shopee Ads</h1>
            <p class="mt-1 text-sm text-gray-500">
                Automasi budget untuk <strong>GMV Max</strong> dan <strong>Iklan Produk Individual</strong> (API Shopee yang aktif).
                Legacy tipe (Toko Auto/Manual, Produk Otomatis, Group) tidak didukung API.
            </p>
        </div>
        @if($canEdit)
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('shopee-ads.toggle-pause') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    {{ $settings->isPaused() ? 'Resume' : 'Pause' }}
                </button>
            </form>
            <form method="POST" action="{{ route('shopee-ads.run-schedules') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-blue-300 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-900 hover:bg-blue-100">
                    Run Jadwal Now
                </button>
            </form>
            <form method="POST" action="{{ route('shopee-ads.replenish') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Replenish Item Ads
                </button>
            </form>
            <form method="POST" action="{{ route('shopee-ads.daily-reset') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100">
                    Daily Reset Now
                </button>
            </form>
            @if($canBoost)
            <form method="POST" action="{{ route('shopee-ads.boost') }}" onsubmit="return confirm('Boost semua budget iklan ×{{ $settings->manual_boost_multiplier }}?')">
                @csrf
                <button type="submit" class="rounded-lg border border-purple-300 bg-purple-50 px-4 py-2 text-sm font-medium text-purple-900 hover:bg-purple-100">
                    Boost ×{{ $settings->manual_boost_multiplier }}
                </button>
            </form>
            @endif
            <a href="{{ route('shopee-ads.authorize') }}" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
                Authorize Shopee
            </a>
        </div>
        @endif
    </div>

    @if(session('success'))
    <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if($automationBlockers !== [])
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-testid="shopee-ads-automation-blockers">
        <p class="font-medium">Automasi tidak berjalan:</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach($automationBlockers as $reason)
            <li>{{ $reason }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-400">Status</p>
            <p class="mt-2 text-lg font-semibold {{ $settings->isAutomationActive() ? 'text-green-700' : 'text-amber-700' }}">
                {{ $settings->isAutomationActive() ? 'Active' : 'Paused' }}
            </p>
            @if(strtolower(trim($settings->status)) !== 'active' && strtolower(trim($settings->status)) !== 'paused')
            <p class="mt-1 text-xs text-gray-500">DB status: <code class="text-xs">{{ $settings->status }}</code></p>
            @endif
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-400">GMV Max (tracked)</p>
            <p class="mt-2 text-lg font-semibold text-gray-900">Rp {{ format_amount($settings->gms_current_budget, 0) }}</p>
            @if($settings->gms_campaign_id)
            <p class="mt-1 text-xs text-gray-500">Campaign {{ $settings->gms_campaign_id }}</p>
            @endif
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-400">Combined daily cap</p>
            <p class="mt-2 text-lg font-semibold text-gray-900">Rp {{ format_amount($settings->daily_max_budget, 0) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" data-testid="shopee-ads-cron-status">
            <p class="text-xs font-medium uppercase text-gray-400">Cron (shopee-ads:process)</p>
            <p class="mt-2 text-lg font-semibold {{ ($cronTask?->active ?? false) ? 'text-green-700' : 'text-amber-700' }}">
                {{ ($cronTask?->active ?? false) ? 'Enabled' : 'Disabled' }}
            </p>
            @if($cronTask?->last_run_at)
            <p class="mt-1 text-xs text-gray-500">Last run {{ $cronTask->last_run_at->timezone($automationTimezone)->format('d M Y H:i') }} WIB</p>
            @else
            <p class="mt-1 text-xs text-gray-500">Belum ada run tercatat — pastikan system cron memanggil <code class="text-xs">schedule:run</code> tiap menit.</p>
            @endif
            <p class="mt-1 text-xs text-gray-400">Sekarang {{ $nowWib->format('d M Y H:i') }} WIB (GMT+7) · tz {{ $automationTimezone }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-400">OAuth</p>
            <p class="mt-2 text-lg font-semibold {{ $connection['has_token'] && ! $connection['is_expired'] ? 'text-green-700' : 'text-red-700' }}">
                {{ $connection['has_token'] && ! $connection['is_expired'] ? 'Connected' : 'Not connected' }}
            </p>
            @if($connection['shop_id'])
            <p class="mt-1 text-xs text-gray-500">Shop {{ $connection['shop_id'] }}</p>
            @endif
            @if($connection['last_error'])
            <p class="mt-2 text-xs text-red-600">{{ $oauthErrorHint ?? $connection['last_error'] }}</p>
            @endif
            <p class="mt-2 text-xs text-gray-400">Redirect: {{ $oauthRedirectUrl }}</p>
            <p class="mt-1 text-xs text-gray-400">API calls: whitelist server outbound IP di Shopee Open Platform → App list → IP Address Whitelist.</p>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-gray-900">Rencana end-of-day</h2>
            @if($ruleStatus['labels'] !== [])
            <div class="flex flex-wrap gap-2 text-xs">
                @if($ruleStatus['double_date'])
                <span class="rounded-full bg-pink-100 px-2 py-1 font-medium text-pink-800">Double date aktif</span>
                @endif
                @if($ruleStatus['payday'])
                <span class="rounded-full bg-blue-100 px-2 py-1 font-medium text-blue-800">Payday aktif</span>
                @endif
                @foreach($ruleStatus['labels'] as $label)
                <span class="rounded-full bg-gray-100 px-2 py-1 text-gray-700">{{ $label }}</span>
                @endforeach
            </div>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3">Tipe</th>
                        <th class="px-6 py-3">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($planned as $type => $row)
                    <tr>
                        <td class="px-6 py-3 font-medium">{{ $adTypeLabels[$type] ?? $type }}</td>
                        <td class="px-6 py-3 text-gray-600">
                            @if($type === 'gmv_max')
                            Start Rp {{ format_amount($row['start'], 0) }}
                            + increments Rp {{ format_amount($row['increments'], 0) }}
                            → planned Rp {{ format_amount($row['planned'], 0) }}
                            (cap Rp {{ format_amount($row['cap'], 0) }})
                            @else
                            Start Rp {{ format_amount($row['start'], 0) }}
                            ({{ $row['active_ads'] }} ads × Rp {{ format_amount($row['start_per_ad'], 0) }}
                            @if(isset($row['effective_max_ads']) && $row['effective_max_ads'] > $settings->max_item_ads)
                            · max {{ $row['effective_max_ads'] }}
                            @endif)
                            + increments Rp {{ format_amount($row['increments'], 0) }}
                            → planned Rp {{ format_amount($row['planned'], 0) }}
                            · {{ $row['note'] }}
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($canEdit)
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm" x-data="{ settingsOpen: false, specialRulesOpen: false }">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-900">Pengaturan (GMV + Item)</h2>
            <button
                type="button"
                @click="settingsOpen = !settingsOpen"
                class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-gray-600 hover:bg-gray-50"
                data-testid="toggle-shopee-ads-settings"
                :aria-expanded="settingsOpen"
            >
                <span x-text="settingsOpen ? 'Sembunyikan' : 'Tampilkan'"></span>
                <svg class="h-4 w-4 transition-transform" :class="settingsOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
        </div>
        <form method="POST" action="{{ route('shopee-ads.settings.update') }}" x-show="settingsOpen" x-cloak class="space-y-4 px-6 py-4">
            @csrf
            @method('PATCH')
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <label class="block text-sm">
                    <span class="text-gray-600">GMV Max starting budget</span>
                    <input type="number" name="starting_budget_gmv_max" value="{{ $settings->starting_budget_gmv_max }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                </label>
                <label class="block text-sm">
                    <span class="text-gray-600">Combined daily cap (IDR)</span>
                    <input type="number" name="daily_max_budget" value="{{ $settings->daily_max_budget }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                </label>
                <label class="block text-sm">
                    <span class="text-gray-600">Item ad starting budget</span>
                    <input type="number" name="item_ad_starting_budget" min="25000" value="{{ $settings->item_ad_starting_budget }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                </label>
                <label class="block text-sm">
                    <span class="text-gray-600">Max item ads</span>
                    <input type="number" name="max_item_ads" value="{{ $settings->max_item_ads }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                </label>
                <label class="block text-sm">
                    <span class="text-gray-600">Item split high / mid / low %</span>
                    <div class="mt-1 flex gap-2">
                        <input type="number" name="item_split_high" value="{{ $settings->item_split_high }}" class="w-full rounded-lg border-gray-300" required>
                        <input type="number" name="item_split_mid" value="{{ $settings->item_split_mid }}" class="w-full rounded-lg border-gray-300" required>
                        <input type="number" name="item_split_low" value="{{ $settings->item_split_low }}" class="w-full rounded-lg border-gray-300" required>
                    </div>
                </label>
                <label class="block text-sm">
                    <span class="text-gray-600">Item ROAS off / after N checks</span>
                    <div class="mt-1 flex gap-2">
                        <input type="number" step="0.01" name="item_roas_off_threshold" value="{{ $settings->item_roas_off_threshold }}" class="w-full rounded-lg border-gray-300" required>
                        <input type="number" name="item_off_after_checks" value="{{ $settings->item_off_after_checks }}" class="w-full rounded-lg border-gray-300" required>
                    </div>
                </label>
                <label class="block text-sm">
                    <span class="text-gray-600">New item ROAS target (0 = auto)</span>
                    <input type="number" step="0.01" name="item_new_roas_target" value="{{ $settings->item_new_roas_target }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                </label>
                <label class="block text-sm">
                    <span class="text-gray-600">Daily reset WIB (H:M)</span>
                    <div class="mt-1 flex gap-2">
                        <input type="number" name="daily_reset_hour" value="{{ $settings->daily_reset_hour }}" class="w-full rounded-lg border-gray-300" required>
                        <input type="number" name="daily_reset_minute" value="{{ $settings->daily_reset_minute }}" class="w-full rounded-lg border-gray-300" required>
                    </div>
                </label>
                <label class="block text-sm">
                    <span class="text-gray-600">Item replenish WIB (H:M)</span>
                    <div class="mt-1 flex gap-2">
                        <input type="number" name="item_replenish_hour" value="{{ $settings->item_replenish_hour }}" class="w-full rounded-lg border-gray-300" required>
                        <input type="number" name="item_replenish_minute" value="{{ $settings->item_replenish_minute }}" class="w-full rounded-lg border-gray-300" required>
                    </div>
                </label>
                <label class="block text-sm">
                    <span class="text-gray-600">Max new item ads / run</span>
                    <input type="number" name="item_replenish_max_per_run" value="{{ $settings->item_replenish_max_per_run }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                </label>
            </div>
            <div class="flex flex-wrap gap-4 text-sm text-gray-700">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="item_ads_enabled" value="1" {{ $settings->item_ads_enabled ? 'checked' : '' }}> Item ads enabled
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="item_replenish_enabled" value="1" {{ $settings->item_replenish_enabled ? 'checked' : '' }}> Auto item replenish
                </label>
            </div>

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <button
                    type="button"
                    @click="specialRulesOpen = !specialRulesOpen"
                    class="flex w-full items-center justify-between gap-2 text-left"
                    data-testid="toggle-shopee-ads-special-rules"
                    :aria-expanded="specialRulesOpen"
                >
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Aturan spesial</h3>
                        <p class="mt-1 text-xs text-gray-500">Multiplier diterapkan otomatis pada daily reset, replenish, dan jadwal increment (WIB).</p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-gray-500 transition-transform" :class="specialRulesOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="specialRulesOpen" x-cloak class="mt-4 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-3 rounded-lg border border-gray-200 bg-white p-4">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-800">
                                <input type="checkbox" name="double_date_enabled" value="1" {{ $settings->double_date_enabled ? 'checked' : '' }}>
                                Double date (tanggal kembar, mis. 8/8)
                            </label>
                            <div class="grid gap-2 sm:grid-cols-3 text-sm">
                                <label>
                                    <span class="text-gray-600">GMV ×</span>
                                    <input type="number" step="0.1" name="double_date_gmv_multiplier" value="{{ $settings->double_date_gmv_multiplier }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                                </label>
                                <label>
                                    <span class="text-gray-600">Item ads ×</span>
                                    <input type="number" step="0.1" name="double_date_item_ads_multiplier" value="{{ $settings->double_date_item_ads_multiplier }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                                </label>
                                <label>
                                    <span class="text-gray-600">Item budget ×</span>
                                    <input type="number" step="0.1" name="double_date_item_budget_multiplier" value="{{ $settings->double_date_item_budget_multiplier }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                                </label>
                            </div>
                        </div>
                        <div class="space-y-3 rounded-lg border border-gray-200 bg-white p-4">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-800">
                                <input type="checkbox" name="payday_enabled" value="1" {{ $settings->payday_enabled ? 'checked' : '' }}>
                                Payday (tanggal gajian)
                            </label>
                            <div class="grid gap-2 sm:grid-cols-3 text-sm">
                                <label>
                                    <span class="text-gray-600">Tanggal</span>
                                    <input type="number" name="payday_day" min="1" max="28" value="{{ $settings->payday_day }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                                </label>
                                <label>
                                    <span class="text-gray-600">GMV ×</span>
                                    <input type="number" step="0.1" name="payday_gmv_multiplier" value="{{ $settings->payday_gmv_multiplier }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                                </label>
                                <label>
                                    <span class="text-gray-600">Item ×</span>
                                    <input type="number" step="0.1" name="payday_item_multiplier" value="{{ $settings->payday_item_multiplier }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg border border-purple-200 bg-purple-50 p-4 text-sm">
                        <label class="block font-medium text-purple-900">
                            Manual boost (tombol Boost — permission <code class="text-xs">shopee-ads-boost</code>)
                        </label>
                        <label class="mt-2 block">
                            <span class="text-gray-600">Multiplier ×</span>
                            <input type="number" step="0.1" name="manual_boost_multiplier" value="{{ $settings->manual_boost_multiplier }}" class="mt-1 w-full max-w-xs rounded-lg border-gray-300" required>
                        </label>
                        <p class="mt-2 text-xs text-purple-800">Mengalikan budget GMV Max + semua item ads yang aktif sekarang (live dari Shopee).</p>
                    </div>
                </div>
            </div>
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Simpan</button>
        </form>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-900">Tambah jadwal</h2>
        </div>
        <form method="POST" action="{{ route('shopee-ads.schedules.store') }}" class="space-y-4 px-6 py-4">
            @csrf
            <label class="block text-sm">
                <span class="text-gray-600">Tipe iklan</span>
                <select name="ad_type" class="mt-1 w-full rounded-lg border-gray-300" required>
                    @foreach($supportedTypes as $type)
                    <option value="{{ $type }}">{{ $adTypeLabels[$type] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm">
                <span class="text-gray-600">Waktu WIB (HH:MM)</span>
                <input type="text" name="run_time" placeholder="09:00" pattern="\d{2}:\d{2}" class="mt-1 w-full rounded-lg border-gray-300" required>
            </label>
            <label class="block text-sm">
                <span class="text-gray-600">Increment IDR (item = total pool)</span>
                <input type="number" name="increment_idr" min="1" class="mt-1 w-full rounded-lg border-gray-300" required>
            </label>
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Tambah / Update</button>
        </form>
    </div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-900">Jadwal</h2>
            <p class="mt-1 text-xs text-gray-500">Increment jalan setelah HH:MM WIB (catch-up sampai midnight). Status <strong>Active</strong> atau legacy <code class="text-xs">Running</code> — bukan Paused.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3">Tipe</th>
                        <th class="px-6 py-3">Waktu</th>
                        <th class="px-6 py-3">Increment</th>
                        <th class="px-6 py-3">Last run</th>
                        @if($canEdit)<th class="px-6 py-3"></th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($schedules as $schedule)
                    <tr>
                        <td class="px-6 py-3">
                            {{ $adTypeLabels[$schedule->ad_type] ?? $schedule->ad_type }}
                            @if(! in_array($schedule->ad_type, $supportedTypes, true))
                            <span class="text-xs text-amber-600">(legacy)</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 font-mono">{{ $schedule->run_time }}</td>
                        <td class="px-6 py-3">Rp {{ format_amount($schedule->increment_idr, 0) }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ $schedule->last_run_at?->timezone('Asia/Jakarta')->format('d M H:i') ?? '—' }}</td>
                        @if($canEdit)
                        <td class="px-6 py-3">
                            <form method="POST" action="{{ route('shopee-ads.schedules.destroy', $schedule) }}" onsubmit="return confirm('Hapus?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Belum ada jadwal.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-900">Item ads (tracked)</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3">Campaign</th>
                        <th class="px-6 py-3">Item</th>
                        <th class="px-6 py-3">Budget</th>
                        <th class="px-6 py-3">ROAS</th>
                        <th class="px-6 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($itemAds as $ad)
                    <tr>
                        <td class="px-6 py-3 font-mono text-xs">{{ $ad->campaign_id }}</td>
                        <td class="px-6 py-3">{{ $ad->item_id }}</td>
                        <td class="px-6 py-3">Rp {{ format_amount($ad->budget, 0) }}</td>
                        <td class="px-6 py-3">{{ $ad->last_roas ?? '—' }}</td>
                        <td class="px-6 py-3">{{ $ad->turned_off ? 'ended' : $ad->status }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Belum ada item ads — sync setelah authorize.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-900">History</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3">Waktu</th>
                        <th class="px-6 py-3">Action</th>
                        <th class="px-6 py-3">Tipe</th>
                        <th class="px-6 py-3">Before → After</th>
                        <th class="px-6 py-3">Message</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($history as $row)
                    <tr>
                        <td class="px-6 py-3 text-gray-500">{{ $row->created_at?->timezone('Asia/Jakarta')->format('d M H:i') }}</td>
                        <td class="px-6 py-3">{{ $row->action }}</td>
                        <td class="px-6 py-3">{{ $adTypeLabels[$row->ad_type] ?? $row->ad_type ?? '—' }}</td>
                        <td class="px-6 py-3 font-mono text-xs">
                            @if($row->before_budget !== null || $row->after_budget !== null)
                            {{ format_amount($row->before_budget ?? 0, 0) }} → {{ format_amount($row->after_budget ?? 0, 0) }}
                            @else — @endif
                        </td>
                        <td class="px-6 py-3 text-gray-600">{{ $row->message }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Belum ada history.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-xs text-gray-400">
        Increment selalu membaca <strong>budget live</strong> dari Shopee (bukan starting budget / DB stale) — lihat
        <code>bots/engine.py</code> baris live-budget sync untuk item ads, dan
        <code>get_product_level_campaign_setting_info</code> untuk GMV Max di PHP.
    </p>
</div>
@endsection
