@extends('layouts.app')

@section('title', 'Shopee Ads')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Shopee Ads', 'href' => route('shopee-ads.index')],
];
$saCard = 'rounded-xl border border-gray-200 bg-white shadow-sm';
$saCardHeader = 'border-b border-gray-100 px-5 py-4';
$saLabel = 'block text-sm font-medium text-gray-700';
$saInput = 'mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500';
$saInputSm = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500';
$saTableHead = 'bg-gray-50 text-left text-xs uppercase text-gray-500';
$saTh = 'px-5 py-3';
$saTd = 'px-5 py-3';
$saDivide = 'divide-y divide-gray-100';
$saTextMuted = 'text-gray-500';
$saTextBody = 'text-gray-600';
$saBtnSecondary = 'rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50';
$saBtnPrimary = 'rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800';
$saBtnAmber = 'rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100';
$saBtnBlueOutline = 'rounded-lg border border-blue-300 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-900 hover:bg-blue-100';
$saBtnPurple = 'rounded-lg border border-purple-300 bg-purple-50 px-4 py-2 text-sm font-medium text-purple-900 hover:bg-purple-100';
$saBtnOrange = 'rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700';
$saAlertSuccess = 'rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800';
$saAlertError = 'rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800';
$saAlertWarn = 'rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900';
$saInnerCard = 'rounded-lg border border-gray-200 bg-gray-50 p-4';
$saInnerCardWhite = 'space-y-3 rounded-lg border border-gray-200 bg-white p-4';
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Shopee Ads</h2>
            <p class="mt-1 text-sm {{ $saTextMuted }}">
                Automasi budget untuk <strong class="text-gray-700">GMV Max</strong> dan
                <strong class="text-gray-700">Iklan Produk Individual</strong> (API Shopee yang aktif).
                Legacy tipe (Toko Auto/Manual, Produk Otomatis, Group) tidak didukung API.
            </p>
        </div>
        @if($canEdit)
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('shopee-ads.toggle-pause') }}">
                @csrf
                @if($settings->isPaused())
                <button type="submit" class="{{ $saBtnPrimary }}">Continue</button>
                @else
                <button type="submit" class="{{ $saBtnSecondary }}">Pause</button>
                @endif
            </form>
            <form method="POST" action="{{ route('shopee-ads.sync-item-ads') }}">
                @csrf
                <button type="submit" class="{{ $saBtnSecondary }}">Sync Item Ads</button>
            </form>
            <form method="POST" action="{{ route('shopee-ads.replenish') }}">
                @csrf
                <button type="submit" class="{{ $saBtnSecondary }}">Replenish Item Ads</button>
            </form>
            <form method="POST" action="{{ route('shopee-ads.daily-reset') }}">
                @csrf
                <button type="submit" class="{{ $saBtnAmber }}">Daily Reset Now</button>
            </form>
            @if($canBoost)
            <form method="POST" action="{{ route('shopee-ads.boost') }}" onsubmit="return confirm('Boost semua budget iklan ×{{ $settings->manual_boost_multiplier }}?')">
                @csrf
                <button type="submit" class="{{ $saBtnPurple }}">Boost ×{{ $settings->manual_boost_multiplier }}</button>
            </form>
            @endif
            <a href="{{ route('shopee-ads.authorize') }}" class="{{ $saBtnOrange }}">Authorize Shopee</a>
        </div>
        @endif
    </div>

    @if($automationBlockers !== [])
    <div class="{{ $saAlertWarn }}" data-testid="shopee-ads-automation-blockers">
        <p class="font-medium">Automasi tidak berjalan:</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach($automationBlockers as $reason)
            <li>{{ $reason }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
        <div class="{{ $saCard }} p-5">
            <p class="text-xs font-medium uppercase {{ $saTextMuted }}">Status</p>
            <p class="mt-2 text-lg font-semibold {{ $settings->automationStatus()->isActive() ? 'text-green-700' : 'text-amber-700' }}">
                {{ $settings->automationStatus()->isActive() ? 'Active' : 'Paused' }}
            </p>
            <p class="mt-1 text-xs {{ $saTextMuted }}">DB: <code class="text-xs">{{ $settings->status }}</code></p>
        </div>
        <div class="{{ $saCard }} p-5">
            <p class="text-xs font-medium uppercase {{ $saTextMuted }}">GMV Max (tracked)</p>
            <p class="mt-2 text-lg font-semibold text-gray-900">Rp {{ number_format($settings->gms_current_budget, 0, ',', '.') }}</p>
            @if($settings->gms_campaign_id)
            <p class="mt-1 text-xs {{ $saTextMuted }}">Campaign {{ $settings->gms_campaign_id }}</p>
            @endif
        </div>
        <div class="{{ $saCard }} p-5">
            <p class="text-xs font-medium uppercase {{ $saTextMuted }}">Combined daily cap (GMV + item ads)</p>
            @php($combinedPlan = $planned['combined'] ?? null)
            @if($combinedPlan && $combinedPlan['effective_cap'] > $combinedPlan['base_cap'])
            <p class="mt-2 text-lg font-semibold text-gray-900">Rp {{ number_format($combinedPlan['effective_cap'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs {{ $saTextMuted }}">Base Rp {{ number_format($combinedPlan['base_cap'], 0, ',', '.') }} (boosted today)</p>
            @else
            <p class="mt-2 text-lg font-semibold text-gray-900">Rp {{ number_format($settings->daily_max_budget, 0, ',', '.') }}</p>
            @endif
        </div>
        <div class="{{ $saCard }} p-5" data-testid="shopee-ads-cron-status">
            <p class="text-xs font-medium uppercase {{ $saTextMuted }}">Cron (shopee-ads:process)</p>
            <p class="mt-2 text-lg font-semibold {{ ($cronTask?->active ?? false) ? 'text-green-700' : 'text-amber-700' }}">
                {{ ($cronTask?->active ?? false) ? 'Enabled' : 'Disabled' }}
            </p>
        </div>
        <div class="{{ $saCard }} p-5">
            <p class="text-xs font-medium uppercase {{ $saTextMuted }}">OAuth</p>
            <p class="mt-2 text-lg font-semibold {{ $connection['has_token'] && ! $connection['is_expired'] ? 'text-green-700' : 'text-red-700' }}">
                {{ $connection['has_token'] && ! $connection['is_expired'] ? 'Connected' : 'Not connected' }}
            </p>
            @if($connection['shop_id'])
            <p class="mt-1 text-xs {{ $saTextMuted }}">Shop {{ $connection['shop_id'] }}</p>
            @endif
            @if($connection['last_error'])
            <p class="mt-2 text-xs text-red-600">{{ $oauthErrorHint ?? $connection['last_error'] }}</p>
            @endif
        </div>
    </div>

    <div class="{{ $saCard }} px-5 py-3" data-testid="shopee-ads-cron-details">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-xs {{ $saTextMuted }}">
            @if($cronTask?->last_run_at)
            <span>Last run {{ $cronTask->last_run_at->timezone($automationTimezone)->format('d M Y H:i') }} WIB</span>
            @else
            <span class="text-amber-700">Belum ada run tercatat untuk shopee-ads:process.</span>
            @endif
            <span class="{{ $schedulerHealth['healthy'] ? $saTextMuted : 'text-amber-700' }}" data-testid="shopee-ads-scheduler-health">
                Scheduler: {{ $schedulerHealth['message'] }}
            </span>
            <span class="text-gray-400">Sekarang {{ $nowWib->format('d M Y H:i') }} WIB (GMT+7) · tz {{ $automationTimezone }}</span>
        </div>
        @if(! $schedulerHealth['healthy'])
        <p class="mt-2 text-xs text-gray-400">Contoh crontab: <code class="text-xs">* * * * * cd /path/to/aria && php artisan schedule:run >> /dev/null 2>&1</code></p>
        @endif
    </div>

    <div class="{{ $saCard }}">
        <div class="{{ $saCardHeader }} flex flex-wrap items-center justify-between gap-2">
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
                <thead class="{{ $saTableHead }}">
                    <tr>
                        <th class="{{ $saTh }}">Tipe</th>
                        <th class="{{ $saTh }}">Detail</th>
                    </tr>
                </thead>
                <tbody class="{{ $saDivide }}">
                    @foreach($planned as $type => $row)
                    @if($type === 'combined')
                    @continue
                    @endif
                    <tr>
                        <td class="{{ $saTd }} font-medium text-gray-900">{{ $adTypeLabels[$type] ?? $type }}</td>
                        <td class="{{ $saTd }} {{ $saTextBody }}">
                            @if($type === 'gmv_max')
                            Start Rp {{ format_amount($row['start'], 0) }}
                            + increments Rp {{ format_amount($row['increments'], 0) }}
                            → planned Rp {{ format_amount($row['planned'], 0) }}
                            @else
                            Pool Rp {{ format_amount($row['start_pool'] ?? $row['start'], 0) }}
                            ÷ {{ $row['slot_count'] ?? $settings->max_item_ads }} slots
                            = Rp {{ format_amount($row['start_per_ad'], 0) }}/ad
                            ({{ $row['active_ads'] }} aktif → Rp {{ format_amount($row['start'], 0) }})
                            @if(isset($row['effective_max_ads']) && $row['effective_max_ads'] > $settings->max_item_ads)
                            · max {{ $row['effective_max_ads'] }} slots
                            @endif
                            + increments Rp {{ format_amount($row['increments'], 0) }}
                            → planned Rp {{ format_amount($row['planned'], 0) }}
                            · {{ $row['note'] }}
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    @if(isset($planned['combined']))
                    <tr class="bg-gray-50">
                        <td class="{{ $saTd }} font-medium text-gray-900">Combined</td>
                        <td class="{{ $saTd }} {{ $saTextBody }}">
                            Planned total Rp {{ format_amount($planned['combined']['planned_total'], 0) }}
                            @if($planned['combined']['over_cap'])
                            → capped Rp {{ format_amount($planned['combined']['capped_total'], 0) }}
                            (cap Rp {{ format_amount($planned['combined']['effective_cap'], 0) }})
                            @else
                            (cap Rp {{ format_amount($planned['combined']['effective_cap'], 0) }})
                            @endif
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    @if($canEdit)
    <div class="{{ $saCard }}" x-data="{ settingsOpen: false, specialRulesOpen: false }">
        <div class="{{ $saCardHeader }} flex flex-wrap items-center justify-between gap-2">
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
        <form method="POST" action="{{ route('shopee-ads.settings.update') }}" x-show="settingsOpen" x-cloak class="space-y-4 p-5">
            @csrf
            @method('PATCH')
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <label class="block">
                    <span class="{{ $saLabel }}">GMV Max starting budget</span>
                    <input type="number" name="starting_budget_gmv_max" value="{{ $settings->starting_budget_gmv_max }}" class="{{ $saInput }}" required>
                </label>
                <label class="block">
                    <span class="{{ $saLabel }}">Combined daily cap (IDR)</span>
                    <input type="number" name="daily_max_budget" value="{{ $settings->daily_max_budget }}" class="{{ $saInput }}" required>
                </label>
                <label class="block">
                    <span class="{{ $saLabel }}">Item ads starting pool (÷ max item ads per slot)</span>
                    <input type="number" name="item_ad_starting_budget" min="{{ $settings->max_item_ads * 25000 }}" value="{{ $settings->item_ad_starting_budget }}" class="{{ $saInput }}" required>
                </label>
                <label class="block">
                    <span class="{{ $saLabel }}">Max item ads</span>
                    <input type="number" name="max_item_ads" value="{{ $settings->max_item_ads }}" class="{{ $saInput }}" required>
                </label>
                <label class="block">
                    <span class="{{ $saLabel }}">Item split high / mid / low %</span>
                    <div class="mt-1 flex gap-2">
                        <input type="number" name="item_split_high" value="{{ $settings->item_split_high }}" class="{{ $saInputSm }}" required>
                        <input type="number" name="item_split_mid" value="{{ $settings->item_split_mid }}" class="{{ $saInputSm }}" required>
                        <input type="number" name="item_split_low" value="{{ $settings->item_split_low }}" class="{{ $saInputSm }}" required>
                    </div>
                </label>
                <label class="block">
                    <span class="{{ $saLabel }}">Item ROAS off / after N checks</span>
                    <div class="mt-1 flex gap-2">
                        <input type="number" step="0.01" name="item_roas_off_threshold" value="{{ $settings->item_roas_off_threshold }}" class="{{ $saInputSm }}" required>
                        <input type="number" name="item_off_after_checks" value="{{ $settings->item_off_after_checks }}" class="{{ $saInputSm }}" required>
                    </div>
                </label>
                <label class="block">
                    <span class="{{ $saLabel }}">New item ROAS target (0 = auto)</span>
                    <input type="number" step="0.01" name="item_new_roas_target" value="{{ $settings->item_new_roas_target }}" class="{{ $saInput }}" required>
                </label>
                <label class="block">
                    <span class="{{ $saLabel }}">Daily reset WIB (H:M)</span>
                    <div class="mt-1 flex gap-2">
                        <input type="number" name="daily_reset_hour" value="{{ $settings->daily_reset_hour }}" class="{{ $saInputSm }}" required>
                        <input type="number" name="daily_reset_minute" value="{{ $settings->daily_reset_minute }}" class="{{ $saInputSm }}" required>
                    </div>
                </label>
                <label class="block">
                    <span class="{{ $saLabel }}">Item replenish max per schedule</span>
                    <input type="number" name="item_replenish_max_per_run" value="{{ $settings->item_replenish_max_per_run }}" class="{{ $saInput }}" required>
                    <p class="mt-1 text-xs {{ $saTextMuted }}">Max new ads after each produk_manual increment schedule. Daily reset fills to max {{ $settings->max_item_ads }} in one run. Legacy WIB time below is ignored by cron.</p>
                </label>
                <label class="block opacity-60">
                    <span class="{{ $saLabel }}">Item replenish WIB (legacy, unused)</span>
                    <div class="mt-1 flex gap-2">
                        <input type="number" name="item_replenish_hour" value="{{ $settings->item_replenish_hour }}" class="{{ $saInputSm }}" required>
                        <input type="number" name="item_replenish_minute" value="{{ $settings->item_replenish_minute }}" class="{{ $saInputSm }}" required>
                    </div>
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

            <div class="{{ $saInnerCard }}">
                <button
                    type="button"
                    @click="specialRulesOpen = !specialRulesOpen"
                    class="flex w-full items-center justify-between gap-2 text-left"
                    data-testid="toggle-shopee-ads-special-rules"
                    :aria-expanded="specialRulesOpen"
                >
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Aturan spesial</h3>
                        <p class="mt-1 text-xs {{ $saTextMuted }}">Multiplier diterapkan otomatis pada daily reset, replenish, dan jadwal increment (WIB).</p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-gray-500 transition-transform" :class="specialRulesOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="specialRulesOpen" x-cloak class="mt-4 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="{{ $saInnerCardWhite }}">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-800">
                                <input type="checkbox" name="double_date_enabled" value="1" {{ $settings->double_date_enabled ? 'checked' : '' }}>
                                Double date (tanggal kembar, mis. 8/8)
                            </label>
                            <div class="grid gap-2 sm:grid-cols-3 text-sm">
                                <label>
                                    <span class="{{ $saLabel }}">GMV ×</span>
                                    <input type="number" step="0.1" name="double_date_gmv_multiplier" value="{{ $settings->double_date_gmv_multiplier }}" class="{{ $saInput }}" required>
                                </label>
                                <label>
                                    <span class="{{ $saLabel }}">Item ads ×</span>
                                    <input type="number" step="0.1" name="double_date_item_ads_multiplier" value="{{ $settings->double_date_item_ads_multiplier }}" class="{{ $saInput }}" required>
                                </label>
                                <label>
                                    <span class="{{ $saLabel }}">Item budget ×</span>
                                    <input type="number" step="0.1" name="double_date_item_budget_multiplier" value="{{ $settings->double_date_item_budget_multiplier }}" class="{{ $saInput }}" required>
                                </label>
                            </div>
                        </div>
                        <div class="{{ $saInnerCardWhite }}">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-800">
                                <input type="checkbox" name="payday_enabled" value="1" {{ $settings->payday_enabled ? 'checked' : '' }}>
                                Payday (tanggal gajian)
                            </label>
                            <div class="grid gap-2 sm:grid-cols-3 text-sm">
                                <label>
                                    <span class="{{ $saLabel }}">Tanggal</span>
                                    <input type="number" name="payday_day" min="1" max="28" value="{{ $settings->payday_day }}" class="{{ $saInput }}" required>
                                </label>
                                <label>
                                    <span class="{{ $saLabel }}">GMV ×</span>
                                    <input type="number" step="0.1" name="payday_gmv_multiplier" value="{{ $settings->payday_gmv_multiplier }}" class="{{ $saInput }}" required>
                                </label>
                                <label>
                                    <span class="{{ $saLabel }}">Item ×</span>
                                    <input type="number" step="0.1" name="payday_item_multiplier" value="{{ $settings->payday_item_multiplier }}" class="{{ $saInput }}" required>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg border border-purple-200 bg-purple-50 p-4 text-sm">
                        <label class="block font-medium text-purple-900">
                            Manual boost (tombol Boost — permission <code class="text-xs">shopee-ads-boost</code>)
                        </label>
                        <label class="mt-2 block">
                            <span class="{{ $saLabel }}">Multiplier ×</span>
                            <input type="number" step="0.1" name="manual_boost_multiplier" value="{{ $settings->manual_boost_multiplier }}" class="{{ $saInput }} max-w-xs" required>
                        </label>
                        <p class="mt-2 text-xs text-purple-800">Mengalikan budget GMV Max + semua item ads yang aktif sekarang (live dari Shopee).</p>
                    </div>
                </div>
            </div>
            <button type="submit" class="{{ $saBtnPrimary }}">Simpan</button>
        </form>
    </div>

    <div class="{{ $saCard }}">
        <div class="{{ $saCardHeader }}">
            <h2 class="text-sm font-semibold text-gray-900">Tambah jadwal</h2>
        </div>
        <form method="POST" action="{{ route('shopee-ads.schedules.store') }}" class="space-y-4 p-5">
            @csrf
            <label class="block">
                <span class="{{ $saLabel }}">Tipe iklan</span>
                <select name="ad_type" class="{{ $saInput }}" required>
                    @foreach($supportedTypes as $type)
                    <option value="{{ $type }}">{{ $adTypeLabels[$type] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="{{ $saLabel }}">Waktu WIB (HH:MM)</span>
                <input type="text" name="run_time" placeholder="09:00" pattern="\d{2}:\d{2}" class="{{ $saInput }}" required>
            </label>
            <label class="block">
                <span class="{{ $saLabel }}">Increment IDR (item = total pool)</span>
                <input type="number" name="increment_idr" min="1" class="{{ $saInput }}" required>
            </label>
            <button type="submit" class="{{ $saBtnPrimary }}">Tambah / Update</button>
        </form>
    </div>
    @endif

    <div class="{{ $saCard }}">
        <div class="{{ $saCardHeader }}">
            <h2 class="text-sm font-semibold text-gray-900">Jadwal</h2>
            <p class="mt-1 text-xs {{ $saTextMuted }}">Increment jalan setelah HH:MM WIB (catch-up sampai midnight). Automasi harus <strong>Active</strong> (bukan Paused).</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="{{ $saTableHead }}">
                    <tr>
                        <th class="{{ $saTh }}">Tipe</th>
                        <th class="{{ $saTh }}">Waktu</th>
                        <th class="{{ $saTh }}">Increment</th>
                        <th class="{{ $saTh }}">Last run</th>
                        @if($canEdit)<th class="{{ $saTh }}"></th>@endif
                    </tr>
                </thead>
                <tbody class="{{ $saDivide }}">
                    @forelse($schedules as $schedule)
                    <tr>
                        <td class="{{ $saTd }} text-gray-900">
                            {{ $adTypeLabels[$schedule->ad_type] ?? $schedule->ad_type }}
                            @if(! in_array($schedule->ad_type, $supportedTypes, true))
                            <span class="text-xs text-amber-600">(legacy)</span>
                            @endif
                        </td>
                        <td class="{{ $saTd }} font-mono">{{ $schedule->run_time }}</td>
                        <td class="{{ $saTd }}">Rp {{ number_format($schedule->increment_idr, 0, ',', '.') }}</td>
                        <td class="{{ $saTd }} {{ $saTextMuted }}">{{ $schedule->last_run_at?->timezone('Asia/Jakarta')->format('d M H:i') ?? '—' }}</td>
                        @if($canEdit)
                        <td class="{{ $saTd }}">
                            <form method="POST" action="{{ route('shopee-ads.schedules.destroy', $schedule) }}" onsubmit="return confirm('Hapus?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="5" class="{{ $saTd }} py-8 text-center {{ $saTextMuted }}">Belum ada jadwal.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="{{ $saCard }}" data-testid="shopee-ads-item-ads-table">
        <div class="{{ $saCardHeader }}">
            <h2 class="text-sm font-semibold text-gray-900">Item ads (tracked)</h2>
            <p class="mt-1 text-xs {{ $saTextMuted }}">
                Tabel dari DB lokal — klik <strong>Sync Item Ads</strong> untuk tarik data live dari Shopee (bisa beberapa detik).
                <strong class="text-gray-700">Daily reset</strong> mengatur ulang budget iklan yang sudah ada dan <strong>membuat iklan baru sampai max {{ $settings->max_item_ads }}</strong>;
                setelah jadwal increment produk_manual, sistem top-up jika active &lt; max (max {{ $settings->item_replenish_max_per_run }} per jadwal).
                Tombol <strong>Replenish</strong> = manual top-up (max {{ $settings->item_replenish_max_per_run }}).
                @if($itemAdsSyncStats !== null)
                <span class="mt-1 block">Sync terakhir: {{ $itemAdsSyncStats['active'] }} aktif di Shopee
                    @if($itemAdsSyncStats['imported'] > 0)({{ $itemAdsSyncStats['imported'] }} baru)@endif
                    @if($itemAdsSyncStats['closed'] > 0)({{ $itemAdsSyncStats['closed'] }} ditutup)@endif
                </span>
                @endif
                @if($itemAdsSyncError)
                <span class="mt-1 block text-amber-700">{{ $itemAdsSyncError }}</span>
                @endif
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="{{ $saTableHead }}">
                    <tr>
                        <th class="{{ $saTh }}">Campaign</th>
                        <th class="{{ $saTh }}">Item</th>
                        <th class="{{ $saTh }}">Budget</th>
                        <th class="{{ $saTh }}">ROAS</th>
                        <th class="{{ $saTh }}">Status</th>
                    </tr>
                </thead>
                <tbody class="{{ $saDivide }}">
                    @forelse($itemAds as $ad)
                    <tr>
                        <td class="{{ $saTd }} font-mono text-xs text-gray-900">{{ $ad->campaign_id }}</td>
                        <td class="{{ $saTd }} text-gray-900">{{ $ad->item_id }}</td>
                        <td class="{{ $saTd }}">Rp {{ number_format($ad->budget, 0, ',', '.') }}</td>
                        <td class="{{ $saTd }}">{{ $ad->last_roas ?? '—' }}</td>
                        <td class="{{ $saTd }}">{{ $ad->turned_off ? 'ended' : $ad->status }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="{{ $saTd }} py-8 text-center {{ $saTextMuted }}">
                            Belum ada item ads di DB.
                            @if($connection['has_token'] && ! $connection['is_expired'])
                            Klik <strong>Sync Item Ads</strong> jika sudah ada iklan manual di Shopee, atau
                            <strong>Replenish Item Ads</strong> untuk membuat iklan baru.
                            @else
                            Authorize Shopee dulu, lalu sync atau replenish.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="{{ $saCard }}">
        <div class="{{ $saCardHeader }}">
            <h2 class="text-sm font-semibold text-gray-900">History</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="{{ $saTableHead }}">
                    <tr>
                        <th class="{{ $saTh }}">Waktu</th>
                        <th class="{{ $saTh }}">Action</th>
                        <th class="{{ $saTh }}">Tipe</th>
                        <th class="{{ $saTh }}">Before → After</th>
                        <th class="{{ $saTh }}">Message</th>
                    </tr>
                </thead>
                <tbody class="{{ $saDivide }}">
                    @forelse($history as $row)
                    <tr>
                        <td class="{{ $saTd }} {{ $saTextMuted }}">{{ $row->created_at?->timezone('Asia/Jakarta')->format('d M H:i') }}</td>
                        <td class="{{ $saTd }} text-gray-900">{{ $row->action }}</td>
                        <td class="{{ $saTd }}">{{ $adTypeLabels[$row->ad_type] ?? $row->ad_type ?? '—' }}</td>
                        <td class="{{ $saTd }} font-mono text-xs">
                            @if($row->before_budget !== null || $row->after_budget !== null)
                            {{ format_amount($row->before_budget ?? 0, 0) }} → {{ format_amount($row->after_budget ?? 0, 0) }}
                            @else — @endif
                        </td>
                        <td class="{{ $saTd }} {{ $saTextBody }}">{{ $row->message }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="{{ $saTd }} py-8 text-center {{ $saTextMuted }}">Belum ada history.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-xs text-gray-400">
        Increment membaca <strong>budget live</strong> dari Shopee (bukan starting budget / DB stale).
    </p>
</div>
@endsection
