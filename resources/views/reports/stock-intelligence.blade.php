@extends('layouts.app')

@section('title', 'Stock Intelligence')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Reports', 'href' => '#'],
    ['title' => 'Stock Intelligence', 'href' => route('reports.stock-intelligence')],
];

// Normalize $data (LengthAwarePaginator when report exists, plain array otherwise)
if ($data instanceof \Illuminate\Pagination\LengthAwarePaginator) {
    $rows = collect($data->items());
    $currentPage = $data->currentPage();
    $lastPage = $data->lastPage();
    $total = $data->total();
    $pageLinks = $data->linkCollection()->toArray();
} else {
    $rows = collect($data['data'] ?? []);
    $currentPage = $data['current_page'] ?? 1;
    $lastPage = $data['last_page'] ?? 1;
    $total = $data['total'] ?? 0;
    $pageLinks = $data['links'] ?? [];
}

$performanceTabs = [
    ['key' => 'all', 'label' => 'Semua'],
    ['key' => 'elite', 'label' => '1. Elite'],
    ['key' => 'good', 'label' => '2. Good'],
    ['key' => 'active', 'label' => '3. Active'],
    ['key' => 'lagging', 'label' => '4. Lagging'],
    ['key' => 'stagnant', 'label' => '5. Stagnant'],
    ['key' => 'deadstock', 'label' => '6. Deadstock'],
    ['key' => 'critical', 'label' => '7. Critical'],
];

$badgeClass = function ($key) {
    return match ($key) {
        'elite' => 'bg-emerald-500 text-white',
        'good' => 'bg-blue-500 text-white',
        'active' => 'bg-cyan-500 text-white',
        'lagging' => 'border border-amber-200 bg-amber-100 text-amber-700',
        'stagnant' => 'border border-orange-200 bg-orange-50 text-orange-600',
        'deadstock' => 'bg-rose-500 text-white',
        'critical' => 'bg-zinc-800 text-white',
        default => 'border border-gray-200 text-gray-700',
    };
};

$dayMap = ['Senin' => 'Monday', 'Selasa' => 'Tuesday', 'Rabu' => 'Wednesday', 'Kamis' => 'Thursday', 'Jumat' => 'Friday', 'Sabtu' => 'Saturday', 'Minggu' => 'Sunday'];

$generateDays = $settings['generate_days'] ?? [];
if (is_string($generateDays)) {
    $decoded = json_decode($generateDays, true);
    $generateDays = is_array($decoded) ? $decoded : [];
}
@endphp

