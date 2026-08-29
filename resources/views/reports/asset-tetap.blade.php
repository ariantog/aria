@extends('layouts.app')

@section('title', 'Nilai Buku Asset Tetap')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Reports', 'href' => '#'],
    ['title' => 'Nilai Buku Asset Tetap', 'href' => route('reports.asset-tetap')],
];
$fmt = fn ($v) => 'Rp '.format_amount($v, 0);
@endphp

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Nilai Buku Asset Tetap</h1>
        <p class="text-gray-500">
            Per {{ $asOf->format('d M Y') }}
            @if($filters['month'])
                (bulan {{ $filters['month'] }}/{{ $filters['year'] }})
            @else
                (tahun {{ $filters['year'] }})
            @endif
        </p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('reports.asset-tetap') }}" class="flex flex-wrap items-end gap-4">
            <div class="grid w-[180px] gap-1.5">
                <label class="text-sm font-medium" for="month">Month</label>
                <select id="month" name="month" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                    <option value="all" @selected($filters['month'] === null)>All Months</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((string) ($filters['month'] ?? '') === (string) $m)>{{ $m }}</option>
                    @endfor
                </select>
            </div>
            <div class="grid w-[180px] gap-1.5">
                <label class="text-sm font-medium" for="year">Year</label>
                <select id="year" name="year" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                    @foreach($yearList as $y)
                        <option value="{{ $y }}" @selected((int) $filters['year'] === (int) $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
            <a href="{{ route('reports.asset-tetap') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Clear</a>
        </form>
    </div>

    <div class="grid gap-3 md:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-4"><p class="text-xs text-gray-500">Harga perolehan</p><p class="text-lg font-semibold tabular-nums">{{ $fmt($totals['cost']) }}</p></div>
        <div class="rounded-xl border border-gray-200 bg-white p-4"><p class="text-xs text-gray-500">Akumulasi penyusutan</p><p class="text-lg font-semibold tabular-nums">{{ $fmt($totals['accumulated']) }}</p></div>
        <div class="rounded-xl border border-gray-200 bg-white p-4"><p class="text-xs text-gray-500">Nilai buku</p><p class="text-lg font-semibold tabular-nums" data-testid="asset-tetap-report-nbv-total">{{ $fmt($totals['nbv']) }}</p></div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-sm" data-testid="asset-tetap-report-table">
            <thead class="border-b bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-3 py-2 text-left">Kode</th>
                    <th class="px-3 py-2 text-left">Nama</th>
                    <th class="px-3 py-2 text-left">Tgl beli</th>
                    <th class="px-3 py-2 text-right">Harga perolehan</th>
                    <th class="px-3 py-2 text-right">Akum. penyusutan</th>
                    <th class="px-3 py-2 text-right">Nilai buku</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php $item = $row['item']; $register = $row['register']; @endphp
                    <tr class="border-b border-gray-100">
                        <td class="px-3 py-2 font-mono text-xs">{{ $item->code }}</td>
                        <td class="px-3 py-2"><a href="{{ route('assettetap.show', $item) }}" class="text-blue-700 hover:underline">{{ $item->name }}</a></td>
                        <td class="px-3 py-2">{{ $register?->hasBuyTransaction() ? $register->buy_date?->format('d/m/Y') : '—' }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($register->buy_price ?? 0) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($row['accumulated']) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-medium">{{ $fmt($row['nbv']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-8 text-center text-gray-500">Tidak ada asset tetap.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
