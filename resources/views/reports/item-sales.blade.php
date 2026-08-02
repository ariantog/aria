@extends('layouts.app')

@section('title', 'Item Sales Report')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Reports', 'href' => '#'],
    ['title' => 'Item Sales', 'href' => route('reports.item-sales')],
];
$fmtNum = fn($v) => number_format((float) $v, 0, ',', '.');
$fmtCur = fn($v) => 'Rp' . number_format((float) $v, 0, ',', '.');
@endphp

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Item Sales</h1>
        <p class="text-zinc-500">Laporan penjualan per kategori dan customer.</p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('reports.item-sales') }}" class="flex flex-wrap items-end gap-4">
            <div class="grid w-[120px] gap-1.5">
                <label class="text-sm font-medium" for="bulan">Bulan</label>
                <select id="bulan" name="bulan" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                    <option value="0">Semua</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((string)($filters['bulan'] ?? '0') === (string)$m)>{{ $m }}</option>
                    @endfor
                </select>
            </div>
            <div class="grid w-[120px] gap-1.5">
                <label class="text-sm font-medium" for="tahun">Tahun</label>
                <select id="tahun" name="tahun" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                    @foreach($yearList as $y)
                        <option value="{{ $y }}" @selected((int)($filters['tahun'] ?? 0) === (int)$y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid w-[250px] gap-1.5">
                <label class="text-sm font-medium" for="search_group">Cari Grup Item</label>
                <input id="search_group" name="search_group" type="text" placeholder="Ketik nama grup..."
                       value="{{ $filters['search_group'] ?? '' }}"
                       class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
            </div>
            <div class="grid w-[120px] gap-1.5">
                <label class="text-sm font-medium" for="type">Tipe</label>
                <select id="type" name="type" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                    <option value="0">Semua</option>
                    <option value="2" @selected((string)($filters['type'] ?? '0') === '2')>Sell</option>
                    <option value="15" @selected((string)($filters['type'] ?? '0') === '15')>Return</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
                <a href="{{ route('reports.item-sales') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Bersihkan</a>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-md border bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b bg-zinc-50 text-left">
                    <th class="w-[100px] px-3 py-2 font-medium text-gray-600">Periode</th>
                    <th class="px-3 py-2 font-medium text-gray-600">Grup Item</th>
                    <th class="px-3 py-2 font-medium text-gray-600">Customer</th>
                    <th class="w-[100px] px-3 py-2 font-medium text-gray-600">Tipe</th>
                    <th class="w-[100px] px-3 py-2 text-right font-medium text-gray-600">Total Qty</th>
                    <th class="w-[160px] px-3 py-2 text-right font-medium text-gray-600">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dataList as $item)
                <tr class="border-b transition-colors hover:bg-zinc-50/50">
                    <td class="px-3 py-2 text-xs font-medium">{{ $item['month'] }}/{{ $item['year'] }}</td>
                    <td class="px-3 py-2 text-xs">
                        @if($item['group'])
                            <a href="/items-group/{{ $item['group']->id }}" class="text-blue-600 hover:underline">{{ $item['group']->name }}</a>
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-3 py-2 text-xs font-semibold text-zinc-700">{{ $item['customer']->name ?? '-' }}</td>
                    <td class="px-3 py-2">
                        <span class="rounded px-2 py-0.5 text-[10px] font-bold uppercase {{ $item['type'] == 2 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                            {{ $item['type_name'] }}
                        </span>
                    </td>
                    <td class="px-3 py-2 text-right font-mono text-xs font-bold">{{ $fmtNum($item['sum_qty']) }}</td>
                    <td class="px-3 py-2 text-right font-mono text-xs font-bold">{{ $fmtCur($item['sum_total']) }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="h-24 text-center text-gray-500">Data Kosong</td></tr>
                @endforelse
            </tbody>
        </table>
        @include('partials.pagination', ['paginator' => $dataList, 'label' => 'records'])
    </div>
</div>
@endsection
