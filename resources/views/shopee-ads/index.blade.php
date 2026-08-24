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

    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-400">Status</p>
            <p class="mt-2 text-lg font-semibold {{ $settings->isPaused() ? 'text-amber-700' : 'text-green-700' }}">
                {{ $settings->isPaused() ? 'Paused' : 'Active' }}
            </p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-400">GMV Max (tracked)</p>
            <p class="mt-2 text-lg font-semibold text-gray-900">Rp {{ number_format($settings->gms_current_budget, 0, ',', '.') }}</p>
            @if($settings->gms_campaign_id)
            <p class="mt-1 text-xs text-gray-500">Campaign {{ $settings->gms_campaign_id }}</p>
            @endif
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-400">Combined daily cap</p>
            <p class="mt-2 text-lg font-semibold text-gray-900">Rp {{ number_format($settings->daily_max_budget, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-400">OAuth</p>
            <p class="mt-2 text-lg font-semibold {{ $connection['has_token'] && ! $connection['is_expired'] ? 'text-green-700' : 'text-red-700' }}">
                {{ $connection['has_token'] && ! $connection['is_expired'] ? 'Connected' : 'Not connected' }}
            </p>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-900">Rencana end-of-day</h2>
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
                            Start Rp {{ number_format($row['start'], 0, ',', '.') }}
                            + increments Rp {{ number_format($row['increments'], 0, ',', '.') }}
                            → planned Rp {{ number_format($row['planned'], 0, ',', '.') }}
                            (cap Rp {{ number_format($row['cap'], 0, ',', '.') }})
                            @else
                            Pool/run Rp {{ number_format($row['pool_per_run'], 0, ',', '.') }}
                            · {{ $row['active_ads'] }} active ads · {{ $row['note'] }}
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($canEdit)
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-900">Pengaturan (GMV + Item)</h2>
        </div>
        <form method="POST" action="{{ route('shopee-ads.settings.update') }}" class="space-y-4 px-6 py-4">
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
                        <td class="px-6 py-3">Rp {{ number_format($schedule->increment_idr, 0, ',', '.') }}</td>
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
                        <td class="px-6 py-3">Rp {{ number_format($ad->budget, 0, ',', '.') }}</td>
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
                            {{ number_format($row->before_budget ?? 0, 0, ',', '.') }} → {{ number_format($row->after_budget ?? 0, 0, ',', '.') }}
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
