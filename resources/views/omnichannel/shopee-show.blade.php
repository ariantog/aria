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
        </div>
        <a href="{{ route('omnichannel.index') }}" class="text-sm font-medium text-blue-700 hover:underline">← Kembali ke daftar</a>
    </div>

    <div class="flex flex-wrap gap-2">
        <span class="rounded border px-2 py-1 text-xs {{ $cBadge }}">{{ $cLabel }}</span>
        <span class="rounded border px-2 py-1 text-xs {{ $lBadge }}">Item: {{ $lLabel }}</span>
        @if($shadow->compare_note)
        <span class="rounded border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-900">{{ $shadow->compare_note }}</span>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Ringkasan Shopee</h2>
            <dl class="mt-3 space-y-1 text-sm text-gray-700">
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Status</dt><dd>{{ $shadow->channel_status ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Baris</dt><dd>{{ $shadow->line_summary['line_count'] ?? 0 }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Total header</dt><dd>{{ isset($shadow->line_summary['total_amount']) ? number_format($shadow->line_summary['total_amount'], 0, ',', '.') : '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Event</dt><dd>{{ $shadow->last_event_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
            </dl>
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

        <div class="rounded-xl border border-blue-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Jubelio terkait</h2>
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
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
        <h2 class="text-sm font-semibold text-gray-900">Cuplikan JSON Shopee (disimpan di shadow)</h2>
        <pre class="mt-2 max-h-80 overflow-auto rounded-lg bg-white p-3 text-xs text-gray-800">{{ json_encode($payloadSnippet, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
    @endif
</div>
@endsection