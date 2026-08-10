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
            <p class="text-sm text-gray-500">
                Status: <span class="font-bold uppercase">{{ $stockCheck->status }}</span>
                | Gudang {{ $stockCheck->sync_cursor }}/{{ $syncedWarehouseCount }}
                | {{ $stockCheck->per_type_limit }} item + {{ $stockCheck->per_type_limit }} aset lancar/gudang
                | Permintaan {{ $stockCheck->demand_days }} hari
            </p>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-700">Progress Gudang</p>
            <div class="mt-2 text-2xl font-bold">{{ $stockCheck->sync_cursor }} / {{ $syncedWarehouseCount }}</div>
            @if($syncedWarehouseCount > 0)
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-blue-600" style="width: {{ min(100, (int) round(($stockCheck->sync_cursor / $syncedWarehouseCount) * 100)) }}%"></div>
            </div>
            @endif
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-700">Total Ketidakcocokan</p>
            <div class="mt-2 text-2xl font-bold text-red-600">{{ $stockCheck->discrepancies->count() }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-700">Perbandingan</p>
            <p class="mt-2 text-sm text-gray-600">Aria qty vs Jubelio <strong>on-hand</strong></p>
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
                            <th class="px-4 py-3">Tipe</th>
                            <th class="px-4 py-3">Warehouse (Aria)</th>
                            <th class="px-4 py-3">Location (Jubelio)</th>
                            <th class="px-4 py-3 text-center">Qty Aria</th>
                            <th class="px-4 py-3 text-center">Jubelio On Hand</th>
                            <th class="px-4 py-3 text-center">On Order (ref)</th>
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
                                <span class="italic text-gray-500">Item tidak ditemukan</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs">{{ $item->item?->type?->label() ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $item->warehouse->name ?? ('ID: '.$item->warehouse_id) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span>{{ $item->jubelio_location_name ?: '—' }}</span>
                                    <span class="font-mono text-xs text-gray-500">ID: {{ $item->jubelio_location_id }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center font-bold">{{ $item->aria_qty }}</td>
                            <td class="px-4 py-3 text-center font-bold text-blue-600">{{ $item->jubelio_on_hand ?? $item->jubelio_qty }}</td>
                            <td class="px-4 py-3 text-center text-gray-500">{{ $item->jubelio_on_order ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium {{ $diff < 0 ? 'bg-red-600 text-white' : ($diff > 0 ? 'bg-yellow-500 text-white' : 'bg-gray-800 text-white') }}">
                                    {{ $diff > 0 ? '+' . number_format($diff, 2) : number_format($diff, 2) }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center italic text-gray-500">Tidak ada ketidakcocokan ditemukan pada pengecekan ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
