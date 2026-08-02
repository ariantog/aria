@extends('layouts.app')

@section('title', 'Detail Pengecekan #' . $stockCheck->id)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Pengecekan Stok', 'href' => route('jubelio-stock-checks.index')],
    ['title' => 'Detail Pengecekan', 'href' => route('jubelio-stock-checks.show', $stockCheck->id)],
];
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center gap-4">
        <a href="{{ route('jubelio-stock-checks.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold">Pengecekan Stok #{{ $stockCheck->id }}</h1>
            <p class="text-sm text-gray-500">Status: <span class="font-bold uppercase">{{ $stockCheck->status }}</span> | Dibuat: {{ \Carbon\Carbon::parse($stockCheck->created_at)->translatedFormat('d/m/Y H:i') }}</p>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between pb-2">
                <p class="text-sm font-medium text-gray-700">Halaman Terakhir</p>
                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
            </div>
            <div class="text-2xl font-bold">{{ $stockCheck->page_tracking }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between pb-2">
                <p class="text-sm font-medium text-gray-700">Total Ketidakcocokan</p>
                <svg class="h-4 w-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            </div>
            <div class="text-2xl font-bold text-red-600">{{ $stockCheck->discrepancies->count() }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between pb-2">
                <p class="text-sm font-medium text-gray-700">Batas Ketidakcocokan</p>
                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <div class="text-2xl font-bold">200</div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h3 class="text-sm font-semibold text-gray-900">Daftar Ketidakcocokan</h3>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 font-semibold text-gray-600 uppercase">
                        <tr>
                            <th class="px-4 py-3">Item (Aria)</th>
                            <th class="px-4 py-3">Jubelio Item ID</th>
                            <th class="px-4 py-3">Warehouse (Aria)</th>
                            <th class="px-4 py-3">Location (Jubelio)</th>
                            <th class="px-4 py-3 text-center">Qty Aria</th>
                            <th class="px-4 py-3 text-center">Qty Jubelio</th>
                            <th class="px-4 py-3 text-center">Selisih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($stockCheck->discrepancies as $item)
                        @php $diff = $item->aria_qty - $item->jubelio_qty; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                @if($item->item)
                                <div class="flex flex-col">
                                    <span class="font-bold">{{ $item->item->name }}</span>
                                    <span class="font-mono text-xs text-gray-500">{{ $item->item->code }}</span>
                                </div>
                                @else
                                <span class="text-gray-500 italic">Item tidak ditemukan</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono">{{ $item->jubelio_item_id }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    <span>{{ $item->warehouse->name ?? ('ID: ' . $item->warehouse_id) }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span>{{ $item->jubelio_location_name ?: '-' }}</span>
                                    <span class="font-mono text-xs text-gray-500">ID: {{ $item->jubelio_location_id }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center font-bold">{{ $item->aria_qty }}</td>
                            <td class="px-4 py-3 text-center font-bold text-blue-600">{{ $item->jubelio_qty }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium {{ $diff < 0 ? 'bg-red-600 text-white' : ($diff > 0 ? 'bg-yellow-500 text-white' : 'bg-gray-800 text-white') }}">
                                    {{ $diff > 0 ? '+' . number_format($diff, 2) : number_format($diff, 2) }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500 italic">Tidak ada ketidakcocokan ditemukan pada pengecekan ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