<div class="flex flex-col gap-6 p-6"
     x-data="{ settingsOpen: {{ $errors->any() ? 'true' : 'false' }}, generateOpen: false, generateDays: {{ \Illuminate\Support\Js::from($generateDays) }} }">

    {{-- Header --}}
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-3xl font-black uppercase tracking-tight text-zinc-900">Stock Intelligence</h1>
            <p class="mt-1 text-sm font-medium italic text-zinc-500">
                Weighted Algorithm:
                <span class="font-bold text-zinc-900">{{ $settings['gap_weight'] * 100 }}% Gap</span> &
                <span class="font-bold text-zinc-900">{{ $settings['sale_weight'] * 100 }}% Sale History</span>
                (Max: {{ $settings['max_days'] }}d)
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{-- Search --}}
            <form method="GET" action="{{ route('reports.stock-intelligence') }}" class="flex items-center gap-2 rounded-lg border border-zinc-200 bg-white p-1 shadow-sm">
                <input type="hidden" name="performance" value="{{ $filters['performance'] ?? 'all' }}">
                @if($currentReportId)<input type="hidden" name="report_id" value="{{ $currentReportId }}">@endif
                <input name="search" placeholder="Cari item..." value="{{ $filters['search'] ?? '' }}"
                       class="h-9 w-[280px] border-none bg-transparent px-2 text-sm font-medium focus:outline-none">
                <button type="submit" class="flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-gray-100">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>
                <a href="{{ route('reports.stock-intelligence', $currentReportId ? ['report_id' => $currentReportId] : []) }}" class="flex h-8 w-8 items-center justify-center rounded text-zinc-400 hover:text-rose-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </a>
            </form>

            {{-- Generate --}}
            <button @click="generateOpen = true" class="flex h-11 items-center gap-2 rounded-lg bg-emerald-600 px-6 font-bold text-white shadow-lg hover:bg-emerald-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                Generate Laporan Hari Ini
            </button>

            {{-- Settings toggle --}}
            <button @click="settingsOpen = true" class="flex h-11 w-11 items-center justify-center rounded-lg border border-zinc-200 bg-white shadow-sm">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </button>
        </div>
    </div>

    {{-- Generate Confirmation Modal --}}
    <div x-show="generateOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="generateOpen = false">
        <div class="w-full max-w-[450px] overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex items-center gap-4 bg-emerald-600 p-6">
                <div class="rounded-full bg-white/20 p-3">
                    <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h3 class="text-xl font-black uppercase tracking-tight text-white">Konfirmasi Generate</h3>
                    <p class="mt-1 text-xs font-medium uppercase tracking-widest text-emerald-100">Manual Action Required</p>
                </div>
            </div>
            <div class="p-6 text-sm font-medium leading-relaxed text-zinc-600">
                Apakah Anda yakin ingin melakukan <span class="font-bold italic text-zinc-900">Generate Laporan Stock Intelligence</span> untuk hari ini?
                <br><br>
                Sistem akan menghitung ulang seluruh skor performa stok berdasarkan parameter algoritma yang aktif.
            </div>
            <div class="flex justify-end gap-2 border-t bg-zinc-50 p-4">
                <button @click="generateOpen = false" class="rounded px-4 py-2 text-[11px] font-bold uppercase tracking-widest text-zinc-500 hover:bg-zinc-100">Batal</button>
                <form method="POST" action="{{ route('reports.stock-intelligence.generate') }}">
                    @csrf
                    <button type="submit" class="rounded bg-emerald-600 px-6 py-2 text-[11px] font-bold uppercase tracking-widest text-white shadow-md hover:bg-emerald-700">Lanjutkan Generate</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Settings Modal --}}
    <div x-show="settingsOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="settingsOpen = false">
        <div class="w-full max-w-[425px] rounded-xl bg-white p-6 shadow-2xl">
            <form method="POST" action="{{ route('reports.stock-settings.update') }}">
                @csrf
                <h3 class="text-lg font-bold">Stock Algorithm Settings</h3>
                <p class="mt-1 text-sm text-zinc-500">Sesuaikan bobot dan nilai maksimal untuk perhitungan skor performa stok.</p>
                <div class="grid gap-4 py-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium" for="gap_weight">Bobot Gap (0.0 - 1.0)</label>
                            <input id="gap_weight" name="gap_weight" type="number" step="0.1" value="{{ old('gap_weight', $settings['gap_weight']) }}" class="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium" for="sale_weight">Bobot Sale (0.0 - 1.0)</label>
                            <input id="sale_weight" name="sale_weight" type="number" step="0.1" value="{{ old('sale_weight', $settings['sale_weight']) }}" class="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium" for="max_days">Max Days (Hari)</label>
                            <input id="max_days" name="max_days" type="number" value="{{ old('max_days', $settings['max_days']) }}" class="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium" for="total_rows">Total Data (Baris)</label>
                            <input id="total_rows" name="total_rows" type="number" min="100" max="10000" value="{{ old('total_rows', $settings['total_rows']) }}" class="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                        </div>
                    </div>
                    <div class="space-y-3">
                        <label class="text-sm font-medium">Hari Generet Laporan</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($dayMap as $id => $en)
                            <button type="button"
                                    @click="generateDays.includes('{{ $en }}') ? generateDays = generateDays.filter(d => d !== '{{ $en }}') : generateDays.push('{{ $en }}')"
                                    :class="generateDays.includes('{{ $en }}') ? 'scale-105 bg-zinc-900 text-white shadow-md' : 'border border-zinc-200 bg-transparent text-zinc-500'"
                                    class="h-8 rounded px-3 text-[11px] font-bold uppercase transition-all">{{ $id }}</button>
                            @endforeach
                        </div>
                        <template x-for="d in generateDays" :key="d"><input type="hidden" name="generate_days[]" :value="d"></template>
                        <p class="text-[10px] font-medium italic text-zinc-400">* Cron hanya akan berjalan pada hari-hari yang dipilih.</p>
                    </div>
                </div>
                <div class="flex flex-col gap-2 border-t pt-4 sm:flex-row">
                    <button type="submit" formaction="{{ route('reports.stock-settings.reset') }}"
                            onclick="return confirm('Apakah Anda yakin ingin mengembalikan semua pengaturan algoritma ke nilai default?')"
                            class="rounded px-3 py-2 text-sm text-zinc-400 hover:bg-rose-50 hover:text-rose-500">Reset to Default</button>
                    <button type="submit" class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4m0 0L8 3m4 4V3"/></svg>
                        Simpan Konfigurasi
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- History Selector --}}
    <div class="flex flex-col gap-4">
        <div class="flex items-center gap-3 overflow-x-auto pb-2">
            <div class="flex shrink-0 items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-100 px-3 py-2">
                <svg class="h-4 w-4 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-xs font-bold uppercase tracking-wider text-zinc-500">Riwayat:</span>
            </div>
            @foreach($reportHistory as $report)
            <a href="{{ route('reports.stock-intelligence', ['report_id' => $report['id']]) }}"
               class="shrink-0 rounded-lg border px-4 py-2 text-xs font-bold transition-all {{ $currentReportId === $report['id'] ? 'border-zinc-900 bg-zinc-900 text-white shadow-md' : 'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300' }}">
                {{ $report['label'] }}
            </a>
            @endforeach
        </div>

        @if($reportInfo)
        <div class="flex flex-col justify-between gap-6 rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-xl md:flex-row md:items-center">
            <div class="flex items-center gap-5">
                <div class="rounded-xl bg-zinc-800 p-4">
                    <svg class="h-8 w-8 text-zinc-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="mb-1 text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400">Terakhir Diperbarui</div>
                    <div class="flex items-baseline gap-3 font-mono text-3xl font-black tracking-tight text-white tabular-nums">
                        {{ $reportInfo['generet_at'] }}
                        <span class="text-sm font-bold text-zinc-500">({{ $reportInfo['last_update_days_ago'] }})</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-6 text-right">
                <div class="flex flex-col items-end">
                    <div class="mb-1.5 text-[10px] font-black uppercase tracking-widest text-zinc-500">Metode</div>
                    <span class="rounded-full px-4 py-1 text-xs font-black uppercase shadow-lg {{ strtolower($reportInfo['type']) === 'cron' ? 'bg-blue-500 text-white' : 'bg-amber-500 text-white' }}">{{ $reportInfo['type'] }}</span>
                </div>
                <div class="h-12 w-px bg-zinc-800 opacity-50"></div>
                <div class="flex flex-col">
                    <span class="mb-1 text-[10px] font-black uppercase tracking-widest text-zinc-500">Oleh</span>
                    <span class="text-lg font-black uppercase tracking-tight text-white">{{ $reportInfo['generet_by'] }}</span>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Performance Tabs --}}
    <div class="flex flex-wrap gap-2">
        @foreach($performanceTabs as $tab)
        @php
            $isActive = ($filters['performance'] ?? 'all') === $tab['key'];
            $count = $stats[$tab['key']] ?? 0;
            $tabParams = array_filter([
                'performance' => $tab['key'],
                'search' => $filters['search'] ?? null,
                'report_id' => $currentReportId,
            ], fn($v) => $v !== null && $v !== '');
        @endphp
        <a href="{{ route('reports.stock-intelligence', $tabParams) }}"
           class="flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition-all {{ $isActive ? 'border-zinc-900 bg-zinc-900 text-white shadow-sm' : 'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300' }}">
            {{ $tab['label'] }}
            <span class="rounded-md px-1.5 py-0.5 text-[10px] {{ $isActive ? 'bg-zinc-700 text-zinc-100' : 'bg-zinc-100 text-zinc-500' }}">{{ $count }}</span>
        </a>
        @endforeach
    </div>

    {{-- Data Table --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b bg-zinc-50/50 text-left">
                    <th class="w-[280px] px-3 py-2 text-[11px] font-bold uppercase tracking-wider text-gray-600">Item Info</th>
                    <th class="px-3 py-2 text-center text-[11px] font-bold uppercase tracking-wider text-gray-600">Score</th>
                    <th class="px-3 py-2 text-[11px] font-bold uppercase tracking-wider text-gray-600">Performance</th>
                    <th class="px-3 py-2 text-[11px] font-bold uppercase tracking-wider text-gray-600">Current Warehouse</th>
                    <th class="px-3 py-2 text-[11px] font-bold uppercase tracking-wider text-gray-600">Best Performance</th>
                    <th class="px-3 py-2 text-center text-[11px] font-bold uppercase tracking-wider text-gray-600">Gap Days</th>
                    <th class="px-3 py-2 text-right text-[11px] font-bold uppercase tracking-wider text-gray-600">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $item)
                @php
                    $cw = $item['current_warehouse'];
                    $bpw = $item['best_performing_warehouse'] ?? null;
                    $gap = $item['gap_days'];
                    $isBest = $bpw && $bpw['name'] === $cw['name'];
                @endphp
                <tr class="border-b">
                    <td class="px-3 py-2">
                        <div class="font-bold leading-tight text-zinc-900">{{ $item['item_name'] }}</div>
                        <div class="mt-1 text-[10px] uppercase text-zinc-400">ID: {{ $item['item_id'] }}</div>
                    </td>
                    <td class="px-3 py-2 text-center">
                        <div class="font-mono text-xl font-black">{{ number_format((float) $item['score'], 4) }}</div>
                    </td>
                    <td class="px-3 py-2">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeClass($item['performance_key']) }}">{{ $item['performance_level'] }}</span>
                    </td>
                    <td class="px-3 py-2">
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-1.5 text-sm font-medium text-zinc-700">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                {{ $cw['name'] }}
                            </div>
                            <div class="text-[10px] font-medium text-zinc-500">Last Sale: {{ $cw['last_sale'] }}</div>
                            <div class="mt-0.5 flex items-center gap-2 text-xs">
                                <span class="rounded bg-zinc-100 px-1.5 py-0.5 font-bold text-zinc-600">QTY: {{ $cw['qty'] }}</span>
                                <span class="text-zinc-500">{{ $cw['days_ago'] === 'NEVER SOLD' ? 'Never' : $cw['days_ago'].'d' }}</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-2">
                        @if($bpw)
                        <div class="flex flex-col gap-1">
                            <div class="text-sm font-medium italic leading-tight text-emerald-700">{{ $bpw['name'] }}</div>
                            <div class="text-[10px] text-zinc-500">Last Sale: {{ $bpw['last_sale'] }}</div>
                            <div class="mt-0.5 flex items-center gap-2 text-[11px]">
                                <span class="font-bold uppercase text-emerald-600">Stok: {{ $bpw['qty'] }}</span>
                                <span class="text-zinc-400">({{ $bpw['days_ago'] }}d ago)</span>
                            </div>
                        </div>
                        @else
                        -
                        @endif
                    </td>
                    <td class="px-3 py-2 text-center">
                        <div class="font-mono text-base font-bold {{ $gap === 'NEVER SOLD' ? 'text-zinc-400' : ((int) $gap > 30 ? 'text-rose-600' : 'text-zinc-900') }}">
                            {{ $gap === 'NEVER SOLD' ? '-' : '+'.$gap }}
                        </div>
                    </td>
                    <td class="px-3 py-2 text-right">
                        <div class="flex justify-end">
                            @if($isBest)
                            <div class="flex items-center gap-1.5 rounded-md border border-emerald-100 bg-emerald-50 px-2.5 py-1.5 text-[10px] font-bold uppercase text-emerald-600 shadow-sm">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Gudang Terbaik
                            </div>
                            @else
                            <a href="{{ route('reports.rebalance-detail', ['item_id' => $item['item_id'], 'warehouse_id' => $cw['id']]) }}"
                               class="flex h-8 items-center gap-1.5 rounded border border-zinc-200 px-3 text-sm shadow-sm hover:bg-gray-50">
                                <svg class="h-3.5 w-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                Rebalance
                            </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="h-32 text-center italic text-zinc-500">Data kosong.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($lastPage > 1)
    <div class="mt-4 flex items-center justify-between">
        <p class="text-xs font-medium text-zinc-500">Halaman {{ $currentPage }} dari {{ $lastPage }} ({{ $total }} item)</p>
        <div class="flex gap-1">
            @foreach($pageLinks as $link)
            @php $url = $link['url'] ? ($link['url'].($currentReportId ? '&report_id='.$currentReportId : '')) : '#'; @endphp
            <a href="{{ $url }}"
               class="rounded-md border px-3 py-1.5 text-xs font-bold transition-all {{ $link['active'] ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-600' }} {{ $link['url'] ? '' : 'cursor-not-allowed opacity-30' }}">{!! $link['label'] !!}</a>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
