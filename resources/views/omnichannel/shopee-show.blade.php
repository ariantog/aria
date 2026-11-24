@extends('layouts.app')

@section('title', 'Shadow Shopee '.$shadow->external_order_sn)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Omnichannel', 'href' => route('omnichannel.index')],
    ['title' => $shadow->external_order_sn, 'href' => route('omnichannel.shopee.show', $shadow->external_order_sn)],
];
$compareLabels = [
    'matched' => ['Matched', 'bg-green-50 text-green-800 border-green-200'],
    'mismatch' => ['Mismatch', 'bg-amber-50 text-amber-900 border-amber-200'],
    'jubelio_missing' => ['Jubelio belum ada', 'bg-red-50 text-red-800 border-red-200'],
    'pending' => ['Pending', 'bg-gray-50 text-gray-700 border-gray-200'],
];
$lineLabels = [
    'matched' => ['Baris OK', 'bg-green-50 text-green-800 border-green-200'],
    'mismatch' => ['Baris beda', 'bg-amber-50 text-amber-900 border-amber-200'],
    'unavailable' => ['Baris N/A', 'bg-gray-50 text-gray-700 border-gray-200'],
    'skipped' => ['Baris —', 'bg-gray-50 text-gray-600 border-gray-200'],
];
[$cLabel, $cBadge] = $compareLabels[$shadow->compare_status] ?? [$shadow->compare_status, 'bg-gray-50 text-gray-700 border-gray-200'];
[$lLabel, $lBadge] = $lineLabels[$shadow->line_compare_status ?? 'skipped'] ?? [$shadow->line_compare_status, 'bg-gray-50 text-gray-700 border-gray-200'];
@endphp

