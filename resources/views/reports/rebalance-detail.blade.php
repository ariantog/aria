@extends('layouts.app')

@section('title', 'Rebalance Detail')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Stock Intelligence', 'href' => route('reports.stock-intelligence')],
    ['title' => 'Rebalance Detail', 'href' => '#'],
];
@endphp

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
    <div class="flex items-center gap-4">
        <a href="{{ route('reports.stock-intelligence') }}" class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold tracking-tight">{{ $item['name'] }}</h1>
            <p class="font-mono text-sm text-zinc-500">{{ $item['code'] }}</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b p-4">
                    <h2 class="flex items-center gap-2 text-lg font-semibold">
                        <svg class="h-5 w-5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5"/></svg>
                        Stok & Performa di Seluruh Gudang
                    </h2>
                    <p class="mt-1 text-sm text-zinc-500">Membandingkan jumlah stok dan aktivitas penjualan 30 hari terakhir.</p>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-zinc-50 text-left">
                            <th class="px-3 py-2 font-medium text-gray-600">Nama Gudang</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-600">Stok Saat Ini</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-600">Terakhir Laku</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-600">Laku (30 Hari)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($warehouseStocks as $ws)
                        <tr class="border-b {{ $ws['warehouse_id'] === $sourceWarehouse['id'] ? 'bg-amber-50/50' : '' }}">
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2 text-sm font-bold">
                                    {{ $ws['warehouse_name'] }}
                                    @if($ws['warehouse_id'] === $sourceWarehouse['id'])
                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-700">Sumber</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2 text-center font-mono font-bold text-blue-600">{{ $ws['current_stock'] }}</td>
                            <td class="px-3 py-2 text-center text-xs font-medium">{{ $ws['last_sale_date'] ?? '-' }}</td>
                            <td class="px-3 py-2 text-center font-mono font-bold text-emerald-600">{{ $ws['sold_30d'] ?? 0 }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="h-24 text-center text-zinc-500">Tidak ada data gudang.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl border border-blue-200 bg-white shadow-lg">
                <div class="border-b border-blue-100 bg-blue-50/50 p-4">
                    <h2 class="flex items-center gap-2 text-lg font-semibold text-blue-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Smart Recommendation
                    </h2>
                </div>
                <div class="p-6">
                    @if($recommendation)
                    {{-- recommendation rendering (currently controller provides null) --}}
                    <div class="space-y-4 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">Qty Rekomendasi</span>
                            <span class="text-2xl font-black text-blue-600">{{ $recommendation['suggested_qty'] }} <small class="text-xs text-zinc-400">Pcs</small></span>
                        </div>
                        <a href="{{ route('transactions.index') }}" class="block w-full rounded-lg bg-blue-600 py-4 text-center text-base font-bold text-white hover:bg-blue-700">Buat Transaksi Pemindahan</a>
                    </div>
                    @else
                    <div class="px-4 py-12 text-center">
                        <div class="mb-4 inline-block rounded-full bg-zinc-50 p-4">
                            <svg class="h-8 w-8 text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </div>
                        <p class="text-sm font-medium text-zinc-500">Tidak ada gudang tujuan potensial.</p>
                        <p class="mt-2 text-xs text-zinc-400">Semua gudang lain juga memiliki penjualan rendah untuk item ini.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
