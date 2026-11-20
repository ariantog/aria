@extends('layouts.app')

@section('title', 'Omnichannel')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Omnichannel', 'href' => route('omnichannel.index')],
];
$statusLabels = [
    'matched' => ['Matched', 'bg-green-50 text-green-800 border-green-200'],
    'mismatch' => ['Mismatch', 'bg-amber-50 text-amber-900 border-amber-200'],
    'jubelio_missing' => ['Jubelio belum ada', 'bg-red-50 text-red-800 border-red-200'],
    'pending' => ['Pending', 'bg-gray-50 text-gray-700 border-gray-200'],
];
$lineLabels = [
    'matched' => ['Baris OK', 'bg-green-50 text-green-800 border-green-200'],
    'mismatch' => ['Baris beda', 'bg-amber-50 text-amber-900 border-amber-200'],
    'unavailable' => ['Baris N/A', 'bg-gray-50 text-gray-600 border-gray-200'],
    'skipped' => ['—', 'bg-gray-50 text-gray-500 border-gray-200'],
];
@endphp

<div class="flex flex-col gap-4 p-2 sm:gap-6 sm:p-4">
    <div class="flex flex-col justify-between gap-3 md:flex-row md:items-end">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Omnichannel</h1>
            <p class="mt-1 text-sm text-gray-600">Read-only: bandingkan Shopee API (termasuk baris item) vs antrian Jubelio. Tidak mengubah transaksi, stok, atau baris Jubelio.</p>
        </div>
        @if($canJubelio)
        <a href="{{ route('jubelio.index') }}" class="inline-flex h-9 items-center rounded-lg border border-blue-200 bg-white px-3 text-sm font-medium text-blue-800 hover:bg-blue-50">Jubelio Orders</a>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-gray-500">Jubelio</p>
            <p class="mt-1 text-lg font-semibold {{ $jubelioActive ? 'text-green-700' : 'text-gray-500' }}">{{ $jubelioActive ? 'Aktif' : 'Nonaktif' }}</p>
            <p class="text-xs text-gray-500">{{ number_format($jubelioPending) }} antrian sell pending</p>
        </div>
        <div class="rounded-xl border border-orange-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-gray-500">Shopee Open API</p>
            <p class="mt-1 text-lg font-semibold {{ ($shopeeConnection['has_token'] ?? false) ? 'text-green-700' : 'text-red-700' }}">
                {{ ($shopeeConnection['has_token'] ?? false) ? 'Terhubung' : 'Belum OAuth' }}
            </p>
            <p class="text-xs text-gray-500">Shop {{ $shopeeConnection['shop_id'] ?? '—' }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-gray-500">Shopee Push</p>
            <p class="mt-1 text-lg font-semibold {{ $shopeePushConfigured ? 'text-green-700' : 'text-amber-700' }}">
                {{ $shopeePushConfigured ? 'Live Push key' : 'Key kosong' }}
            </p>
            <p class="text-xs text-gray-500">Shadow {{ $shopeeShadowEnabled ? 'on' : 'off' }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-gray-500">Shadow compare</p>
            <p class="mt-1 text-sm text-gray-800">{{ $stats['matched'] }} match · {{ $stats['mismatch'] }} mismatch</p>
            <p class="text-xs text-gray-500">{{ $stats['line_mismatch'] }} beda baris · {{ $stats['jubelio_missing'] }} tanpa Jubelio</p>
        </div>
    </div>

    <form method="GET" action="{{ route('omnichannel.index') }}" class="flex flex-wrap items-end gap-2">
        <label class="flex flex-col gap-0.5">
            <span class="text-[10px] font-semibold uppercase text-gray-500">Compare</span>
            <select name="compare" class="h-9 rounded-md border border-gray-300 bg-white px-2 text-sm">
                <option value="">Semua</option>
                @foreach(array_keys($statusLabels) as $key)
                <option value="{{ $key }}" @selected(($filters['compare'] ?? '') === $key)>{{ $statusLabels[$key][0] }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex flex-col gap-0.5">
            <span class="text-[10px] font-semibold uppercase text-gray-500">Baris item</span>
            <select name="line" class="h-9 rounded-md border border-gray-300 bg-white px-2 text-sm">
                <option value="">Semua</option>
                @foreach(array_keys($lineLabels) as $key)
                @if($key === 'skipped') @continue @endif
                <option value="{{ $key }}" @selected(($filters['line'] ?? '') === $key)>{{ $lineLabels[$key][0] }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex flex-col gap-0.5">
            <span class="text-[10px] font-semibold uppercase text-gray-500">Order / invoice</span>
            <input type="text" name="invoice" value="{{ $filters['invoice'] ?? '' }}" class="h-9 w-48 rounded-md border border-gray-300 px-2 text-sm" placeholder="ordersn atau SP-…">
        </label>
        <button type="submit" class="h-9 rounded-lg bg-gray-800 px-3 text-sm font-medium text-white hover:bg-gray-900">Filter</button>
        @if(($filters['compare'] ?? '') !== '' || ($filters['line'] ?? '') !== '' || ($filters['invoice'] ?? '') !== '')
        <a href="{{ route('omnichannel.index') }}" class="h-9 inline-flex items-center rounded-lg border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50">Reset</a>
        @endif
    </form>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                <tr>
                    <th class="px-3 py-2">Shopee ordersn</th>
                    <th class="px-3 py-2">Shopee status</th>
                    <th class="px-3 py-2">Baris</th>
                    <th class="px-3 py-2">Compare</th>
                    <th class="px-3 py-2">Jubelio invoice</th>
                    <th class="px-3 py-2">Jubelio status</th>
                    <th class="px-3 py-2">Catatan</th>
                    <th class="px-3 py-2">Event</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($shadows as $row)
                @php
                    [$label, $badge] = $statusLabels[$row->compare_status] ?? [$row->compare_status, 'bg-gray-50 text-gray-700 border-gray-200'];
                    [$lineLabel, $lineBadge] = $lineLabels[$row->line_compare_status ?? 'skipped'] ?? ['—', 'bg-gray-50 text-gray-500 border-gray-200'];
                    $lineCount = $row->line_summary['line_count'] ?? null;
                @endphp
                <tr class="hover:bg-gray-50/80">
                    <td class="px-3 py-2 font-mono text-xs">
                        <a href="{{ route('omnichannel.shopee.show', $row->external_order_sn) }}" class="text-blue-700 hover:underline">{{ $row->external_order_sn }}</a>
                    </td>
                    <td class="px-3 py-2">{{ $row->channel_status ?? '—' }}</td>
                    <td class="px-3 py-2">
                        <span class="rounded border px-1.5 py-0.5 text-xs {{ $lineBadge }}">{{ $lineLabel }}</span>
                        @if($lineCount !== null)
                        <span class="ml-1 text-xs text-gray-500">({{ $lineCount }})</span>
                        @endif
                    </td>
                    <td class="px-3 py-2"><span class="rounded border px-1.5 py-0.5 text-xs {{ $badge }}">{{ $label }}</span></td>
                    <td class="px-3 py-2 font-mono text-xs">{{ $row->jubelio_invoice ?? '—' }}</td>
                    <td class="px-3 py-2">{{ $row->jubelio_order_status ?? '—' }}</td>
                    <td class="px-3 py-2 max-w-xs truncate text-xs text-gray-600" title="{{ $row->compare_note }}">{{ $row->compare_note ?? '—' }}</td>
                    <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500">{{ $row->last_event_at?->format('Y-m-d H:i') ?? '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-3 py-8 text-center text-gray-500">Belum ada shadow order. Nyalakan Shopee Order Status push (3) dan pastikan queue worker jalan.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($shadows->hasPages())
    <div class="text-sm">{{ $shadows->links() }}</div>
    @endif
</div>
@endsection