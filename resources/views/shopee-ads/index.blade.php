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
                Kelola budget iklan Shopee (WIB). Automasi dijalankan via cron <code>shopee-ads:process</code> setiap menit.
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
                    Replenish Groups
                </button>
            </form>
            <form method="POST" action="{{ route('shopee-ads.daily-reset') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100">
                    Daily Reset Now
                </button>
            </form>
            <a href="{{ route('shopee-ads.authorize') }}"
               class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
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
            <p class="text-xs font-medium uppercase text-gray-400">OAuth</p>
            <p class="mt-2 text-lg font-semibold {{ $connection['has_token'] && ! $connection['is_expired'] ? 'text-green-700' : 'text-red-700' }}">
                @if(! $connection['has_token'])
                Belum authorize
                @elseif($connection['is_expired'])
                Token expired
                @else
                Connected
                @endif
            </p>
            @if($connection['shop_id'])
            <p class="mt-1 text-xs text-gray-500">Shop {{ $connection['shop_id'] }}</p>
            @endif
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-400">Starting Budget</p>
            <p class="mt-2 text-lg font-semibold text-gray-900">Rp {{ number_format($settings->starting_budget, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-400">Daily Cap</p>
            <p class="mt-2 text-lg font-semibold text-gray-900">Rp {{ number_format($settings->daily_max_budget, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-900">Planned end-of-day (single ads)</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3">Tipe</th>
                        <th class="px-6 py-3">Start</th>
                        <th class="px-6 py-3">Planned</th>
                        <th class="px-6 py-3">Cap</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($planned as $type => $row)
                    <tr>
                        <td class="px-6 py-3 font-medium">{{ $adTypeLabels[$type] ?? $type }}</td>
                        <td class="px-6 py-3">Rp {{ number_format($row['start'], 0, ',', '.') }}</td>
                        <td class="px-6 py-3">Rp {{ number_format($row['planned'], 0, ',', '.') }}</td>
                        <td class="px-6 py-3">Rp {{ number_format($row['cap'], 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($canEdit)
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-sm font-semibold text-gray-900">Pengaturan</h2>
            </div>
            <form method="POST" action="{{ route('shopee-ads.settings.update') }}" class="space-y-4 px-6 py-4">
                @csrf
                @method('PATCH')
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="text-gray-600">Starting budget (IDR)</span>
                        <input type="number" name="starting_budget" value="{{ old('starting_budget', $settings->starting_budget) }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-600">Daily max budget (IDR)</span>
                        <input type="number" name="daily_max_budget" value="{{ old('daily_max_budget', $settings->daily_max_budget) }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-600">Toko auto campaign ID</span>
                        <input type="text" name="toko_auto_campaign_id" value="{{ old('toko_auto_campaign_id', $settings->toko_auto_campaign_id) }}" class="mt-1 w-full rounded-lg border-gray-300">
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-600">Toko manual campaign ID</span>
                        <input type="text" name="toko_manual_campaign_id" value="{{ old('toko_manual_campaign_id', $settings->toko_manual_campaign_id) }}" class="mt-1 w-full rounded-lg border-gray-300">
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-600">Produk auto campaign ID</span>
                        <input type="text" name="produk_auto_campaign_id" value="{{ old('produk_auto_campaign_id', $settings->produk_auto_campaign_id) }}" class="mt-1 w-full rounded-lg border-gray-300">
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-600">Group split high %</span>
                        <input type="number" name="group_split_high" value="{{ old('group_split_high', $settings->group_split_high) }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-600">Group split mid %</span>
                        <input type="number" name="group_split_mid" value="{{ old('group_split_mid', $settings->group_split_mid) }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-600">Group split low %</span>
                        <input type="number" name="group_split_low" value="{{ old('group_split_low', $settings->group_split_low) }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-600">ROAS off threshold</span>
                        <input type="number" step="0.01" name="group_roas_off_threshold" value="{{ old('group_roas_off_threshold', $settings->group_roas_off_threshold) }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-600">Off after N low ROAS increments</span>
                        <input type="number" name="group_off_after_increments" value="{{ old('group_off_after_increments', $settings->group_off_after_increments) }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-600">Daily reset (WIB hour:minute)</span>
                        <div class="mt-1 flex gap-2">
                            <input type="number" name="daily_reset_hour" min="0" max="23" value="{{ $settings->daily_reset_hour }}" class="w-full rounded-lg border-gray-300" required>
                            <input type="number" name="daily_reset_minute" min="0" max="59" value="{{ $settings->daily_reset_minute }}" class="w-full rounded-lg border-gray-300" required>
                        </div>
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-600">Replenish time (WIB)</span>
                        <div class="mt-1 flex gap-2">
                            <input type="number" name="group_replenish_hour" min="0" max="23" value="{{ $settings->group_replenish_hour }}" class="w-full rounded-lg border-gray-300" required>
                            <input type="number" name="group_replenish_minute" min="0" max="59" value="{{ $settings->group_replenish_minute }}" class="w-full rounded-lg border-gray-300" required>
                        </div>
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-600">Target active groups</span>
                        <input type="number" name="group_target_active_count" value="{{ $settings->group_target_active_count }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-600">Max new groups / run</span>
                        <input type="number" name="group_replenish_max_per_run" value="{{ $settings->group_replenish_max_per_run }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-600">Replenish min ROAS (recycled)</span>
                        <input type="number" step="0.01" name="group_replenish_min_roas" value="{{ $settings->group_replenish_min_roas }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-600">New group ROAS target (0 = GMV Max)</span>
                        <input type="number" step="0.01" name="group_roas_target" value="{{ $settings->group_roas_target }}" class="mt-1 w-full rounded-lg border-gray-300" required>
                    </label>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="group_replenish_enabled" value="1" {{ $settings->group_replenish_enabled ? 'checked' : '' }}>
                    Auto replenishment enabled
                </label>
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
                        @foreach($adTypeLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-gray-600">Waktu (WIB HH:MM)</span>
                    <input type="text" name="run_time" placeholder="09:00" pattern="\d{2}:\d{2}" class="mt-1 w-full rounded-lg border-gray-300" required>
                </label>
                <label class="block text-sm">
                    <span class="text-gray-600">Increment IDR (group = total pool)</span>
                    <input type="number" name="increment_idr" min="1" class="mt-1 w-full rounded-lg border-gray-300" required>
                </label>
                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Tambah / Update</button>
            </form>
        </div>
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
                        <th class="px-6 py-3">Waktu WIB</th>
                        <th class="px-6 py-3">Increment</th>
                        <th class="px-6 py-3">Last run</th>
                        @if($canEdit)<th class="px-6 py-3"></th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($schedules as $schedule)
                    <tr>
                        <td class="px-6 py-3">{{ $adTypeLabels[$schedule->ad_type] ?? $schedule->ad_type }}</td>
                        <td class="px-6 py-3 font-mono">{{ $schedule->run_time }}</td>
                        <td class="px-6 py-3">Rp {{ number_format($schedule->increment_idr, 0, ',', '.') }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ $schedule->last_run_at?->timezone('Asia/Jakarta')->format('d M H:i') ?? '—' }}</td>
                        @if($canEdit)
                        <td class="px-6 py-3">
                            <form method="POST" action="{{ route('shopee-ads.schedules.destroy', $schedule) }}" onsubmit="return confirm('Hapus jadwal?')">
                                @csrf
                                @method('DELETE')
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
            <h2 class="text-sm font-semibold text-gray-900">History (50 terakhir)</h2>
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
                        <td class="px-6 py-3">{{ $row->ad_type ?? '—' }}</td>
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
</div>
@endsection
