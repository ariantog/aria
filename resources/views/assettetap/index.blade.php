@extends('layouts.app')

@section('title', 'Asset Tetap')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => route('items.index')],
    ['title' => 'Asset Tetap', 'href' => route('assettetap.index')],
];
$fmt = fn ($v) => 'Rp '.format_amount($v, 0);
@endphp

<div class="flex flex-col gap-3 p-3 sm:p-4">
    <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Asset Tetap</h2>
            <p class="mt-0.5 text-sm text-gray-500">Register peralatan / kendaraan dan nilai buku setelah penyusutan bulanan.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($can['depreciate'])
            <a href="{{ route('assettetap.depreciate') }}"
               data-testid="assettetap-run-depreciation"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Run Depreciation
            </a>
            @endif
            <a href="{{ route('reports.asset-tetap') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Nilai Buku
            </a>
            @if($can['create'])
            <a href="{{ route('assettetap.create') }}"
               data-testid="assettetap-add"
               class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Add Asset
            </a>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <form method="GET" action="{{ route('assettetap.index') }}" class="flex flex-wrap items-end gap-2">
        <div class="grid gap-1.5">
            <label class="text-sm font-medium text-gray-700" for="search">Search</label>
            <input id="search" name="search" value="{{ $filters['search'] }}"
                   data-testid="assettetap-search"
                   class="w-64 rounded-md border border-gray-300 px-2.5 py-1.5 text-sm" placeholder="Nama / kode">
        </div>
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
        <a href="{{ route('assettetap.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Clear</a>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm" data-testid="assettetap-table">
            <thead class="border-b border-gray-200 bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-3 py-2.5 font-bold">Kode</th>
                    <th class="px-3 py-2.5 font-bold">Nama</th>
                    <th class="px-3 py-2.5 font-bold">Tgl beli</th>
                    <th class="px-3 py-2.5 text-right font-bold">Harga perolehan</th>
                    <th class="px-3 py-2.5 text-right font-bold">Akum. penyusutan</th>
                    <th class="px-3 py-2.5 text-right font-bold">Nilai buku</th>
                    <th class="px-3 py-2.5 font-bold">Status</th>
                    <th class="w-16 px-3 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $row)
                    @php
                        $item = $row['item'];
                        $register = $row['register'];
                        $bought = $register && $register->hasBuyTransaction();
                    @endphp
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="px-3 py-2 font-mono text-xs text-gray-600">{{ $item->code }}</td>
                        <td class="px-3 py-2 font-medium">
                            <a href="{{ route('assettetap.show', $item) }}" class="text-blue-700 hover:underline">{{ $item->name }}</a>
                        </td>
                        <td class="px-3 py-2 text-gray-600">{{ $bought ? $register->buy_date?->format('d/m/Y') : '—' }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $bought ? $fmt($register->buy_price) : '—' }}</td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-600">{{ $bought ? $fmt($row['accumulated']) : '—' }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ $bought ? $fmt($row['nbv']) : '—' }}</td>
                        <td class="px-3 py-2">
                            @if($bought)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Aktif</span>
                            @else
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Belum dibeli</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('assettetap.show', $item) }}" class="text-xs font-medium text-blue-700 hover:underline">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-8 text-center text-sm text-gray-500">Belum ada asset tetap.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $items->links() }}</div>
</div>
@endsection