<div class="flex flex-col gap-4 p-2 sm:gap-6 sm:p-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Shopee {{ $shadow->external_order_sn }}</h1>
            <p class="mt-1 text-sm text-gray-600">Read-only snapshot dari shadow compare — tidak memposting atau mengubah Jubelio.</p>
            @if($detailFetchedNow ?? false)
            <p class="mt-1 text-xs text-gray-500" data-testid="shadow-detail-fetched-now">Detail Shopee + compare dimuat sekarang dari API.</p>
            @elseif($detailFromCache ?? false)
            <p class="mt-1 text-xs text-gray-500" data-testid="shadow-detail-from-cache">Detail dari cache shadow (buka Refresh untuk paksa ambil ulang).</p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('omnichannel.shopee.refresh', $shadow->external_order_sn) }}">
                @csrf
                <button type="submit"
                        class="inline-flex h-9 items-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        data-testid="shadow-refresh-button">
                    Refresh dari Shopee
                </button>
            </form>
            <a href="{{ route('omnichannel.index') }}" class="text-sm font-medium text-blue-700 hover:underline">← Kembali ke daftar</a>
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="flex flex-wrap gap-2">
        <span class="rounded border px-2 py-1 text-xs {{ $cBadge }}">{{ $cLabel }}</span>
        <span class="rounded border px-2 py-1 text-xs {{ $lBadge }}">Item: {{ $lLabel }}</span>
        @if($shadow->compare_note)
        <span class="rounded border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-900">{{ $shadow->compare_note }}</span>
        @endif
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Ingress (Aria)</h2>
        <p class="mt-1 text-xs text-gray-500">Waktu baris pertama muncul di Aria — bukan waktu order di marketplace.</p>
        @include('omnichannel.partials.ingress-timeline', ['ingress' => $ingress ?? [], 'dataTestid' => 'omnichannel-ingress-timeline'])
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Ringkasan Shopee</h2>
            <dl class="mt-3 space-y-1 text-sm text-gray-700">
                @php $warehouse = $warehouse ?? []; @endphp
                @if(!empty($warehouse['aria_name']) || !empty($warehouse['platform_label']) || !empty($warehouse['unmapped']))
                <div class="flex justify-between gap-4" data-testid="omnichannel-shadow-warehouse">
                    <dt class="text-gray-500">Gudang</dt>
                    <dd class="text-right">
                        @if(!empty($warehouse['aria_name']))
                        {{ $warehouse['aria_name'] }}
                        @elseif(!empty($warehouse['unmapped']))
                        <span class="text-amber-800">Not mapped</span>
                        @else
                        —
                        @endif
                        @if(!empty($warehouse['platform_label']))
                        <span class="block font-mono text-xs text-gray-500">Platform: {{ $warehouse['platform_label'] }}</span>
                        @endif
                    </dd>
                </div>
                @endif
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Status</dt><dd>{{ $shadow->channel_status ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Baris</dt>
                    <dd>
                        @if(isset($shadow->line_summary['line_count']))
                        {{ $shadow->line_summary['line_count'] }}
                        @else
                        <span class="text-gray-500">belum dimuat</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Total header</dt><dd>{{ isset($shadow->line_summary['total_amount']) ? number_format($shadow->line_summary['total_amount'], 0, ',', '.') : '—' }}</dd></div>
            </dl>
            @if(empty($shadow->line_summary['lines']) && !($shadow->detailLoaded()))
            <p class="mt-3 text-sm text-gray-500">Baris item belum dimuat — buka halaman ini memicu fetch API, atau gunakan Refresh.</p>
            @endif
            @if(!empty($shadow->line_summary['lines']))
            <table class="mt-4 min-w-full text-xs">
                <thead class="text-left text-gray-500"><tr><th class="py-1 pr-2">SKU</th><th class="py-1 pr-2">Qty</th><th class="py-1">Amount</th></tr></thead>
                <tbody class="font-mono">
                @foreach($shadow->line_summary['lines'] as $line)
                <tr class="border-t border-gray-100"><td class="py-1 pr-2">{{ $line['sku'] }}</td><td class="py-1 pr-2">{{ $line['qty'] }}</td><td class="py-1">{{ number_format($line['amount'], 0, ',', '.') }}</td></tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>

        <div class="rounded-xl border border-blue-200 bg-white p-4 shadow-sm dark:border-blue-800/60 dark:bg-gray-800/50">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Jubelio terkait</h2>
            @if($jubelioOrder)
            <dl class="mt-3 space-y-1 text-sm text-gray-700">
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Invoice</dt>
                    <dd class="font-mono text-xs">
                        @if($canJubelio)
                        <a href="{{ route('jubelio.show', $jubelioOrder->id) }}" class="text-blue-700 hover:underline">{{ $jubelioOrder->invoice }}</a>
                        @else
                        {{ $jubelioOrder->invoice }}
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Status antrian</dt><dd>{{ $jubelioOrder->order_status ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Sync status</dt><dd>{{ $jubelioOrder->status }}</dd></div>
            </dl>
            @if(!empty($shadow->jubelio_line_summary['lines']))
            <table class="mt-4 min-w-full text-xs">
                <thead class="text-left text-gray-500"><tr><th class="py-1 pr-2">Kode Aria</th><th class="py-1 pr-2">Qty</th><th class="py-1">Jubelio item</th></tr></thead>
                <tbody class="font-mono">
                @foreach($shadow->jubelio_line_summary['lines'] as $line)
                <tr class="border-t border-gray-100"><td class="py-1 pr-2">{{ $line['code'] ?? '—' }}</td><td class="py-1 pr-2">{{ $line['qty'] }}</td><td class="py-1">{{ $line['jubelio_item_id'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
            @endif
            @else
            <p class="mt-3 text-sm text-gray-500">Belum ada baris jubelioorders yang cocok.</p>
            @endif
        </div>
    </div>

    @if($payloadSnippet)
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Cuplikan JSON Shopee (disimpan di shadow)</h2>
        <pre class="mt-2 max-h-80 overflow-auto rounded-lg bg-white p-3 text-xs text-gray-800 dark:bg-gray-900 dark:text-gray-100">{{ json_encode($payloadSnippet, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
    @endif
</div>
@endsection