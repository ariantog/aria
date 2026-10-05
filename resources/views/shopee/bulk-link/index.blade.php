@extends('layouts.app')

@section('title', 'Shopee Bulk Link')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Shopee Warehouse Mapping', 'href' => route('shopee.sync.index')],
    ['title' => 'Bulk Link', 'href' => route('shopee.bulk-link.index')],
];
$preview = $preview ?? null;
$applyResult = $applyResult ?? null;
$rows = $applyResult['rows'] ?? ($preview['rows'] ?? []);
$summary = $applyResult['summary'] ?? ($preview['summary'] ?? null);
@endphp

<div class="flex flex-col gap-4 p-4 sm:p-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Shopee Bulk Link</h1>
        <p class="mt-1 max-w-3xl text-sm text-gray-500">
            Upload export Shopee <span class="font-mono">DATA LENGKAP PRODUK</span> (Excel) atau CSV sejenis.
            Yang dipakai: <strong>kolom 2</strong> Kode Variasi → <span class="font-mono">shopee_model_id</span>,
            <strong>kolom 3</strong> SKU → dicocokkan ke Aria (<span class="font-mono">legacy_code</span> dulu, lalu <span class="font-mono">code</span>).
            Kolom 1 Kode Produk disimpan sebagai <span class="font-mono">shopee_item_id</span> (tidak dipakai untuk pencarian SKU).
        </p>
    </div>

    @if($flash['success'] ?? null)
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $flash['success'] }}</div>
    @endif
    @if($flash['error'] ?? null)
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
    @endif

    @if(! ($stockReady ?? false))
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        STOCK CHECKER belum siap — apply butuh OAuth di
        <a href="{{ route('shopee.sync.index') }}" class="font-medium underline">Warehouse Map</a>.
        Preview tetap bisa tanpa OAuth.
    </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-900">Format file</h2>
            <p class="mt-2 text-sm text-gray-600">Baris pertama = header Shopee. Maks. {{ number_format(\App\Services\Shopee\ShopeeItemBulkLinkParser::MAX_ROWS, 0, ',', '.') }} baris.</p>
            <div class="mt-3 overflow-x-auto rounded-lg border border-gray-100 bg-gray-50">
                <table class="min-w-full text-left text-xs">
                    <thead class="border-b border-gray-200 text-gray-500">
                        <tr>
                            <th class="px-3 py-2 font-medium">Kode Produk</th>
                            <th class="px-3 py-2 font-medium">Kode Variasi</th>
                            <th class="px-3 py-2 font-medium">SKU</th>
                        </tr>
                    </thead>
                    <tbody class="font-mono text-gray-800">
                        <tr class="border-b border-gray-100"><td class="px-3 py-2 text-gray-400">58000473010</td><td class="px-3 py-2">395043520573</td><td class="px-3 py-2">ELBOWSUPPORT-05-BLUE</td></tr>
                        <tr><td class="px-3 py-2 text-gray-400">57918450371</td><td class="px-3 py-2">391549925714</td><td class="px-3 py-2">DUMBBELL-07-BLACK-32KG</td></tr>
                    </tbody>
                </table>
            </div>
            <ul class="mt-3 list-inside list-disc text-xs text-gray-500">
                <li>Urutan lookup SKU kolom 3: <span class="font-mono">legacy_code</span> → <span class="font-mono">code</span> → baris error</li>
                <li>Format manual masih didukung: <span class="font-mono">code</span>, <span class="font-mono">shopee_item_id</span>, <span class="font-mono">shopee_model_id</span></li>
            </ul>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-900">Upload</h2>
            <form method="POST" action="{{ route('shopee.bulk-link.preview') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="bulk-link-file" class="block text-xs font-medium text-gray-700">File (.xlsx, .xls, .csv)</label>
                    <input id="bulk-link-file" name="file" type="file" accept=".xlsx,.xls,.csv,.txt" required
                           class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-orange-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-orange-700 hover:file:bg-orange-100">
                </div>
                <button type="submit" data-testid="shopee-bulk-link-preview"
                        class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
                    Preview
                </button>
            </form>
            <p class="mt-3 text-xs text-gray-400">
                <a href="{{ route('shopee.auto-link.index') }}" class="text-blue-600 hover:underline">Auto Link</a>
                untuk pencarian otomatis tanpa file.
            </p>
        </div>
    </div>

    @if($preview && ! $applyResult)
    <div class="rounded-xl border border-blue-200 bg-white p-5 shadow-sm" data-testid="shopee-bulk-link-preview-panel">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Preview</h2>
                @if($summary)
                <p class="mt-1 text-sm text-gray-600">
                    {{ $preview['rows_total'] ?? $summary['total'] }} baris —
                    <span class="text-emerald-700">{{ $summary['ready'] ?? 0 }} siap</span>,
                    {{ $summary['skipped'] }} skipped,
                    <span class="text-red-600">{{ $summary['errors'] }} error</span>
                    @if($preview['rows_truncated'] ?? false)
                        <span class="text-gray-500">(tabel: {{ count($rows) }} baris pertama)</span>
                    @endif
                </p>
                @endif
            </div>
            <form method="POST" action="{{ route('shopee.bulk-link.apply') }}" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="hidden" name="token" value="{{ $preview['token'] ?? '' }}">
                <label class="flex items-center gap-2 text-xs text-gray-600">
                    <input type="checkbox" name="overwrite_existing" value="1" class="rounded border-gray-300">
                    Overwrite SKU yang sudah punya Shopee ID
                </label>
                <button type="submit" data-testid="shopee-bulk-link-apply"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    Apply links
                </button>
            </form>
        </div>

        @include('shopee.bulk-link.partials.result-table', ['rows' => $rows])
    </div>
    @endif

    @if($applyResult)
    <div class="rounded-xl border border-emerald-200 bg-white p-5 shadow-sm" data-testid="shopee-bulk-link-apply-panel">
        <h2 class="text-sm font-semibold text-gray-900">Hasil apply</h2>
        @if($summary)
        <p class="mt-1 text-sm text-gray-600">
            {{ $applyResult['rows_total'] ?? $summary['total'] }} baris —
            {{ $summary['linked'] ?? 0 }} linked, {{ $summary['skipped'] }} skipped, {{ $summary['errors'] }} error
            @if($applyResult['rows_truncated'] ?? false)
                <span class="text-gray-500">(tabel: {{ count($rows) }} baris pertama)</span>
            @endif
        </p>
        @endif
        @include('shopee.bulk-link.partials.result-table', ['rows' => $rows])
    </div>
    @endif
</div>
@endsection
